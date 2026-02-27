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

function renderAuthUI(me) {
  const navAuth = document.querySelector("#navAuth");
  if (!navAuth) return;

  const user = me?.user ?? me ?? null;

  if (!user) {
    navAuth.innerHTML = `<a class="btn btn-outline-light btn-sm" href="./login.html">Connexion</a>`;
    return;
  }

  const role = user.role || "PLAYER";
  const pseudo = user.pseudo || user.email || "Compte";

  navAuth.innerHTML = `
    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-secondary">${escapeHtml(pseudo)}</span>
      <span class="badge bg-dark">${escapeHtml(role)}</span>
      ${role === "ADMIN" ? `<a class="btn btn-warning btn-sm" href="./admin.html">Admin</a>` : ``}
      <button id="btnLogout" class="btn btn-outline-danger btn-sm" type="button">Logout</button>
    </div>
  `;

  document.querySelector("#btnLogout")?.addEventListener("click", async () => {
    try {
      await fetch(apiUrl("/logout"), { method: "POST", credentials: "include" });
    } catch { }
    window.location.href = "./index.html";
  });
}

(async function main() {
  try {
    await inject("#app-header", "./partials/header.html");
    await inject("#app-footer", "./partials/footer.html");

    const me = await loadMe();
    renderAuthUI(me);

    window.dispatchEvent(new CustomEvent("layout:ready", { detail: { me } }));
  } catch (e) {
    console.error("include.js error", e);
  }
})();