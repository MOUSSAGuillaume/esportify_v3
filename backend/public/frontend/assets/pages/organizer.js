import { api, fetchCsrf } from "../js/api.js";
import { toast } from "../js/ui.js";

const tbody = document.querySelector("#eventsTbody");
const form = document.querySelector("#filtersForm");
const qEl = document.querySelector("#q");
const statusEl = document.querySelector("#status");
const sortEl = document.querySelector("#sort");
const refreshBtn = document.querySelector("#refreshBtn");

function fmt(dt) {
    if (!dt) return "-";

    const parsed = new Date(String(dt).replace(" ", "T"));
    return Number.isNaN(parsed.getTime()) ? dt : parsed.toLocaleString("fr-FR");
}

function badge(status) {
    switch (status) {
        case "VALIDATED":
            return "success";
        case "PENDING":
            return "warning";
        case "REJECTED":
            return "danger";
        case "SUSPENDED":
            return "secondary";
        default:
            return "dark";
    }
}

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function canStart(event) {
    if (event.status !== "VALIDATED") return false;
    if (event.started_at) return false;
    if (event.finished_at) return false;
    if (!event.start_at) return false;

    const start = new Date(String(event.start_at).replace(" ", "T"));
    if (Number.isNaN(start.getTime())) return false;

    const now = new Date();
    const diffMs = start.getTime() - now.getTime();

    // Affichage front : bouton actif à partir de 30 min avant
    return diffMs <= 30 * 60 * 1000;
}

function getStartButtonHtml(event) {
    if (event.started_at) {
        return `<button class="btn btn-sm btn-info" disabled>En cours</button>`;
    }

    if (canStart(event)) {
        return `<button class="btn btn-sm btn-success js-start" data-id="${event.id}">Démarrer</button>`;
    }

    return `<button class="btn btn-sm btn-outline-secondary" disabled>Démarrer</button>`;
}

function getLiveButtonHtml(event) {
    if (!event.started_at) return "";

    return `
    <a
      class="btn btn-sm btn-outline-light"
      href="./event-chat.html?eventId=${event.id}">
      Accéder au direct
    </a>
  `;
}

function renderEmptyState() {
    tbody.innerHTML = `
    <tr>
      <td colspan="6" class="text-muted py-4">
        <div class="d-flex align-items-center justify-content-center gap-2">
          <span class="badge bg-secondary">Aucun événement</span>
          <span>Crée un événement puis attends la validation admin.</span>
        </div>
      </td>
    </tr>
  `;
}

async function loadEvents() {
    const params = new URLSearchParams({
        q: qEl?.value?.trim() || "",
        status: statusEl?.value || "",
        sort: sortEl?.value || "start_desc"
    });

    const res = await api(`/organizer/events?${params.toString()}`);
    render(res?.events || []);
}

function render(events) {
    tbody.innerHTML = "";

    if (!events.length) {
        renderEmptyState();
        return;
    }

    for (const event of events) {
        const startBtn = getStartButtonHtml(event);
        const liveBtn = getLiveButtonHtml(event);

        const tr = document.createElement("tr");
        tr.innerHTML = `
      <td class="fw-semibold">${escapeHtml(event.title)}</td>
      <td>
        <span class="badge bg-${badge(event.status)}">${escapeHtml(event.status)}</span>
      </td>
      <td>${Number(event.registered_count ?? 0)}/${Number(event.max_players ?? 0) || "-"}</td>
      <td>${fmt(event.start_at)}</td>
      <td>${fmt(event.end_at)}</td>
      <td class="text-end">
        <div class="d-inline-flex flex-wrap gap-2 justify-content-end">
          <button
            class="btn btn-sm btn-primary js-regs"
            data-id="${event.id}"
            data-title="${escapeHtml(event.title)}"
            data-start="${escapeHtml(event.start_at || "")}"
            data-end="${escapeHtml(event.end_at || "")}"
            data-status="${escapeHtml(event.status || "")}">
            Voir inscrits
          </button>
          ${startBtn}
          ${liveBtn}
        </div>
      </td>
    `;
        tbody.appendChild(tr);
    }
}

async function openRegs(eventId, meta) {
    const res = await api(`/events/${eventId}/registrations`);
    const regs = res?.registrations || [];

    const modalTitle = document.querySelector("#modalTitle");
    const modalMeta = document.querySelector("#modalMeta");
    const list = document.querySelector("#regsList");

    if (!modalTitle || !modalMeta || !list) return;

    modalTitle.textContent = `Joueurs inscrits — ${meta.title}`;
    modalMeta.textContent =
        `Début : ${meta.start || "-"} — Fin : ${meta.end || "-"} — Statut : ${meta.status || "-"}`;

    list.innerHTML = "";

    if (!regs.length) {
        list.innerHTML = `
      <li class="list-group-item text-muted">
        Aucun joueur inscrit pour cet événement.
      </li>
    `;
        return;
    }

    for (const reg of regs) {
        const refused = reg.status === "REFUSED";

        list.insertAdjacentHTML(
            "beforeend",
            `
        <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-3">
          <div>
            <div class="fw-semibold">${escapeHtml(reg.pseudo)}</div>
            <div class="text-muted small">
              ${escapeHtml(reg.email)} — ${escapeHtml(reg.status)}
            </div>
          </div>

          <button
            class="btn btn-sm btn-outline-danger js-refuse"
            data-event="${eventId}"
            data-user="${reg.user_id}"
            ${refused ? "disabled" : ""}>
            Refuser
          </button>
        </li>
      `
        );
    }

    const modal = new bootstrap.Modal(document.getElementById("regsModal"));
    modal.show();
}

async function refusePlayer(eventId, userId) {
    await fetchCsrf();

    await api(`/events/${eventId}/registrations/${userId}/refuse`, {
        method: "POST",
        csrf: true,
        body: {}
    });

    toast("Joueur refusé ✅", "success");

    const title =
        document.querySelector("#modalTitle")?.textContent?.replace("Joueurs inscrits — ", "") ||
        "Inscriptions";

    await openRegs(eventId, {
        title,
        start: "",
        end: "",
        status: ""
    });
}

async function startEvent(eventId, btn) {
    if (btn) {
        btn.disabled = true;
        btn.textContent = "Démarrage...";
    }

    try {
        await fetchCsrf();

        const res = await api(`/events/${eventId}/start`, {
            method: "POST",
            csrf: true,
            body: {}
        });

        toast(res?.message || "Événement démarré ✅", "success");
        await loadEvents();
    } catch (err) {
        console.error("START ERROR", err);
        toast(err.message || "Start refusé", "danger");

        if (btn) {
            btn.disabled = false;
            btn.textContent = "Démarrer";
        }
    }
}

form?.addEventListener("submit", (e) => {
    e.preventDefault();
    loadEvents().catch((err) => {
        toast(err.message || "Erreur", "danger");
    });
});

refreshBtn?.addEventListener("click", () => {
    loadEvents().catch((err) => {
        toast(err.message || "Erreur", "danger");
    });
});

document.addEventListener("click", (e) => {
    const regsBtn = e.target.closest(".js-regs");
    if (regsBtn) {
        openRegs(regsBtn.dataset.id, {
            title: regsBtn.dataset.title,
            start: regsBtn.dataset.start,
            end: regsBtn.dataset.end,
            status: regsBtn.dataset.status
        }).catch((err) => {
            toast(err.message || "Erreur", "danger");
        });
    }

    const refuseBtn = e.target.closest(".js-refuse");
    if (refuseBtn) {
        refusePlayer(refuseBtn.dataset.event, refuseBtn.dataset.user).catch((err) => {
            toast(err.message || "Erreur", "danger");
        });
    }

    const startBtn = e.target.closest(".js-start");
    if (startBtn) {
        startEvent(startBtn.dataset.id, startBtn);
    }
});

loadEvents().catch(() => { });