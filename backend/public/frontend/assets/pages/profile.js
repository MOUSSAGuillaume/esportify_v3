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
    return d.toLocaleString("fr-FR", { dateStyle: "medium", timeStyle: "short" });
}

function findEvent(eventId) {
    return state.events.find((e) => Number(e.id) === Number(eventId));
}

/**
 * DB réelle:
 * registrations.status = ACTIVE / REFUSED / CANCELLED
 * -> Mes inscriptions : ACTIVE
 * Favoris/Scores : pas dans registrations (à intégrer plus tard via tables favorites/scores)
 */
function computeDerived() {
    const regs = state.registrations || [];

    const myRegisteredEvents = regs
        .filter((r) => String(r.status).toUpperCase() === "ACTIVE")
        .map((r) => ({ event: findEvent(r.event_id), registration: r }))
        .filter((x) => x.event);

    return {
        myRegisteredEvents,
        favoriteEvents: [], // placeholder
        myScores: [],       // placeholder
    };
}

function canCreateEvent() {
    const role = String(state.user?.role || "").toUpperCase();
    return role === "ADMIN" || role === "ORGANIZER";
}

function roleLabel() {
    const role = String(state.user?.role || "").toUpperCase();
    if (role === "ADMIN") return "Admin";
    if (role === "ORGANIZER") return "Organisateur";
    return "Joueur";
}

function render() {
    const { favoriteEvents, myRegisteredEvents, myScores } = computeDerived();

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

              <div class="d-flex gap-4">
                <div class="text-center">
                  <div class="h3 mb-0">${myRegisteredEvents.length}</div>
                  <div class="text-muted small">Inscriptions</div>
                </div>
                <div class="text-center">
                  <div class="h3 mb-0">${favoriteEvents.length}</div>
                  <div class="text-muted small">Favoris</div>
                </div>
                <div class="text-center">
                  <div class="h3 mb-0">${myScores.length}</div>
                  <div class="text-muted small">Scores</div>
                </div>
              </div>
            </div>

            <div class="mt-4 d-flex flex-wrap gap-2">
              ${canCreateEvent() ? `<a class="btn btn-primary" href="../frontend/create_event.html">Créer un événement</a>` : ""}
              <a class="btn btn-outline-secondary" href="../frontend/events.html">Voir les événements</a>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12">
        ${renderTabs(favoriteEvents, myRegisteredEvents, myScores)}
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
    ${state.user?.bio ? `<p class="mt-2 mb-0">${escapeHtml(state.user.bio)}</p>` : `<p class="mt-2 mb-0 text-muted">Aucune bio.</p>`}
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

function renderTabs(favs, regs, scores) {
    return `
    <ul class="nav nav-tabs" id="profileTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-reg" data-bs-toggle="tab" data-bs-target="#pane-reg" type="button" role="tab">
          Mes inscriptions
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-fav" data-bs-toggle="tab" data-bs-target="#pane-fav" type="button" role="tab">
          Favoris
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-score" data-bs-toggle="tab" data-bs-target="#pane-score" type="button" role="tab">
          Mes scores
        </button>
      </li>
    </ul>

    <div class="tab-content border border-top-0 p-3 bg-white" id="profileTabsContent">
      <div class="tab-pane fade show active" id="pane-reg" role="tabpanel">
        ${renderRegs(regs)}
      </div>
      <div class="tab-pane fade" id="pane-fav" role="tabpanel">
        ${renderFavs(favs)}
      </div>
      <div class="tab-pane fade" id="pane-score" role="tabpanel">
        ${renderScores(scores)}
      </div>
    </div>
  `;
}

function renderFavs(list) {
    if (!list.length) {
        return `<div class="text-center text-muted py-5">Favoris : à intégrer via la table <code>favorites</code>.</div>`;
    }
    return `<div>OK</div>`;
}

function renderRegs(list) {
    if (!list.length) {
        return `<div class="text-center text-muted py-5">Aucune inscription ACTIVE</div>`;
    }
    return `
    <div class="vstack gap-3">
      ${list.map(({ event, registration }) => `
        <div class="card shadow-sm">
          <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3">
            <div>
              <div class="fw-bold">${escapeHtml(event.title)}</div>
              <div class="text-muted small">${escapeHtml(formatDate(event.start_at))}</div>
              <div class="mt-2">
                <span class="badge text-bg-light border">Statut inscription: ${escapeHtml(registration.status)}</span>
                <span class="badge text-bg-light border ms-2">Event: ${escapeHtml(event.status)}</span>
              </div>
            </div>
            <div class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-secondary" href="/event-details.php?id=${encodeURIComponent(event.id)}">Détails</a>
            </div>
          </div>
        </div>
      `).join("")}
    </div>
  `;
}

function renderScores(list) {
    if (!list.length) {
        return `<div class="text-center text-muted py-5">Scores : à intégrer via la table <code>scores</code>.</div>`;
    }
    return `<div>OK</div>`;
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
        pseudoInput.addEventListener("input", (e) => (state.form.pseudo = e.target.value));
    }

    const bioInput = document.getElementById("bioInput");
    if (bioInput) {
        bioInput.addEventListener("input", (e) => (state.form.bio = e.target.value));
    }
}

async function loadProfile() {
    root.innerHTML = `<div class="text-center py-5">Chargement…</div>`;

    try {
        const data = await api("/profile/me");

        state.user = data.user;
        state.registrations = data.registrations || [];
        state.events = data.events || [];

        state.form.pseudo = state.user?.pseudo || "";
        state.form.bio = state.user?.bio || "";
        state.editMode = false;

        render();
    } catch (e) {
        console.error("Failed to load profile:", e);
        globalThis.location.href = "../frontend/login.html";
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
        // fetchCsrf doit récupérer /csrf et configurer le header CSRF dans api.js
        await fetchCsrf();
        await api("/profile/me", { method: "PUT", body: { pseudo, bio }, csrf: true });

        toast.success("Profil mis à jour !");
        await loadProfile();
    } catch (e) {
        toast.error(e?.message || "Erreur lors de la mise à jour");
    }
}

loadProfile();