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

function setActiveLink() {
  const path = location.pathname.split("/").pop(); // organizer.html
  document.querySelectorAll("#navLinks a.nav-link").forEach(a => {
    const href = (a.getAttribute("href") || "").split("/").pop();
    if (href && href === path) a.classList.add("active");
  });
}

function ensureDashboardLink(role) {
  const navLinks = document.querySelector("#navLinks");
  if (!navLinks) return;

  // déjà présent ?
  if (navLinks.querySelector('a[href="./organizer.html"]')) return;

  if (String(role).toUpperCase() === "ORGANIZER") {
    const li = document.createElement("li");
    li.className = "nav-item";
    li.innerHTML = `<a class="nav-link fw-semibold" href="./organizer.html">Dashboard</a>`;
    navLinks.appendChild(li);
  }
}

function renderAuthUI(me) {
  const navAuth = document.querySelector("#navAuth");
  if (!navAuth) return;

  const user = me?.user ?? me ?? null;

  if (!user) {
    navAuth.innerHTML = `<a class="btn btn-outline-light btn-sm" href="./login.html">Connexion</a>`;
    return;
  }

  const role = String(user.role || "PLAYER").toUpperCase();
  const pseudo = user.pseudo || user.email || "Compte";

  // Bouton selon rôle (dans le bloc à droite)
  let roleBtn = "";
  if (role === "ADMIN") {
    roleBtn = `<a class="btn btn-warning btn-sm" href="./admin.html">Admin</a>`;
  } else if (role === "ORGANIZER") {
    roleBtn = `<a class="btn btn-primary btn-sm" href="./organizer.html">Dashboard</a>`;
  } else {
    // PLAYER (à adapter selon ta page)
    roleBtn = `<a class="btn btn-outline-info btn-sm" href="./profile.html">Mon compte</a>`;
  }

  const current = location.pathname.split("/").pop();

  let roleBadge = "";

  // ORGANIZER
  if (role === "ORGANIZER") {
    roleBadge = current === "organizer.html"
      ? `<span class="badge bg-primary">ORGANIZER</span>`
      : `<a href="./organizer.html" class="badge bg-primary text-decoration-none">ORGANIZER</a>`;
  }

  // ADMIN
  else if (role === "ADMIN") {
    roleBadge = current === "admin.html"
      ? `<span class="badge bg-warning text-dark">ADMIN</span>`
      : `<a href="./admin.html" class="badge bg-warning text-dark text-decoration-none">ADMIN</a>`;
  }

  // PLAYER
  else {
    roleBadge = `<span class="badge bg-dark">${escapeHtml(role)}</span>`;
  }

  navAuth.innerHTML = `
  <div class="d-flex align-items-center gap-2">
    <span class="badge bg-secondary">${escapeHtml(pseudo)}</span>
    ${roleBadge}
    <button id="btnLogout" class="btn btn-outline-danger btn-sm" type="button">Logout</button>
  </div>
`;
}

(async function main() {
  try {
    await inject("#app-header", "./partials/header.html");
    await inject("#app-footer", "./partials/footer.html");

    const me = await loadMe();
    renderAuthUI(me);
    setActiveLink();

    window.dispatchEvent(new CustomEvent("layout:ready", { detail: { me } }));
  } catch (e) {
    console.error("include.js error", e);
  }
})();