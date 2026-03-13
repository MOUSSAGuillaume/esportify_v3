import { api } from "../js/api.js";
import { toast } from "../js/ui.js";

const editEventModalEl = document.getElementById("editEventModal");
const editEventId = document.getElementById("editEventId");
const editTitle = document.getElementById("editTitle");
const editDescription = document.getElementById("editDescription");
const editStartAt = document.getElementById("editStartAt");
const editEndAt = document.getElementById("editEndAt");
const editMaxPlayers = document.getElementById("editMaxPlayers");
const btnSaveEditEvent = document.getElementById("btnSaveEditEvent");
const btnDeleteEvent = document.getElementById("btnDeleteEvent");

const editEventModal = editEventModalEl ? new bootstrap.Modal(editEventModalEl) : null;

let onSavedCallback = null;

function toDatetimeLocalValue(dateValue) {
    if (!dateValue) return "";

    const d = new Date(dateValue);
    if (Number.isNaN(d.getTime())) return "";

    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, "0");
    const dd = String(d.getDate()).padStart(2, "0");
    const hh = String(d.getHours()).padStart(2, "0");
    const mi = String(d.getMinutes()).padStart(2, "0");

    return `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
}

function getPayload() {
    return {
        title: editTitle?.value.trim() ?? "",
        description: editDescription?.value.trim() ?? "",
        start_at: editStartAt?.value ?? "",
        end_at: editEndAt?.value ?? "",
        max_players: Number(editMaxPlayers?.value ?? 0),
    };
}

function validatePayload(payload) {
    if (!payload.title) return "Le titre est obligatoire.";
    if (!payload.description) return "La description est obligatoire.";
    if (!payload.start_at) return "La date de début est obligatoire.";
    if (!payload.end_at) return "La date de fin est obligatoire.";
    if (payload.max_players <= 0) return "Le nombre de joueurs doit être supérieur à 0.";

    const start = new Date(payload.start_at);
    const end = new Date(payload.end_at);

    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        return "Dates invalides.";
    }

    if (end <= start) {
        return "La date de fin doit être après la date de début.";
    }

    return null;
}

export function openEditEventModal(event) {
    if (!editEventModal || !event) return;

    if (editEventId) editEventId.value = String(event.id ?? "");
    if (editTitle) editTitle.value = event.title ?? "";
    if (editDescription) editDescription.value = event.description ?? "";
    if (editStartAt) editStartAt.value = toDatetimeLocalValue(event.start_at);
    if (editEndAt) editEndAt.value = toDatetimeLocalValue(event.end_at);
    if (editMaxPlayers) editMaxPlayers.value = String(event.max_players ?? 1);

    editEventModal.show();
}

export function initEditEventModal({ onSaved } = {}) {
    onSavedCallback = typeof onSaved === "function" ? onSaved : null;

    btnSaveEditEvent?.addEventListener("click", async () => {
        try {
            const id = Number(editEventId?.value ?? 0);
            if (!id) {
                toast("Événement introuvable", "danger");
                return;
            }

            const payload = getPayload();
            const error = validatePayload(payload);

            if (error) {
                toast(error, "danger");
                return;
            }

            await api(`/admin/events/${id}`, {
                method: "PUT",
                body: payload,
                csrf: true,
            });

            editEventModal?.hide();
            toast("Événement mis à jour", "success");

            if (onSavedCallback) {
                await onSavedCallback();
            }
        } catch (err) {
            toast(err?.message || "Erreur lors de la mise à jour", "danger");
        }
    });

    btnDeleteEvent?.addEventListener("click", async () => {
        try {
            const id = Number(editEventId?.value ?? 0);
            if (!id) {
                toast("Événement introuvable", "danger");
                return;
            }

            const confirmed = window.confirm("Voulez-vous vraiment supprimer cet événement ?");
            if (!confirmed) return;

            await api(`/admin/events/${id}`, {
                method: "DELETE",
                csrf: true,
            });

            editEventModal?.hide();
            toast("Événement supprimé", "success");

            if (onSavedCallback) {
                await onSavedCallback();
            }
        } catch (err) {
            toast(err?.message || "Erreur lors de la suppression", "danger");
        }
    });
}