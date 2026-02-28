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
const cityEl = document.getElementById("city");
const slotsEl = document.getElementById("slots");
const statusEl = document.getElementById("status");

const btnRegister = document.getElementById("btnRegister");
const btnUnregister = document.getElementById("btnUnregister");

let currentUser = null;
let event = null;

function showState(msg, type = "info") {
    stateEl.className = `alert alert-${type}`;
    stateEl.textContent = msg;
    stateEl.classList.remove("d-none");
}
function hideState() { stateEl.classList.add("d-none"); }

function safeText(v, fallback = "—") {
    if (v === null || v === undefined) return fallback;
    const s = String(v).trim();
    return s.length ? s : fallback;
}
function getStartDate(e) { return e.start_at ?? e.startAt ?? null; }
function getRegisteredCount(e) { return Number(e.registeredCount ?? e.registered_count ?? 0) || 0; }
function getMaxPlayers(e) { return Number(e.maxPlayers ?? e.max_players ?? 0) || 0; }
function isRegistered(e) {
    return Boolean(e.is_registered ?? e.isRegistered ?? e.registered ?? false);
}
function formatDate(v) {
    if (!v) return "—";
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return "—";
    return d.toLocaleString();
}

function refreshButtons() {
    if (!currentUser) {
        btnRegister.classList.add("d-none");
        btnUnregister.classList.add("d-none");
        return;
    }

    const reg = isRegistered(event);
    btnRegister.classList.toggle("d-none", reg);
    btnUnregister.classList.toggle("d-none", !reg);

    const max = getMaxPlayers(event);
    const count = getRegisteredCount(event);
    const full = max > 0 && count >= max;

    btnRegister.disabled = full;
    statusEl.textContent = reg ? "Inscrit" : (full ? "Complet" : "Ouvert");
    slotsEl.textContent = max > 0 ? `${count} / ${max}` : `${count}`;
}

function render() {
    hideState();
    titleEl.textContent = safeText(event.title ?? event.name ?? "Événement", "Événement");
    descEl.textContent = safeText(event.description, "—");
    dateEl.textContent = formatDate(getStartDate(event));
    gameEl.textContent = safeText(event.game ?? event.game_name ?? event.category, "E-sport");
    cityEl.textContent = safeText(event.city, "—");

    const org = event.organizerName ?? event.organizer_name ?? event.organizer?.name ?? "";
    metaEl.textContent = org ? `Organisateur : ${org}` : "";

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
    // adapte si ton endpoint détail est différent
    event = await api(`/events/${eventId}`);
    event = event?.event ?? event; // tolérant
}

btnRegister.addEventListener("click", async () => {
    try {
        btnRegister.disabled = true;
        await fetchCsrf();
        await api(`/events/${eventId}/register`, { method: "POST", csrf: true });
        toast("Inscription réussie", "success");
        event.is_registered = true;
        event.isRegistered = true;
        event.registeredCount = getRegisteredCount(event) + 1;
        render();
    } catch (err) {
        btnRegister.disabled = false;
        toast(err?.message || "Erreur", "danger");
    }
});

btnUnregister.addEventListener("click", async () => {
    try {
        btnUnregister.disabled = true;
        await fetchCsrf();
        await api(`/events/${eventId}/register`, { method: "DELETE", csrf: true });
        toast("Désinscription réussie", "success");
        event.is_registered = false;
        event.isRegistered = false;
        event.registeredCount = Math.max(0, getRegisteredCount(event) - 1);
        btnUnregister.disabled = false;
        render();
    } catch (err) {
        btnUnregister.disabled = false;
        toast(err?.message || "Erreur", "danger");
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
        showState("Impossible de charger l’événement.", "danger");
    }
}

init();