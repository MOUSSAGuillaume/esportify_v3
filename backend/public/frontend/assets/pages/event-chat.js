import { api, fetchCsrf } from "../js/api.js";
import { toast } from "../js/ui.js";

const params = new URLSearchParams(window.location.search);
const eventId = params.get("eventId");

const titleEl = document.querySelector("#chatTitle");
const metaEl = document.querySelector("#chatMeta");
const messagesEl = document.querySelector("#chatMessages");
const formEl = document.querySelector("#chatForm");
const inputEl = document.querySelector("#chatInput");
const infoBannerEl = document.querySelector("#chatInfoBanner");
const accessStateEl = document.querySelector("#chatAccessState");

let canWrite = false;

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function fmtDateTime(dt) {
    if (!dt) return "";
    const d = new Date(String(dt).replace(" ", "T"));
    return Number.isNaN(d.getTime()) ? dt : d.toLocaleString("fr-FR");
}

function roleClass(role) {
    const r = String(role || "").toUpperCase();
    if (r === "ADMIN") return "role-admin";
    if (r === "ORGANIZER") return "role-organizer";
    return "role-player";
}

function showAccessDenied(message) {
    const msgEl = document.querySelector("#accessDeniedMessage");
    if (msgEl) {
        msgEl.textContent =
            message || "Le chat est réservé aux participants de l’événement, mais vous pouvez visionner le direct.";
    }

    const modalEl = document.getElementById("accessDeniedModal");
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

function setWriteState(allowed, infoMessage = null) {
    canWrite = !!allowed;

    if (inputEl) {
        inputEl.disabled = !canWrite;
        inputEl.placeholder = canWrite
            ? "Écrire un message..."
            : "Lecture seule — chat réservé aux participants";
    }

    const submitBtn = formEl?.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = !canWrite;
    }

    if (infoBannerEl) {
        if (canWrite) {
            infoBannerEl.classList.add("d-none");
            infoBannerEl.textContent = "";
        } else {
            infoBannerEl.classList.remove("d-none");
            infoBannerEl.textContent =
                infoMessage || "Le chat est réservé aux participants de l’événement, mais vous pouvez visionner le direct.";
        }
    }

    if (accessStateEl) {
        accessStateEl.textContent = canWrite ? "Vous pouvez écrire dans le chat." : "Lecture seule";
    }
}

function renderMessages(messages) {
    if (!messagesEl) return;

    messagesEl.innerHTML = "";

    if (!messages.length) {
        messagesEl.innerHTML = `<div class="chat-empty">Aucun message pour le moment.</div>`;
        return;
    }

    for (const msg of messages) {
        messagesEl.insertAdjacentHTML("beforeend", `
      <div class="chat-message">
        <div class="chat-message__meta">
          <span class="chat-message__author ${roleClass(msg.role)}">${escapeHtml(msg.pseudo)}</span>
          <span class="chat-message__role">${escapeHtml(msg.role || "PLAYER")}</span>
          <span class="chat-message__time">${escapeHtml(fmtDateTime(msg.createdAt))}</span>
        </div>
        <div class="chat-message__content">${escapeHtml(msg.message)}</div>
      </div>
    `);
    }

    messagesEl.scrollTop = messagesEl.scrollHeight;
}

async function loadChat() {
    if (!eventId) {
        toast("Événement introuvable", "danger");
        return;
    }

    try {
        const res = await api(`/events/${eventId}/chat`);

        if (titleEl) {
            titleEl.textContent = `Direct — ${res?.event?.title || "Événement"}`;
        }

        if (metaEl) {
            metaEl.textContent = `Statut : ${res?.event?.status || "-"} — Démarré le : ${fmtDateTime(res?.event?.started_at)}`;
        }

        setWriteState(res?.canWrite, res?.infoMessage || null);
        renderMessages(res?.messages || []);
    } catch (err) {
        if (err.status === 403) {
            setWriteState(false, err.message);
            showAccessDenied(err.message);
            return;
        }

        toast(err.message || "Impossible de charger le direct", "danger");
    }
}

async function sendMessage(content) {
    await fetchCsrf();

    await api(`/events/${eventId}/chat`, {
        method: "POST",
        csrf: true,
        body: { content }
    });
}

formEl?.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (!canWrite) {
        showAccessDenied("Le chat est réservé aux participants de l’événement, mais vous pouvez visionner le direct.");
        return;
    }

    const content = inputEl?.value?.trim() || "";
    if (!content) return;

    try {
        await sendMessage(content);
        if (inputEl) inputEl.value = "";
        await loadChat();
    } catch (err) {
        if (err.status === 403) {
            setWriteState(false, err.message);
            showAccessDenied(err.message);
            return;
        }

        toast(err.message || "Erreur lors de l’envoi du message", "danger");
    }
});

loadChat().catch(() => { });

setInterval(() => {
    loadChat().catch(() => { });
}, 5000);