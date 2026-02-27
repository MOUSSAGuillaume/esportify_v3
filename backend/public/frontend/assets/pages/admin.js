(() => {
  "use strict";

  const API_BASE = ""; // même origin
  let CSRF = null;
  let chartEvents = null;
  let chartUsers = null;

  const $ = (id) => document.getElementById(id);

  function escapeHtml(str) {
    return String(str ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function fmtDate(dt) {
    if (!dt) return "—";
    const d = new Date(dt);
    if (Number.isNaN(d.getTime())) return String(dt);
    return new Intl.DateTimeFormat("fr-FR", {
      day: "2-digit", month: "2-digit", year: "numeric",
      hour: "2-digit", minute: "2-digit",
    }).format(d);
  }

  async function apiFetch(path, opts = {}) {
    const url = API_BASE + path;

    const headers = { ...(opts.headers || {}) };
    if (opts.json) headers["Content-Type"] = "application/json";
    if (CSRF) headers["X-CSRF-TOKEN"] = CSRF;

    const res = await fetch(url, {
      credentials: "include",
      ...opts,
      headers,
    });

    if (res.status === 204) return null;

    const data = await res.json().catch(() => null);

    if (!res.ok) {
      const msg = data?.error || `Erreur API (${res.status})`;
      throw new Error(msg);
    }
    return data;
  }

  async function requireAdmin() {
    const me = await apiFetch("/me"); // => { user: {...} }
    const user = me?.user || null;
    if (!user || String(user.role).toUpperCase() !== "ADMIN") {
      window.location.href = "/frontend/index.html";
      return null;
    }
    return user;
  }

  async function loadCsrf() {
    const data = await apiFetch("/csrf");
    CSRF = data?.csrfToken || null;
  }

  function buildLegendEvents(items) {
    const legend = $("legendEvents");
    if (!legend) return;
    legend.innerHTML = "";
    for (const it of items) {
      const el = document.createElement("div");
      el.className = "d-flex align-items-center gap-2";
      el.innerHTML = `
        <span style="display:inline-block;width:10px;height:10px;border-radius:999px;background:${it.color}"></span>
        <span class="text-secondary">${escapeHtml(it.label)}:</span>
        <span>${it.value}</span>
      `;
      legend.appendChild(el);
    }
  }

  async function loadStats() {
    const raw = await apiFetch("/admin/stats");
    const st = raw?.stats ?? raw;

    const usersTotal = st.users?.total ?? 0;
    const eventsTotal = st.events?.total ?? 0;

    const pending   = st.events?.byStatus?.PENDING ?? 0;
    const validated = st.events?.byStatus?.VALIDATED ?? 0;
    const rejected  = st.events?.byStatus?.REJECTED ?? 0;
    const suspended = st.events?.byStatus?.SUSPENDED ?? 0;

    const unread = st.messages?.unread ?? 0;

    const usersPlayer    = st.users?.byRole?.PLAYER ?? 0;
    const usersOrganizer = st.users?.byRole?.ORGANIZER ?? 0;
    const usersAdmin     = st.users?.byRole?.ADMIN ?? 0;

    $("statUsers").textContent = usersTotal;
    $("statEvents").textContent = eventsTotal;
    $("statPending").textContent = pending;
    $("statUnread").textContent = unread;

    $("pillPending").textContent = pending;
    $("pillUnread").textContent = unread;

    const doughnutData = [
      { label: "En attente", value: pending, color: "#EAB308" },
      { label: "Validés", value: validated, color: "#22C55E" },
      { label: "Refusés", value: rejected, color: "#EF4444" },
      { label: "Suspendus", value: suspended, color: "#6B7280" },
    ];

    const ctx1 = $("chartEvents");
    if (ctx1) {
      chartEvents?.destroy();
      chartEvents = new Chart(ctx1, {
        type: "doughnut",
        data: {
          labels: doughnutData.map(x => x.label),
          datasets: [{ data: doughnutData.map(x => x.value), backgroundColor: doughnutData.map(x => x.color), borderWidth: 0 }],
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: "65%", plugins: { legend: { display: false } } },
      });
      buildLegendEvents(doughnutData);
    }

    const barData = [
      { label: "Joueurs", value: usersPlayer },
      { label: "Organisateurs", value: usersOrganizer },
      { label: "Admins", value: usersAdmin },
    ];

    const ctx2 = $("chartUsers");
    if (ctx2) {
      chartUsers?.destroy();
      chartUsers = new Chart(ctx2, {
        type: "bar",
        data: {
          labels: barData.map(x => x.label),
          datasets: [{ label: "Utilisateurs", data: barData.map(x => x.value), borderRadius: 8 }],
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
      });
    }
  }

  async function loadPendingEvents() {
    const data = await apiFetch("/admin/events?status=PENDING");
    const events = data?.events ?? [];

    const tbody = $("eventsTbody");
    tbody.innerHTML = "";
    $("eventsEmpty")?.classList.toggle("d-none", events.length !== 0);

    for (const ev of events) {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${ev.id}</td>
        <td class="fw-semibold">${escapeHtml(ev.title || "")}</td>
        <td>${ev.organizer_id ?? "—"}</td>
        <td>${fmtDate(ev.start_at)}</td>
        <td>${ev.max_players ?? "—"}</td>
        <td><span class="badge badge-soft">${escapeHtml(ev.status || "PENDING")}</span></td>
        <td class="text-end">
          <div class="btn-group btn-group-sm">
            <button class="btn btn-success" data-action="validate" data-id="${ev.id}">Approuver</button>
            <button class="btn btn-danger" data-action="reject" data-id="${ev.id}">Refuser</button>
            <button class="btn btn-outline-warning" data-action="suspend" data-id="${ev.id}">Suspendre</button>
          </div>
        </td>
      `;
      tbody.appendChild(tr);
    }

    tbody.querySelectorAll("button[data-action]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const id = btn.getAttribute("data-id");
        const action = btn.getAttribute("data-action");
        await apiFetch(`/admin/events/${id}/${action}`, { method: "POST" });
        await refreshAll();
      });
    });
  }

  async function loadUsers() {
    const data = await apiFetch("/admin/users");
    const users = data?.users ?? [];

    const tbody = $("usersTbody");
    tbody.innerHTML = "";

    for (const u of users) {
      const role = (u.role || "PLAYER").toUpperCase();
      const suspended = Number(u.is_suspended) === 1;

      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${u.id}</td>
        <td>${escapeHtml(u.email || "")}</td>
        <td>${escapeHtml(u.pseudo || "")}</td>
        <td>
          <select class="form-select form-select-sm bg-dark text-white border border-white/10" data-role-user="${u.id}">
            ${["PLAYER","ORGANIZER","ADMIN"].map(r => `<option value="${r}" ${r===role?"selected":""}>${r}</option>`).join("")}
          </select>
        </td>
        <td>${Number(u.is_active) === 1 ? "✅" : "❌"}</td>
        <td>${suspended ? `<span class="text-danger">Suspendu</span>` : `<span class="text-success">OK</span>`}</td>
        <td>${fmtDate(u.created_at)}</td>
        <td class="text-end">
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-light" data-user-action="saveRole" data-id="${u.id}">Enregistrer rôle</button>
            ${suspended
              ? `<button class="btn btn-success" data-user-action="unsuspend" data-id="${u.id}">Réactiver</button>`
              : `<button class="btn btn-danger" data-user-action="suspend" data-id="${u.id}">Suspendre</button>`
            }
          </div>
        </td>
      `;
      tbody.appendChild(tr);
    }

    tbody.querySelectorAll("button[data-user-action]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const id = btn.getAttribute("data-id");
        const act = btn.getAttribute("data-user-action");

        if (act === "saveRole") {
          const sel = document.querySelector(`select[data-role-user="${id}"]`);
          await apiFetch(`/admin/users/${id}/role`, { method: "POST", json: true, body: JSON.stringify({ role: sel.value }) });
        } else {
          await apiFetch(`/admin/users/${id}/${act}`, { method: "POST" });
        }
        await refreshAll();
      });
    });
  }

  async function loadMessages() {
    const data = await apiFetch("/admin/messages");
    const messages = data?.messages ?? [];

    const list = $("messagesList");
    list.innerHTML = "";
    $("messagesEmpty")?.classList.toggle("d-none", messages.length !== 0);

    const modalEl = $("messageModal");
    const modal = modalEl ? new bootstrap.Modal(modalEl) : null;

    for (const m of messages) {
      const unread = Number(m.is_read) === 0;

      const item = document.createElement("button");
      item.type = "button";
      item.className =
        "list-group-item list-group-item-action d-flex justify-content-between align-items-center " +
        (unread ? "border-start border-4 border-primary" : "");

      item.innerHTML = `
        <div class="text-start">
          <div class="fw-semibold">
            ${escapeHtml(m.subject || "(Sans sujet)")}
            ${unread ? `<span class="badge bg-primary ms-2">Nouveau</span>` : ""}
          </div>
          <div class="text-secondary small">${escapeHtml(m.name || "")} — ${escapeHtml(m.email || "")}</div>
        </div>
        <div class="text-secondary small">${fmtDate(m.created_at)}</div>
      `;

      item.addEventListener("click", async () => {
        if (unread) {
          await apiFetch(`/admin/messages/${m.id}/read`, { method: "POST" }).catch(() => {});
        }

        $("modalSubject").textContent = m.subject || "Message";
        $("modalMeta").textContent = `De: ${m.name || ""} (${m.email || ""}) — ${fmtDate(m.created_at)}`;
        $("modalBody").textContent = m.message || "";
        $("modalReply").href = `mailto:${encodeURIComponent(m.email || "")}?subject=${encodeURIComponent("Re: " + (m.subject || ""))}`;

        modal?.show();
        await refreshAll();
      });

      list.appendChild(item);
    }
  }

  async function refreshAll() {
    await Promise.all([loadStats(), loadPendingEvents(), loadUsers(), loadMessages()]);
  }

  document.addEventListener("DOMContentLoaded", async () => {
    try {
      await requireAdmin();
      await loadCsrf();

      $("btnRefreshEvents")?.addEventListener("click", loadPendingEvents);
      $("btnRefreshUsers")?.addEventListener("click", loadUsers);
      $("btnRefreshMessages")?.addEventListener("click", loadMessages);

      await refreshAll();
    } catch (e) {
      console.error(e);
      alert(e.message);
    }
  });
})();