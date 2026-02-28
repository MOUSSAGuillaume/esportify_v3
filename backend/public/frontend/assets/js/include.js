// assets/js/include.js (NON-MODULE)

const API_BASE = ""; // même domaine/port

function apiUrl(path) {
  const p = String(path || "");
  return API_BASE.replace(/\/+$/, "") + "/" + p.replace(/^\/+/, "");
}

async function inject(selector, url) {
  const el = document.querySelector(selector);
  if (!el) return;

  const res = await fetch(url, { cache: "no-store" });
  if (!res.ok) {
    console.error(`Impossible de charger ${url} (${res.status})`);
    el.innerHTML = "";
    return;
  }
  el.innerHTML = await res.text();
}

async function loadMe() {
  try {
    const res = await fetch(apiUrl("/me"), { credentials: "include" });
    if (!res.ok) return null;
    return await res.json();
  } catch (e) {
    console.error("loadMe error", e);
    return null;
  }
}

function escapeHtml(str) {
  return String(str ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

/** Active la nav centrale si tu as <ul id="navLinks"> */
function setActiveLink() {
  const path = location.pathname.split("/").pop();
  document.querySelectorAll("#navLinks a.nav-link").forEach((a) => {
    const href = (a.getAttribute("href") || "").split("/").pop();
    if (href && href === path) a.classList.add("active");
  });
}

/** CSRF: on stocke en localStorage pour le réutiliser (logout, POST, etc.) */
async function getCsrfToken() {
  const existing = localStorage.getItem("csrfToken");
  if (existing) return existing;

  try {
    const res = await fetch(apiUrl("/csrf"), { credentials: "include" });
    if (!res.ok) return null;

    const data = await res.json();
    if (data?.csrfToken) {
      localStorage.setItem("csrfToken", data.csrfToken);
      return data.csrfToken;
    }
  } catch (e) {
    console.error("getCsrfToken error", e);
  }
  return null;
}

async function doLogout() {
  const csrf = await getCsrfToken();

  const res = await fetch(apiUrl("/logout"), {
    method: "POST",
    credentials: "include",
    headers: csrf ? { "X-CSRF-TOKEN": csrf } : {},
  });

  if (!res.ok) {
    const txt = await res.text().catch(() => "");
    console.error("Logout failed:", res.status, txt);
    throw new Error(`Logout HTTP ${res.status}`);
  }
}

function roleBadgeHtml(role, currentPage) {
  if (role === "ORGANIZER") {
    return currentPage === "organizer.html"
      ? `<span class="badge bg-primary">ORGANIZER</span>`
      : `<a href="./organizer.html" class="badge bg-primary text-decoration-none">ORGANIZER</a>`;
  }

  if (role === "ADMIN") {
    return currentPage === "admin.html"
      ? `<span class="badge bg-warning text-dark">ADMIN</span>`
      : `<a href="./admin.html" class="badge bg-warning text-dark text-decoration-none">ADMIN</a>`;
  }

  return `<span class="badge bg-dark">${escapeHtml(role)}</span>`;
}

function renderAuthUI(me) {
  const navAuth = document.querySelector("#navAuth");
  if (!navAuth) return;

  const user = me?.user ?? me ?? null;

  // Non connecté
  if (!user) {
    navAuth.innerHTML = `<a class="btn btn-outline-light btn-sm" href="./login.html">Connexion</a>`;
    return;
  }

  const role = String(user.role || "PLAYER").toUpperCase();
  const pseudo = user.pseudo || user.email || "Compte";
  const current = location.pathname.split("/").pop();

  const badgeRole = roleBadgeHtml(role, current);

  navAuth.innerHTML = `
    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-secondary">${escapeHtml(pseudo)}</span>
      ${badgeRole}
      <button id="btnLogout" class="btn btn-outline-danger btn-sm" type="button">Logout</button>
    </div>
  `;

  // ✅ Bind logout (IMPORTANT: après insertion du bouton)
  document.querySelector("#btnLogout")?.addEventListener("click", async () => {
    try {
      await doLogout();
    } catch (e) {
      console.error(e);
    } finally {
      window.location.href = "./index.html";
    }
  });
}

(async function main() {
  try {
    await inject("#app-header", "./partials/header.html");
    await inject("#app-footer", "./partials/footer.html");

    // Optionnel mais confortable: précharger un token CSRF
    // (ça évite un "petit délai" au clic logout)
    getCsrfToken().catch(() => { });

    const me = await loadMe();
    renderAuthUI(me);
    setActiveLink();

    window.dispatchEvent(new CustomEvent("layout:ready", { detail: { me } }));
  } catch (e) {
    console.error("include.js error", e);
  }
})();