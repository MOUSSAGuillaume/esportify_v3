import { api, fetchCsrf } from "../js/api.js";
import { toast } from "../js/ui.js";

const qs = new URLSearchParams(location.search);
const eventId = qs.get("id");

const stateEl = document.getElementById("state");
const titleEl = document.getElementById("title");
const metaEl = document.getElementById("meta");
const descEl = document.getElementById("description");
const dateEl = document.getElementById("date");
const gameEl = document.getElementById("game");
const slotsEl = document.getElementById("slots");
const statusEl = document.getElementById("status");

const btnRegister = document.getElementById("btnRegister");
const btnUnregister = document.getElementById("btnUnregister");

let currentUser = null;
let event = null;

function showState(msg, type = "info") {
    if (!stateEl) return;
    stateEl.className = `alert alert-${type}`;
    stateEl.textContent = msg;
    stateEl.classList.remove("d-none");
}

function hideState() {
    stateEl?.classList.add("d-none");
}

function safeText(v, fallback = "—") {
    if (v === null || v === undefined) return fallback;
    const s = String(v).trim();
    return s.length ? s : fallback;
}

function getStartDate(e) {
    return e.start_at ?? e.startAt ?? null;
}

function getRegisteredCount(e) {
    return Number(e.registered_count ?? e.registeredCount ?? 0) || 0;
}

function getMaxPlayers(e) {
    return Number(e.max_players ?? e.maxPlayers ?? 0) || 0;
}

function isRegistered(e) {
    return Boolean(e.is_registered ?? e.isRegistered ?? e.registered ?? false);
}

function isStarted(e) {
    return Boolean(e.started_at ?? e.startedAt);
}

function isFinished(e) {
    return Boolean(e.finished_at ?? e.finishedAt);
}

function formatDate(v) {
    if (!v) return "—";
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return "—";
    return d.toLocaleString("fr-FR");
}

function computeStatusLabel() {
    if (!event) return "—";
    if (isFinished(event)) return "Terminé";
    if (isStarted(event)) return "En cours";
    if (isRegistered(event)) return "Inscrit";

    const max = getMaxPlayers(event);
    const count = getRegisteredCount(event);
    const full = max > 0 && count >= max;

    return full ? "Complet" : "Ouvert";
}

function refreshButtons() {
    if (!btnRegister || !btnUnregister || !slotsEl || !statusEl || !event) return;

    if (!currentUser) {
        btnRegister.classList.add("d-none");
        btnUnregister.classList.add("d-none");
        slotsEl.textContent = getMaxPlayers(event) > 0
            ? `${getRegisteredCount(event)} / ${getMaxPlayers(event)}`
            : `${getRegisteredCount(event)}`;
        statusEl.textContent = computeStatusLabel();
        return;
    }

    const reg = isRegistered(event);
    const max = getMaxPlayers(event);
    const count = getRegisteredCount(event);
    const full = max > 0 && count >= max;
    const locked = full || isStarted(event) || isFinished(event);

    btnRegister.classList.toggle("d-none", reg);
    btnUnregister.classList.toggle("d-none", !reg);

    btnRegister.disabled = locked;
    slotsEl.textContent = max > 0 ? `${count} / ${max}` : `${count}`;
    statusEl.textContent = computeStatusLabel();
}

function render() {
    if (!event) return;

    hideState();

    if (titleEl) titleEl.textContent = safeText(event.title ?? event.name ?? "Événement", "Événement");
    if (descEl) descEl.textContent = safeText(event.description, "—");
    if (dateEl) dateEl.textContent = formatDate(getStartDate(event));
    if (gameEl) gameEl.textContent = safeText(event.game ?? event.game_name ?? event.category, "E-sport");

    const org = event.organizerName ?? event.organizer_name ?? event.organizer?.name ?? "";
    if (metaEl) metaEl.textContent = org ? `Organisateur : ${org}` : "";

    refreshButtons();
}

async function loadCurrentUser() {
    try {
        const me = await api("/me");
        currentUser = me?.user ?? me;
    } catch {
        currentUser = null;
    }
}

async function loadEvent() {
    let data = await api(`/events/${eventId}`);
    data = data?.event ?? data;

    event = {
        ...data,
        registered_count: Number(data?.registered_count ?? data?.registeredCount ?? 0) || 0,
        max_players: Number(data?.max_players ?? data?.maxPlayers ?? 0) || 0,
        is_registered: Boolean(data?.is_registered ?? data?.isRegistered ?? false),
    };
}

btnRegister?.addEventListener("click", async () => {
    try {
        btnRegister.disabled = true;
        await fetchCsrf();
        await api(`/events/${eventId}/register`, { method: "DELETE", csrf: true });

        toast("Inscription réussie", "success");

        event.is_registered = true;
        event.registered_count = getRegisteredCount(event) + 1;

        render();
    } catch (err) {
        btnRegister.disabled = false;
        toast(err?.data?.error || err?.message || "Erreur", "danger");
    }
});

btnUnregister?.addEventListener("click", async () => {
    try {
        btnUnregister.disabled = true;
        await fetchCsrf();
        await api(`/events/${eventId}/unregister`, { method: "POST", csrf: true });

        toast("Désinscription réussie", "success");

        event.is_registered = false;
        event.registered_count = Math.max(0, getRegisteredCount(event) - 1);

        render();
    } catch (err) {
        btnUnregister.disabled = false;
        toast(err?.data?.error || err?.message || "Erreur", "danger");
    }
});

async function init() {
    if (!eventId) {
        showState("ID événement manquant dans l’URL.", "danger");
        return;
    }

    try {
        await loadCurrentUser();
        await loadEvent();
        render();
    } catch (err) {
        console.error(err);
        showState(err?.data?.error || err?.message || "Impossible de charger l’événement.", "danger");
    }
}

init();