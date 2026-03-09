import { api, fetchCsrf } from "../js/api.js";
import { toast } from "../js/ui.js";

const root = document.getElementById("profileRoot");

let state = {
  user: null,
  registrations: [],
  events: [],
  editMode: false,
  form: { pseudo: "", bio: "" },
};

function escapeHtml(str = "") {
  return String(str)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function formatDate(dt) {
  if (!dt) return "";
  const d = new Date(dt);
  if (Number.isNaN(d.getTime())) return "";
  return d.toLocaleString("fr-FR", { dateStyle: "medium", timeStyle: "short" });
}

function getRole() {
  return String(state.user?.role || "").toUpperCase();
}

function isPlayer() {
  return getRole() === "PLAYER";
}

function isOrganizer() {
  return getRole() === "ORGANIZER";
}

function isAdmin() {
  return getRole() === "ADMIN";
}

function canCreateEvent() {
  return isOrganizer() || isAdmin();
}

function roleLabel() {
  if (isAdmin()) return "Admin";
  if (isOrganizer()) return "Organisateur";
  return "Joueur";
}

function findEvent(eventId) {
  return state.events.find((e) => Number(e.id) === Number(eventId));
}

function isStarted(event) {
  return Boolean(event?.started_at ?? event?.startedAt);
}

function isFinished(event) {
  return Boolean(event?.finished_at ?? event?.finishedAt);
}

function isUpcoming(event) {
  const start = event?.start_at ?? event?.startAt;
  if (!start) return false;
  const d = new Date(start);
  return !Number.isNaN(d.getTime()) && d.getTime() > Date.now() && !isFinished(event);
}

function isActiveRegistration(registration) {
  return String(registration?.status || "").toUpperCase() === "ACTIVE";
}

function registrationEventPair(registration) {
  const event = findEvent(registration.event_id);
  if (!event) return null;
  return { event, registration };
}

function computeDerived() {
  const regs = Array.isArray(state.registrations) ? state.registrations : [];
  const events = Array.isArray(state.events) ? state.events : [];

  const activeRegistrations = regs
    .filter(isActiveRegistration)
    .map(registrationEventPair)
    .filter(Boolean);

  const playerCurrentEvents = activeRegistrations.filter(({ event }) => isStarted(event) && !isFinished(event));
  const playerUpcomingEvents = activeRegistrations.filter(({ event }) => isUpcoming(event));
  const playerPlayedEvents = activeRegistrations.filter(({ event }) => isFinished(event));

  const organizerOwnedEvents = events.filter((e) => Number(e.organizer_id) === Number(state.user?.id));
  const organizerCurrentEvents = organizerOwnedEvents.filter((e) => isStarted(e) && !isFinished(e));
  const organizerUpcomingEvents = organizerOwnedEvents.filter((e) => isUpcoming(e));
  const organizerFinishedEvents = organizerOwnedEvents.filter((e) => isFinished(e));

  return {
    activeRegistrations,
    playerCurrentEvents,
    playerUpcomingEvents,
    playerPlayedEvents,
    organizerOwnedEvents,
    organizerCurrentEvents,
    organizerUpcomingEvents,
    organizerFinishedEvents,
  };
}

function profileStats(derived) {
  if (isPlayer()) {
    return [
      { value: derived.activeRegistrations.length, label: "Inscriptions" },
      { value: derived.playerCurrentEvents.length, label: "En cours" },
      { value: derived.playerPlayedEvents.length, label: "Parties jouées" },
    ];
  }

  return [
    { value: derived.organizerOwnedEvents.length, label: "Événements" },
    { value: derived.organizerCurrentEvents.length, label: "En cours" },
    { value: derived.organizerFinishedEvents.length, label: "Terminés" },
  ];
}

function render() {
  const derived = computeDerived();
  const stats = profileStats(derived);

  root.innerHTML = `
    <div class="row g-4">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex flex-column flex-md-row gap-4 align-items-start align-items-md-center">
              <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center"
                   style="width:84px;height:84px;font-size:32px;">
                ${escapeHtml((state.form.pseudo || state.user?.pseudo || "?")[0]?.toUpperCase() || "?")}
              </div>

              <div class="flex-grow-1">
                ${state.editMode ? renderEditForm() : renderViewMode()}
              </div>

              <div class="d-flex gap-4 flex-wrap">
                ${stats.map((item) => `
                  <div class="text-center">
                    <div class="h3 mb-0">${item.value}</div>
                    <div class="text-muted small">${escapeHtml(item.label)}</div>
                  </div>
                `).join("")}
              </div>
            </div>

            <div class="mt-4 d-flex flex-wrap gap-2">
              ${canCreateEvent() ? `<a class="btn btn-primary" href="/create_event">Créer un événement</a>` : ""}
              <a class="btn btn-outline-secondary" href="/events">Voir les événements</a>
              ${isPlayer() ? `<a class="btn btn-outline-secondary" href="/my-events">Mes inscriptions</a>` : ""}
            </div>
          </div>
        </div>
      </div>

      <div class="col-12">
        ${renderTabs(derived)}
      </div>
    </div>
  `;

  bindEvents();
}

function renderViewMode() {
  return `
    <div class="d-flex align-items-center gap-2 mb-2">
      <h1 class="h4 mb-0">${escapeHtml(state.user?.pseudo || state.user?.email || "Profil")}</h1>
      <span class="badge text-bg-primary">${escapeHtml(roleLabel())}</span>
    </div>
    <div class="text-muted">${escapeHtml(state.user?.email || "")}</div>
    ${state.user?.bio
      ? `<p class="mt-2 mb-0">${escapeHtml(state.user.bio)}</p>`
      : `<p class="mt-2 mb-0 text-muted">Aucune bio.</p>`
    }
    <button id="btnEdit" class="btn btn-sm btn-outline-dark mt-3">Modifier le profil</button>
  `;
}

function renderEditForm() {
  return `
    <div class="row g-2">
      <div class="col-12 col-md-6">
        <label class="form-label">Pseudo</label>
        <input id="pseudoInput" class="form-control" value="${escapeHtml(state.form.pseudo)}" maxlength="30" />
        <div class="form-text">3 à 30 caractères</div>
      </div>
      <div class="col-12">
        <label class="form-label">Bio</label>
        <textarea id="bioInput" class="form-control" rows="3" maxlength="500">${escapeHtml(state.form.bio || "")}</textarea>
        <div class="form-text">Max 500 caractères</div>
      </div>
      <div class="col-12 d-flex gap-2 mt-2">
        <button id="btnSave" class="btn btn-primary">Sauvegarder</button>
        <button id="btnCancel" class="btn btn-outline-secondary">Annuler</button>
      </div>
    </div>
  `;
}

function renderTabs(derived) {
  if (isPlayer()) {
    return renderPlayerTabs(derived);
  }

  return renderOrganizerAdminTabs(derived);
}

function renderPlayerTabs(derived) {
  return `
    <ul class="nav nav-tabs" id="profileTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-current" data-bs-toggle="tab" data-bs-target="#pane-current" type="button" role="tab">
          Parties en cours
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-reg" data-bs-toggle="tab" data-bs-target="#pane-reg" type="button" role="tab">
          Mes inscriptions
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-played" data-bs-toggle="tab" data-bs-target="#pane-played" type="button" role="tab">
          Parties jouées
        </button>
      </li>
    </ul>

    <div class="tab-content border border-top-0 p-3 bg-white" id="profileTabsContent">
      <div class="tab-pane fade show active" id="pane-current" role="tabpanel">
        ${renderCurrentGames(derived.playerCurrentEvents)}
      </div>
      <div class="tab-pane fade" id="pane-reg" role="tabpanel">
        ${renderRegistrations(derived.playerUpcomingEvents)}
      </div>
      <div class="tab-pane fade" id="pane-played" role="tabpanel">
        ${renderPlayedGames(derived.playerPlayedEvents)}
      </div>
    </div>
  `;
}

function renderOrganizerAdminTabs(derived) {
  return `
    <ul class="nav nav-tabs" id="profileTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-my-events" data-bs-toggle="tab" data-bs-target="#pane-my-events" type="button" role="tab">
          Mes événements
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-current-events" data-bs-toggle="tab" data-bs-target="#pane-current-events" type="button" role="tab">
          En cours
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-finished-events" data-bs-toggle="tab" data-bs-target="#pane-finished-events" type="button" role="tab">
          Terminés
        </button>
      </li>
    </ul>

    <div class="tab-content border border-top-0 p-3 bg-white" id="profileTabsContent">
      <div class="tab-pane fade show active" id="pane-my-events" role="tabpanel">
        ${renderManagedEvents(derived.organizerOwnedEvents)}
      </div>
      <div class="tab-pane fade" id="pane-current-events" role="tabpanel">
        ${renderManagedEvents(derived.organizerCurrentEvents)}
      </div>
      <div class="tab-pane fade" id="pane-finished-events" role="tabpanel">
        ${renderManagedEvents(derived.organizerFinishedEvents)}
      </div>
    </div>
  `;
}

function renderCurrentGames(list) {
  if (!list.length) {
    return `<div class="text-center text-muted py-5">Aucune partie en cours.</div>`;
  }

  return `
    <div class="vstack gap-3">
      ${list.map(({ event }) => `
        <div class="card shadow-sm">
          <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
            <div>
              <div class="fw-bold">${escapeHtml(event.title)}</div>
              <div class="text-muted small">${escapeHtml(formatDate(event.start_at))}</div>
              <div class="mt-2">
                <span class="badge text-bg-warning">En cours</span>
              </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <a class="btn btn-sm btn-primary" href="/event?id=${encodeURIComponent(event.id)}">Voir</a>
              <a class="btn btn-sm btn-outline-secondary" href="/event-chat?id=${encodeURIComponent(event.id)}">Accéder au chat</a>
            </div>
          </div>
        </div>
      `).join("")}
    </div>
  `;
}

function renderRegistrations(list) {
  if (!list.length) {
    return `<div class="text-center text-muted py-5">Aucune inscription à venir.</div>`;
  }

  return `
    <div class="vstack gap-3">
      ${list.map(({ event, registration }) => `
        <div class="card shadow-sm">
          <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3">
            <div>
              <div class="fw-bold">${escapeHtml(event.title)}</div>
              <div class="text-muted small">${escapeHtml(formatDate(event.start_at))}</div>
              <div class="mt-2 d-flex flex-wrap gap-2">
                <span class="badge text-bg-light border">Inscription : ${escapeHtml(registration.status)}</span>
                <span class="badge text-bg-light border">Événement : ${escapeHtml(event.status)}</span>
              </div>
            </div>
            <div class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-secondary" href="/event?id=${encodeURIComponent(event.id)}">Détails</a>
            </div>
          </div>
        </div>
      `).join("")}
    </div>
  `;
}

function renderPlayedGames(list) {
  if (!list.length) {
    return `<div class="text-center text-muted py-5">Aucune partie jouée pour le moment.</div>`;
  }

  return `
    <div class="vstack gap-3">
      ${list.map(({ event }) => `
        <div class="card shadow-sm">
          <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3">
            <div>
              <div class="fw-bold">${escapeHtml(event.title)}</div>
              <div class="text-muted small">${escapeHtml(formatDate(event.start_at))}</div>
              <div class="mt-2 d-flex flex-wrap gap-2">
                <span class="badge text-bg-secondary">Terminé</span>
                <span class="badge text-bg-light border">Résultat à afficher</span>
              </div>
            </div>
            <div class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-secondary" href="/event?id=${encodeURIComponent(event.id)}">Détails</a>
            </div>
          </div>
        </div>
      `).join("")}
    </div>
  `;
}

function renderManagedEvents(list) {
  if (!list.length) {
    return `<div class="text-center text-muted py-5">Aucun événement à afficher.</div>`;
  }

  return `
    <div class="vstack gap-3">
      ${list.map((event) => `
        <div class="card shadow-sm">
          <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3">
            <div>
              <div class="fw-bold">${escapeHtml(event.title || "Événement")}</div>
              <div class="text-muted small">${escapeHtml(formatDate(event.start_at))}</div>
              <div class="mt-2 d-flex flex-wrap gap-2">
                <span class="badge text-bg-light border">Statut : ${escapeHtml(event.status || "—")}</span>
                ${isStarted(event) && !isFinished(event) ? `<span class="badge text-bg-warning">En cours</span>` : ""}
                ${isFinished(event) ? `<span class="badge text-bg-secondary">Terminé</span>` : ""}
              </div>
            </div>
            <div class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-secondary" href="/event?id=${encodeURIComponent(event.id)}">Détails</a>
            </div>
          </div>
        </div>
      `).join("")}
    </div>
  `;
}

function bindEvents() {
  const btnEdit = document.getElementById("btnEdit");
  if (btnEdit) {
    btnEdit.addEventListener("click", () => {
      state.editMode = true;
      render();
    });
  }

  const btnCancel = document.getElementById("btnCancel");
  if (btnCancel) {
    btnCancel.addEventListener("click", () => {
      state.editMode = false;
      state.form.pseudo = state.user?.pseudo || "";
      state.form.bio = state.user?.bio || "";
      render();
    });
  }

  const btnSave = document.getElementById("btnSave");
  if (btnSave) {
    btnSave.addEventListener("click", saveProfile);
  }

  const pseudoInput = document.getElementById("pseudoInput");
  if (pseudoInput) {
    pseudoInput.addEventListener("input", (e) => {
      state.form.pseudo = e.target.value;
    });
  }

  const bioInput = document.getElementById("bioInput");
  if (bioInput) {
    bioInput.addEventListener("input", (e) => {
      state.form.bio = e.target.value;
    });
  }
}

async function loadProfile() {
  root.innerHTML = `<div class="text-center py-5">Chargement…</div>`;

  try {
    const data = await api("/me");

    state.user = data.user ?? data;
    state.registrations = data.registrations || [];
    state.events = data.events || [];
    state.form.pseudo = state.user?.pseudo || "";
    state.form.bio = state.user?.bio || "";
    state.editMode = false;

    render();
  } catch (e) {
    console.error("Failed to load profile:", e);
    globalThis.location.href = "/login";
  }
}

async function saveProfile() {
  const pseudo = (state.form.pseudo || "").trim();
  const bio = (state.form.bio || "").trim();

  if (pseudo.length < 3 || pseudo.length > 30) {
    toast.error("Pseudo invalide (3 à 30 caractères)");
    return;
  }

  if (bio.length > 500) {
    toast.error("Bio trop longue (max 500)");
    return;
  }

  try {
    await fetchCsrf();
    await api("/me", {
      method: "PUT",
      body: { pseudo, bio },
      csrf: true,
    });

    toast.success("Profil mis à jour !");
    await loadProfile();
  } catch (e) {
    toast.error(e?.message || "Erreur lors de la mise à jour");
  }
}

loadProfile();