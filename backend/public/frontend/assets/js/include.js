// assets/js/include.js (NON-MODULE)

const API_BASE = "";

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

function ensureBootstrapJs() {
  return new Promise((resolve, reject) => {
    if (window.bootstrap) {
      resolve();
      return;
    }

    const existing = document.querySelector('script[data-bootstrap-bundle="true"]');
    if (existing) {
      existing.addEventListener("load", () => resolve(), { once: true });
      existing.addEventListener("error", () => reject(new Error("Bootstrap JS load error")), { once: true });
      return;
    }

    const script = document.createElement("script");
    script.src = "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js";
    script.dataset.bootstrapBundle = "true";
    script.onload = () => resolve();
    script.onerror = () => reject(new Error("Bootstrap JS load error"));
    document.head.appendChild(script);
  });
}

function ensureBootstrapIcons() {
  if (document.querySelector('link[data-bootstrap-icons]')) return;

  const link = document.createElement("link");
  link.rel = "stylesheet";
  link.href = "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css";
  link.dataset.bootstrapIcons = "true";

  document.head.appendChild(link);
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
  const path = location.pathname.split("/").pop();
  document.querySelectorAll("#navLinks a.nav-link").forEach((a) => {
    const href = (a.getAttribute("href") || "").split("/").pop();
    if (href && href === path) a.classList.add("active");
  });
}

async function getCsrfToken() {
  const existing = localStorage.getItem("csrfToken");
  if (existing) return existing;

  try {
    const res = await fetch(apiUrl("/csrf"), { credentials: "include" });
    if (!res.ok) return null;

    const data = await res.json();
    if (data?.token) {
      localStorage.setItem("csrfToken", data.token);
      return data.token;
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
    headers: csrf ? { "X-CSRF-Token": csrf } : {},
  });

  if (!res.ok) {
    const txt = await res.text().catch(() => "");
    console.error("Logout failed:", res.status, txt);
    throw new Error(`Logout HTTP ${res.status}`);
  }
}

function roleLabel(role) {
  const r = String(role || "").toUpperCase();
  if (r === "ADMIN") return "Admin";
  if (r === "ORGANIZER") return "Organisateur";
  return "Joueur";
}

function canOrganizer(role) {
  const r = String(role || "").toUpperCase();
  return r === "ORGANIZER" || r === "ADMIN";
}

function isAdmin(role) {
  return String(role || "").toUpperCase() === "ADMIN";
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

  const linkProfile = "./profile.html";
  const linkOrganizer = "./organizer.html";
  const linkAdmin = "./admin.html";

  const organizerItem = canOrganizer(role)
    ? `
      <li>
        <a class="dropdown-item d-flex align-items-center gap-2" href="${linkOrganizer}">
          <i class="bi bi-trophy"></i>
          <span>Mes événements</span>
        </a>
      </li>
    `
    : "";

  const adminItem = isAdmin(role)
    ? `
      <li>
        <a class="dropdown-item d-flex align-items-center gap-2" href="${linkAdmin}">
          <i class="bi bi-shield"></i>
          <span>Administration</span>
        </a>
      </li>
    `
    : "";

  navAuth.innerHTML = `
    <div class="d-flex align-items-center gap-2 user-menu">
      <div class="dropdown">
        <button
          class="btn user-dropdown-btn d-flex align-items-center gap-2"
          type="button"
          data-bs-toggle="dropdown"
          aria-expanded="false"
        >
          <div class="user-avatar">
            <i class="bi bi-person-fill"></i>
          </div>
          <span class="user-name">${escapeHtml(pseudo)}</span>
        </button>

        <ul class="dropdown-menu dropdown-menu-end user-dropdown shadow">
          <li>
            <a class="dropdown-item d-flex align-items-center gap-2" href="${linkProfile}">
              <i class="bi bi-person"></i>
              <span>Mon espace</span>
            </a>
          </li>

          ${organizerItem}
          ${adminItem}

          <li><hr class="dropdown-divider"></li>

          <li>
            <button class="dropdown-item d-flex align-items-center gap-2 text-danger" id="btnLogout" type="button">
              <i class="bi bi-box-arrow-right"></i>
              <span>Déconnexion</span>
            </button>
          </li>
        </ul>
      </div>

      <span class="badge user-role">${escapeHtml(roleLabel(role))}</span>
    </div>
  `;

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
    ensureBootstrapIcons();
    await ensureBootstrapJs();

    await inject("#app-header", "./partials/header.html");
    await inject("#app-footer", "./partials/footer.html");

    getCsrfToken().catch(() => { });

    const me = await loadMe();
    renderAuthUI(me);
    setActiveLink();

    window.dispatchEvent(new CustomEvent("layout:ready", { detail: { me } }));
  } catch (e) {
    console.error("include.js error", e);
  }
})();