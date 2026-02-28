import { api, fetchCsrf } from "../js/api.js";
import { toast } from "../js/ui.js";

const tbody = document.querySelector("#eventsTbody");
const form = document.querySelector("#filtersForm");
const qEl = document.querySelector("#q");
const statusEl = document.querySelector("#status");
const sortEl = document.querySelector("#sort");
const refreshBtn = document.querySelector("#refreshBtn");
const logoutBtn = document.querySelector("#logoutBtn");

function fmt(dt) {
    if (!dt) return "-";
    const d = new Date(String(dt).replace(" ", "T"));
    return isNaN(d) ? dt : d.toLocaleString("fr-FR");
}

function badge(status) {
    switch (status) {
        case "VALIDATED": return "success";
        case "PENDING": return "warning";
        case "REJECTED": return "danger";
        case "SUSPENDED": return "secondary";
        default: return "dark";
    }
}

function escapeHtml(s) {
    return String(s ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function canStart(e) {
    if (e.status !== "VALIDATED") return false;
    if (e.started_at) return false;
    if (e.finished_at) return false;
    if (!e.start_at) return false;

    const start = new Date(String(e.start_at).replace(" ", "T"));
    const now = new Date();
    return (start - now) <= 30 * 60 * 1000;
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
    tbody.innerHTML = `
    <tr>
        <td colspan="6" class="text-muted py-4">
            <div class="d-flex align-items-center justify-content-center gap-2">
            <span class="badge bg-secondary">Aucun événement</span>
            <span>Crée un événement puis attends la validation admin.</span>
            </div>
        </td>
    </tr>`;

    if (!events.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-muted">Aucun événement.</td></tr>`;
        return;
    }

    for (const e of events) {
        const startBtn = canStart(e)
            ? `<button class="btn btn-sm btn-success js-start" data-id="${e.id}">Démarrer</button>`
            : `<button class="btn btn-sm btn-outline-secondary" disabled>Démarrer</button>`;

        const tr = document.createElement("tr");
        tr.innerHTML = `
      <td class="fw-semibold">${escapeHtml(e.title)}</td>
      <td><span class="badge bg-${badge(e.status)}">${escapeHtml(e.status)}</span></td>
      <td>${Number(e.registered_count ?? 0)}/${Number(e.max_players ?? 0) || "-"}</td>
      <td>${fmt(e.start_at)}</td>
      <td>${fmt(e.end_at)}</td>
      <td class="text-end">
        <button
          class="btn btn-sm btn-primary js-regs"
          data-id="${e.id}"
          data-title="${escapeHtml(e.title)}"
          data-start="${escapeHtml(e.start_at || '')}"
          data-end="${escapeHtml(e.end_at || '')}"
          data-status="${escapeHtml(e.status || '')}">
          Inscriptions
        </button>
        ${startBtn}
      </td>
    `;
        tbody.appendChild(tr);
    }
}

async function openRegs(eventId, meta) {
    const res = await api(`/events/${eventId}/registrations`);
    const regs = res?.registrations || [];

    document.querySelector("#modalTitle").textContent = `Inscriptions — ${meta.title}`;
    document.querySelector("#modalMeta").textContent =
        `Début: ${meta.start || '-'} — Fin: ${meta.end || '-'} — Statut: ${meta.status || '-'}`;

    const list = document.querySelector("#regsList");
    list.innerHTML = "";

    if (!regs.length) {
        list.innerHTML = `<li class="list-group-item text-muted">Aucune inscription.</li>`;
    } else {
        for (const r of regs) {
            const refused = r.status === "REFUSED";
            list.insertAdjacentHTML("beforeend", `
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-semibold">${escapeHtml(r.pseudo)}</div>
            <div class="text-muted small">${escapeHtml(r.email)} — ${escapeHtml(r.status)}</div>
          </div>
          <button class="btn btn-sm btn-outline-danger js-refuse"
                  data-event="${eventId}" data-user="${r.user_id}" ${refused ? "disabled" : ""}>
            Refuser
          </button>
        </li>
      `);
        }
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
    await openRegs(eventId, { title: "Inscriptions", start: "", end: "", status: "" });
}

async function startEvent(eventId) {
    await fetchCsrf();
    await api(`/events/${eventId}/start`, {
        method: "POST",
        csrf: true,
        body: {}
    });
    toast("Événement démarré ✅", "success");
    await loadEvents();
}

form?.addEventListener("submit", (e) => {
    e.preventDefault();
    loadEvents().catch(err => toast(err.message || "Erreur", "danger"));
});

refreshBtn?.addEventListener("click", () => {
    loadEvents().catch(err => toast(err.message || "Erreur", "danger"));
});

document.addEventListener("click", (e) => {
    const regsBtn = e.target.closest(".js-regs");
    if (regsBtn) {
        const id = regsBtn.dataset.id;
        openRegs(id, {
            title: regsBtn.dataset.title,
            start: regsBtn.dataset.start,
            end: regsBtn.dataset.end,
            status: regsBtn.dataset.status
        }).catch(err => toast(err.message || "Erreur", "danger"));
    }

    const refuseBtn = e.target.closest(".js-refuse");
    if (refuseBtn) {
        refusePlayer(refuseBtn.dataset.event, refuseBtn.dataset.user)
            .catch(err => toast(err.message || "Erreur", "danger"));
    }

    const startBtn = e.target.closest(".js-start");
    if (startBtn) {
        startEvent(startBtn.dataset.id)
            .catch(err => toast(err.message || "Start refusé", "danger"));
    }
});

logoutBtn?.addEventListener("click", async () => {
    if (!e.target.closest("#logoutBtn")) return;
    try {
        await fetchCsrf();
        await api("/logout", { method: "POST", csrf: true, body: {} });
        window.location.href = "/frontend/login.html";
    } catch {
        window.location.href = "/frontend/login.html";
    }
});

loadEvents().catch(() => { });