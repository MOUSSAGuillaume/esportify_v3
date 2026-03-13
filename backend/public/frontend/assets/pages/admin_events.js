import { api } from "../js/api.js";
import { toast } from "../js/ui.js";
import { initEditEventModal, openEditEventModal } from "./event-edit-modal.js";
import { initEventManageModal, openEventManageModal } from "./event-manage-modal.js";

const statTotal = document.getElementById("statTotal");
const statPending = document.getElementById("statPending");
const statActive = document.getElementById("statActive");
const statFinished = document.getElementById("statHistory");

const countActive = document.getElementById("countActive");
const countPending = document.getElementById("countPending");
const countHistory = document.getElementById("countHistory");

const activeList = document.getElementById("activeList");
const pendingList = document.getElementById("pendingList");
const historyList = document.getElementById("historyList");

const activeEmpty = document.getElementById("activeEmpty");
const pendingEmpty = document.getElementById("pendingEmpty");
const historyEmpty = document.getElementById("historyEmpty");

let allEvents = [];

function formatDate(dateValue) {
    if (!dateValue) return "Date inconnue";
    const d = new Date(dateValue);
    if (Number.isNaN(d.getTime())) return "Date invalide";
    return d.toLocaleString("fr-FR");
}

function normalizeStatus(status) {
    return String(status || "").toUpperCase();
}

function getEventTitle(event) {
    return event.title ?? event.name ?? "Événement";
}

function eventCard(event, mode = "default") {
    const status = normalizeStatus(event.status);
    const title = getEventTitle(event);
    const start = formatDate(event.start_at);
    const maxPlayers = event.max_players ?? "—";

    let badgeClass = "validated";
    let badgeLabel = status;

    if (status === "PENDING") {
        badgeClass = "pending";
        badgeLabel = "EN ATTENTE";
    } else if (status === "VALIDATED") {
        badgeClass = "validated";
        badgeLabel = "ACTIF";
    } else if (status === "FINISHED") {
        badgeClass = "finished";
        badgeLabel = "TERMINÉ";
    } else if (status === "REJECTED") {
        badgeClass = "finished";
        badgeLabel = "REFUSÉ";
    } else if (status === "SUSPENDED") {
        badgeClass = "finished";
        badgeLabel = "SUSPENDU";
    }

    return `
    <div class="admin-event-card">
      <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
        <div>
          <div class="admin-event-title">${title}</div>
          <div class="admin-event-meta">
            Début : ${start}<br>
            Joueurs max : ${maxPlayers}
          </div>
        </div>
        <span class="badge-status ${badgeClass}">${badgeLabel}</span>
      </div>

      <div class="admin-event-actions">
        <button class="btn btn-outline-light btn-sm btn-view-event" type="button" data-id="${event.id}">
          Voir
        </button>

        ${mode === "active" ? `
          <button class="btn btn-primary btn-sm btn-manage-event" type="button" data-id="${event.id}">
            Gérer
          </button>
        ` : ""}

        ${mode === "pending" || mode === "active" ? `
          <button class="btn btn-outline-warning btn-sm btn-edit-event" type="button" data-id="${event.id}">
            Modifier
          </button>
        ` : ""}
      </div>
    </div>
  `;
}

function renderSection(listEl, emptyEl, items, mode) {
    if (!listEl || !emptyEl) return;

    if (!items.length) {
        listEl.innerHTML = "";
        emptyEl.classList.remove("d-none");
        return;
    }

    emptyEl.classList.add("d-none");
    listEl.innerHTML = items.map(item => eventCard(item, mode)).join("");

    listEl.querySelectorAll(".btn-view-event").forEach((btn) => {
        btn.addEventListener("click", () => {
            const id = btn.dataset.id;
            if (!id) return;
            window.location.href = `/event?id=${encodeURIComponent(id)}`;
        });
    });

    listEl.querySelectorAll(".btn-manage-event").forEach((btn) => {
        btn.addEventListener("click", () => {
            const id = Number(btn.dataset.id);
            const event = items.find(e => Number(e.id) === id);
            if (!event) return;
            openEventManageModal(event);
        });
    });

    listEl.querySelectorAll(".btn-edit-event").forEach((btn) => {
        btn.addEventListener("click", () => {
            const id = Number(btn.dataset.id);
            const event = items.find(e => Number(e.id) === id);
            if (!event) return;
            openEditEventModal(event);
        });
    });
}

function renderStats() {
    const pending = allEvents.filter(e => normalizeStatus(e.status) === "PENDING");
    const active = allEvents.filter(e => normalizeStatus(e.status) === "VALIDATED");
    const history = allEvents.filter(e =>
        ["FINISHED", "REJECTED", "SUSPENDED"].includes(normalizeStatus(e.status))
    );

    if (statTotal) statTotal.textContent = allEvents.length;
    if (statPending) statPending.textContent = pending.length;
    if (statActive) statActive.textContent = active.length;
    if (statFinished) statFinished.textContent = history.length;

    if (countActive) countActive.textContent = active.length;
    if (countPending) countPending.textContent = pending.length;
    if (countHistory) countHistory.textContent = history.length;

    renderSection(activeList, activeEmpty, active, "active");
    renderSection(pendingList, pendingEmpty, pending, "pending");
    renderSection(historyList, historyEmpty, history, "history");
}

async function loadAdminEvents() {
    const [pendingRes, validatedRes] = await Promise.all([
        api("/admin/events?status=PENDING"),
        api("/admin/events?status=VALIDATED"),
    ]);

    const pending = pendingRes?.events ?? [];
    const validated = validatedRes?.events ?? [];

    allEvents = [...validated, ...pending];
}

async function refreshPage() {
    await loadAdminEvents();
    renderStats();
}

async function init() {
    try {
        const me = await api("/me");
        const user = me?.user ?? me;
        const role = String(user?.role || "").toUpperCase();

        if (!user || !["ADMIN", "ORGANIZER"].includes(role)) {
            window.location.href = "/";
            return;
        }

        initEditEventModal({
            onSaved: refreshPage,
        });

        initEventManageModal({
            onChanged: refreshPage,
        });

        await refreshPage();
    } catch (err) {
        toast(err?.message || "Erreur lors du chargement", "danger");
    }
}

init();