window.addEventListener("layout:ready", async (e) => {
  const me = e.detail?.me?.user ?? e.detail?.me ?? null;

  const mySectionsEl = document.getElementById("myPlayerSections");
  const myCurrentEventsEl = document.getElementById("myCurrentEvents");
  const myUpcomingEventsEl = document.getElementById("myUpcomingEvents");

  function escapeHtml(str) {
    return String(str ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function formatDate(dateValue) {
    if (!dateValue) return "Date inconnue";
    const d = new Date(dateValue);
    if (Number.isNaN(d.getTime())) return "Date invalide";
    return d.toLocaleString("fr-FR");
  }

  function isStarted(ev) {
    return Boolean(ev.started_at ?? ev.startedAt);
  }

  function isFinished(ev) {
    return Boolean(ev.finished_at ?? ev.finishedAt);
  }

  function isUpcoming(ev) {
    const start = ev.start_at ?? ev.startAt;
    if (!start) return false;
    const d = new Date(start);
    return !Number.isNaN(d.getTime()) && d.getTime() > Date.now() && !isFinished(ev);
  }

  function eventMiniCard(ev, statusText) {
    return `
      <a href="./event.html?id=${encodeURIComponent(ev.id)}"
         class="text-decoration-none">
        <div class="border border-secondary rounded-3 p-3 bg-black bg-opacity-25">
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
              <div class="fw-semibold text-light">${escapeHtml(ev.title ?? "Événement")}</div>
              <div class="text-secondary small">${formatDate(ev.start_at ?? ev.startAt)}</div>
            </div>
            <span class="badge text-bg-secondary">${escapeHtml(statusText)}</span>
          </div>
        </div>
      </a>
    `;
  }

  async function loadMyRegistrations() {
    const res = await fetch("/me/registrations", { credentials: "include" });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    return data?.events ?? data?.items ?? [];
  }

  if (!me) return;

  try {
    const rows = await loadMyRegistrations();

    if (mySectionsEl) {
      mySectionsEl.classList.remove("d-none");
    }

    const currentEvents = rows.filter((ev) => isStarted(ev) && !isFinished(ev));
    const upcomingEvents = rows.filter((ev) => isUpcoming(ev));

    if (myCurrentEventsEl) {
      myCurrentEventsEl.innerHTML = currentEvents.length
        ? currentEvents.map((ev) => eventMiniCard(ev, "En cours")).join("")
        : `<div class="text-secondary small">Aucun événement en cours.</div>`;
    }

    if (myUpcomingEventsEl) {
      myUpcomingEventsEl.innerHTML = upcomingEvents.length
        ? upcomingEvents.map((ev) => eventMiniCard(ev, "À venir")).join("")
        : `<div class="text-secondary small">Aucun événement à venir.</div>`;
    }
  } catch (err) {
    console.error("home.js error", err);

    if (myCurrentEventsEl) {
      myCurrentEventsEl.innerHTML = `<div class="text-secondary small">Impossible de charger vos événements.</div>`;
    }

    if (myUpcomingEventsEl) {
      myUpcomingEventsEl.innerHTML = `<div class="text-secondary small">Impossible de charger vos événements.</div>`;
    }
  }
});