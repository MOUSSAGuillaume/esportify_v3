import { api, fetchCsrf } from "../js/api.js";
import { toast } from "../js/ui.js";

//DOM
const listEl = document.getElementById("eventsList");
const sortSelect = document.getElementById("sortSelect");

// Optionnels (si tu as ajouté la barre filtres)
const searchInput = document.getElementById("searchInput");
const organizerSelect = document.getElementById("organizerSelect");
const statusSelect = document.getElementById("statusSelect");

let events = [];
let currentUser = null;

 //Utils
function getStartDate(e) {
  return e.start_at ?? e.start_at ?? null;
}

function getRegisteredCount(e) {
  return Number(e.registeredCount ?? e.registered_count ?? 0) || 0;
}

function getMaxPlayers(e) {
  return Number(e.maxPlayers ?? e.max_players ?? 0) || 0;
}

function safeText(v, fallback = "") {
  if (v === null || v === undefined) return fallback;
  const s = String(v).trim();
  return s.length ? s : fallback;
}

function normalizeStr(v) {
  return safeText(v).toLowerCase();
}

function computeProgress(registered, max) {
  if (!max || max <= 0) return 0;
  return Math.max(0, Math.min((registered / max) * 100, 100));
}

function formatDate(dateValue) {
  if (!dateValue) return "Date inconnue";
  const d = new Date(dateValue);
  if (Number.isNaN(d.getTime())) return "Date invalide";
  return d.toLocaleString();
}

//Data loading
async function loadCurrentUser() {
  try {
    const me = await api("/me"); // peut être {user: {...}} ou {...}
    currentUser = me?.user ?? me;
  } catch {
    currentUser = null; // visiteur
  }
}

async function loadEvents() {
  const data = await api("/events");
  events = data?.events ?? data ?? [];
  if (!Array.isArray(events)) events = [];
}

//Filters
function buildOrganizerOptions(items) {
  if (!organizerSelect) return;

  // essaie plusieurs noms possibles
  const names = new Map();

  for (const e of items) {
    const name =
      e.organizerName ??
      e.organizer_name ??
      e.organizer?.name ??
      null;

    const id =
      e.organizerId ??
      e.organizer_id ??
      e.organizer?.id ??
      name; // fallback si pas d'id

    if (name) names.set(String(id), String(name));
  }

  // garde la sélection actuelle
  const current = organizerSelect.value;

  organizerSelect.innerHTML = `
    <option value="">Tous les organisateurs</option>
    ${[...names.entries()]
      .sort((a, b) => a[1].localeCompare(b[1]))
      .map(([id, name]) => `<option value="${id}">${name}</option>`)
      .join("")}
  `;

  // restore si possible
  if (current) organizerSelect.value = current;
}

function applyFilters(items) {
  let out = [...items];

  // Recherche
  const q = normalizeStr(searchInput?.value ?? "");
  if (q) {
    out = out.filter(e => normalizeStr(e.name).includes(q));
  }

  // Organisateur (si dispo)
  const org = organizerSelect?.value ?? "";
  if (org) {
    out = out.filter(e => {
      const id =
        e.organizerId ??
        e.organizer_id ??
        e.organizer?.id ??
        (e.organizerName ?? e.organizer_name ?? "");
      return String(id) === String(org);
    });
  }

  // Statut (si dispo)
  const st = statusSelect?.value ?? "";
  if (st) {
    out = out.filter(e => String(e.status ?? e.eventStatus ?? "").toUpperCase() === st);
  }

  return out;
}

//Sorting
function sortItems(items) {
  const sortValue = sortSelect?.value ?? "date_desc";

  return [...items].sort((a, b) => {
    const da = new Date(getStartDate(a) ?? 0).getTime();
    const db = new Date(getStartDate(b) ?? 0).getTime();

    if (sortValue === "date_asc") return da - db;
    return db - da; // date_desc
  });
}

//Rendering
function eventCard(e) {
  const start = getStartDate(e);
  const registered = getRegisteredCount(e);
  const maxPlayers = getMaxPlayers(e);

  const percent = computeProgress(registered, maxPlayers);

  const title =
    e.name ??
    e.title ??
    e.event_name ??
    e.eventName ??
    "Événement";
  const game =
    e.game ??
    e.game_name ??
    e.category ??
    "E-sport";

  // image simple (placeholder)
  const imgUrl = `https://picsum.photos/seed/${encodeURIComponent(
    (e.game ?? "gaming") + (e.id ?? title)
  )}/800/400`;

  return `
    <div class="col-md-6 col-lg-4">
      <div class="card text-light position-relative h-100">

        <img src="${imgUrl}"
             class="card-img-top"
             alt="${title}"
             style="height:180px; object-fit:cover;">

        <span class="badge-game">${game}</span>

        <div class="card-body d-flex flex-column">
          <h5 class="fw-bold mb-2">${title}</h5>

          <p class="text-secondary small mb-2">
            ${formatDate(start)}
          </p>

          <p class="small mb-2">
            Joueurs : ${registered} / ${maxPlayers || "—"}
          </p>

          <div class="progress mb-3" style="height:6px;">
            <div class="progress-bar bg-info" style="width:${percent}%"></div>
          </div>

          <div class="mt-auto">
            ${actionButton(e)}
          </div>
        </div>
      </div>
    </div>
  `;
}

function actionButton(e) {
  if (!currentUser) {
    return `
      <a href="./login.html" class="btn btn-outline-light w-100">
        Se connecter
      </a>
    `;
  }

  return `
    <button
      type="button"
      class="btn btn-primary w-100 btn-register"
      data-id="${e.id}">
      S'inscrire
    </button>
  `;
}

function render() {
  if (!listEl) return;

  const filtered = applyFilters(events);
  const sorted = sortItems(filtered);

  listEl.innerHTML = sorted.map(eventCard).join("");

  // Si tu veux afficher le nombre trouvé, tu peux ajouter un #resultsCount dans HTML
  // const countEl = document.getElementById("resultsCount");
  // if (countEl) countEl.textContent = `${sorted.length} événement(s) trouvé(s)`;
}

//Actions (event delegation)
async function handleRegister(eventId) {
  try {
    await fetchCsrf();
    await api(`/events/${eventId}/register`, { method: "POST", csrf: true });
    toast("Inscription réussie", "success");
    await loadEvents();
    render();
  } catch (err) {
    toast(err?.message || "Erreur", "danger");
  }
}

function bindEvents() {
  // 1 seul listener pour tous les boutons
  listEl?.addEventListener("click", (e) => {
    const btn = e.target.closest(".btn-register");
    if (!btn) return;
    const id = btn.dataset.id;
    if (!id) return;
    handleRegister(id);
  });

  sortSelect?.addEventListener("change", render);
  searchInput?.addEventListener("input", render);
  organizerSelect?.addEventListener("change", render);
  statusSelect?.addEventListener("change", render);
}

//Init
async function init() {
  try {
    await loadCurrentUser();
    await loadEvents();

    // Remplit la liste des organisateurs si possible
    buildOrganizerOptions(events);

    render();
    bindEvents();
  } catch (err) {
    toast(err?.message || "Erreur lors du chargement", "danger");
  }
}

init();