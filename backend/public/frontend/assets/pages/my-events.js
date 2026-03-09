import { api, fetchCsrf } from "../js/api.js";
import { toast } from "../js/ui.js";

const listEl = document.getElementById("list");
const stateEl = document.getElementById("state");
const subtitleEl = document.getElementById("subtitle");
const resultsCountEl = document.getElementById("resultsCount");

const tabSelect = document.getElementById("tabSelect");
const sortSelect = document.getElementById("sortSelect");
const reloadBtn = document.getElementById("reloadBtn");

let currentUser = null;
let items = []; // registrations OR events (selon ton API)

function showState(msg, type = "info") {
    stateEl.className = `alert alert-${type}`;
    stateEl.textContent = msg;
    stateEl.classList.remove("d-none");
}
function hideState() { stateEl.classList.add("d-none"); }

function safeText(v, fallback = "") {
    if (v === null || v === undefined) return fallback;
    const s = String(v).trim();
    return s.length ? s : fallback;
}

function getEvent(obj) {
    // si l’API renvoie {event:{...}} dans une registration
    return obj?.event ?? obj;
}

function getId(obj) {
    const e = getEvent(obj);
    return e.id ?? e.event_id ?? e.eventId ?? null;
}

function getTitle(obj) {
    const e = getEvent(obj);
    return e.title ?? e.name ?? e.event_name ?? e.eventName ?? "Événement";
}

function getStartDate(obj) {
    const e = getEvent(obj);
    return e.start_at ?? e.startAt ?? null;
}

function getRegisteredCount(obj) {
    const e = getEvent(obj);
    return Number(e.registeredCount ?? e.registered_count ?? 0) || 0;
}

function getMaxPlayers(obj) {
    const e = getEvent(obj);
    return Number(e.maxPlayers ?? e.max_players ?? 0) || 0;
}

function formatDate(dateValue) {
    if (!dateValue) return "Date inconnue";
    const d = new Date(dateValue);
    if (Number.isNaN(d.getTime())) return "Date invalide";
    return d.toLocaleString();
}

function isPast(startAt) {
    if (!startAt) return false;
    const d = new Date(startAt);
    return d.getTime() < Date.now();
}

function sortItems(list) {
    const mode = sortSelect?.value ?? "date_asc";
    const copy = [...list];

    const byDate = (a, b) =>
        new Date(getStartDate(a) ?? 0).getTime() - new Date(getStartDate(b) ?? 0).getTime();

    const byName = (a, b) => getTitle(a).localeCompare(getTitle(b), "fr");

    switch (mode) {
        case "date_desc": return copy.sort((a, b) => byDate(b, a));
        case "name_asc": return copy.sort(byName);
        case "name_desc": return copy.sort((a, b) => byName(b, a));
        case "date_asc":
        default: return copy.sort(byDate);
    }
}

function filterItems(list) {
    const tab = tabSelect?.value ?? "upcoming";
    if (tab === "all") return list;

    return list.filter((x) => {
        const past = isPast(getStartDate(x));
        return tab === "past" ? past : !past;
    });
}

function cardTemplate(obj) {
    const e = getEvent(obj);
    const id = getId(obj);
    const title = getTitle(obj);
    const start = getStartDate(obj);

    const registered = getRegisteredCount(obj);
    const max = getMaxPlayers(obj);

    const game = e.game ?? e.game_name ?? e.category ?? "E-sport";
    const city = e.city ?? "";

    const imgUrl = `https://picsum.photos/seed/${encodeURIComponent((game ?? "gaming") + (id ?? title))}/800/400`;

    return `
    <div class="col-md-6 col-lg-4">
      <div class="card text-light position-relative h-100">
        <img src="${imgUrl}" class="card-img-top" alt="${safeText(title)}" style="height:180px; object-fit:cover;">
        <span class="badge-game">${safeText(game, "E-sport")}</span>

        <div class="card-body d-flex flex-column">
          <h5 class="fw-bold mb-2">${safeText(title)}</h5>

          <p class="text-secondary small mb-2">${formatDate(start)}</p>

          <p class="small mb-3">
            ${city ? `📍 ${safeText(city)}<br/>` : ``}
            Joueurs : ${registered} / ${max || "—"}
          </p>

          <div class="mt-auto d-flex gap-2">
            <a class="btn btn-outline-light w-100" href="/event?id=${encodeURIComponent(id)}">Voir</a>
            <button type="button" class="btn btn-outline-danger w-100 btn-unregister" data-id="${id}">
              Se désinscrire
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
}

function render() {
    hideState();

    const filtered = filterItems(items);
    const sorted = sortItems(filtered);

    resultsCountEl.textContent = String(sorted.length);

    if (!sorted.length) {
        listEl.innerHTML = "";
        showState("Aucune inscription à afficher.", "info");
        return;
    }

    listEl.innerHTML = sorted.map(cardTemplate).join("");
}

async function loadCurrentUser() {
    const me = await api("/me");
    currentUser = me?.user ?? me;
}

async function tryLoadMyRegistrations() {
    const data = await api("/me");

    if (Array.isArray(data?.registrations)) return data.registrations;
    if (Array.isArray(data?.items)) return data.items;
    if (Array.isArray(data?.data)) return data.data;

    return [];
}

async function handleUnregister(eventId) {
    try {
        await fetchCsrf();
        await api(`/events/${eventId}/register`, { method: "DELETE", csrf: true });
        toast("Désinscription réussie", "success");
        items = items.filter(x => String(getId(x)) !== String(eventId));
        render();
    } catch (err) {
        toast(err?.message || "Erreur", "danger");
    }
}

function bind() {
    listEl?.addEventListener("click", (e) => {
        const btn = e.target.closest(".btn-unregister");
        if (!btn) return;
        const id = btn.dataset.id;
        if (!id) return;
        handleUnregister(id);
    });

    tabSelect?.addEventListener("change", render);
    sortSelect?.addEventListener("change", render);

    reloadBtn?.addEventListener("click", async () => {
        try {
            showState("Rafraîchissement…", "info");
            items = await tryLoadMyRegistrations();
            render();
        } catch (err) {
            showState(err?.message || "Erreur de chargement", "danger");
        }
    });
}

async function init() {
    try {
        await loadCurrentUser();
    } catch {
        // non connecté → login
        window.location.href = "/login";
        return;
    }

    subtitleEl.textContent = `Connecté : ${safeText(currentUser?.email, "joueur")}`;

    try {
        items = await tryLoadMyRegistrations();
        render();
        bind();
    } catch (err) {
        console.error(err);
        showState(err?.message || "Erreur de chargement", "danger");
    }
}

init();