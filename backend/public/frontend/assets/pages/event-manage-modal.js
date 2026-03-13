import { api } from "../js/api.js";
import { toast } from "../js/ui.js";

const manageEventModalEl = document.getElementById("manageEventModal");
const manageEventId = document.getElementById("manageEventId");
const manageEventTitle = document.getElementById("manageEventTitle");
const manageEventMeta = document.getElementById("manageEventMeta");
const manageParticipantsTbody = document.getElementById("manageParticipantsTbody");
const manageParticipantsEmpty = document.getElementById("manageParticipantsEmpty");

const manageEventModal = manageEventModalEl ? new bootstrap.Modal(manageEventModalEl) : null;

let refreshCallback = null;

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function normalizeStatus(status) {
    return String(status || "").toUpperCase();
}

function statusLabel(status) {
    const s = normalizeStatus(status);
    if (s === "ACTIVE") return "Validé";
    if (s === "REFUSED") return "Refusé";
    if (s === "CANCELLED") return "Annulé";
    return s || "—";
}

function participantRow(eventId, row) {
    const status = normalizeStatus(row.status);

    return `
    <tr>
      <td>${row.user_id ?? "—"}</td>
      <td>${escapeHtml(row.pseudo || "—")}</td>
      <td>${escapeHtml(row.email || "—")}</td>
      <td>${escapeHtml(statusLabel(status))}</td>
      <td class="text-end">
        <div class="btn-group btn-group-sm">
          ${status !== "ACTIVE"
            ? `<button class="btn btn-success btn-activate-participant"
                         type="button"
                         data-event-id="${eventId}"
                         data-user-id="${row.user_id}">
                    Valider
                 </button>`
            : ""
        }

          ${status !== "REFUSED"
            ? `<button class="btn btn-warning btn-refuse-participant"
                         type="button"
                         data-event-id="${eventId}"
                         data-user-id="${row.user_id}">
                    Suspendre
                 </button>`
            : ""
        }
        </div>
      </td>
    </tr>
  `;
}

async function loadParticipants(event) {
    const eventId = Number(event?.id ?? 0);
    if (!eventId) return;

    manageEventId.value = String(eventId);
    manageEventTitle.textContent = event.title ?? "Événement";
    manageEventMeta.textContent = `Début : ${event.start_at ?? "—"} • Joueurs max : ${event.max_players ?? "—"}`;

    const data = await api(`/events/${eventId}/registrations`);
    const registrations = Array.isArray(data?.registrations) ? data.registrations : [];

    manageParticipantsTbody.innerHTML = registrations.map(row => participantRow(eventId, row)).join("");
    manageParticipantsEmpty.classList.toggle("d-none", registrations.length !== 0);

    bindParticipantActions(eventId);
}

function bindParticipantActions(eventId) {
    manageParticipantsTbody.querySelectorAll(".btn-refuse-participant").forEach((btn) => {
        btn.addEventListener("click", async () => {
            try {
                const userId = Number(btn.dataset.userId);
                if (!userId) return;

                await api(`/organizer/events/${eventId}/registrations/${userId}/refuse`, {
                    method: "POST",
                    csrf: true,
                });

                toast("Participant suspendu", "success");

                const event = {
                    id: eventId,
                    title: manageEventTitle.textContent,
                    start_at: "",
                    max_players: ""
                };
                await loadParticipants(event);

                if (refreshCallback) {
                    await refreshCallback();
                }
            } catch (err) {
                toast(err?.message || "Erreur lors de la mise à jour du participant", "danger");
            }
        });
    });

    manageParticipantsTbody.querySelectorAll(".btn-activate-participant").forEach((btn) => {
        btn.addEventListener("click", async () => {
            toast("Réactivation à brancher côté backend", "warning");
        });
    });
}

export function initEventManageModal({ onChanged } = {}) {
    refreshCallback = typeof onChanged === "function" ? onChanged : null;
}

export async function openEventManageModal(event) {
    if (!manageEventModal || !event) return;

    try {
        await loadParticipants(event);
        manageEventModal.show();
    } catch (err) {
        toast(err?.message || "Erreur lors du chargement des participants", "danger");
    }
}