import { api, fetchCsrf } from "./api.js";
import { toast } from "./ui.js";

export async function me() {
  try {
    return await api("/me");
  } catch {
    return null;
  }
}

export async function login(email, password) {
  await fetchCsrf();
  await api("/login", { method: "POST", body: { email, password }, csrf: true });
  toast("Connecté ✅", "success");
}

export async function register(email, pseudo, password, role = "PLAYER") {
  await fetchCsrf();
  await api("/register", { method: "POST", body: { email, pseudo, password, role }, csrf: true });
  toast("Compte créé ✅", "success");
}

export async function logout() {
  await fetchCsrf();
  await api("/logout", { method: "POST", csrf: true });
  toast("Déconnecté", "secondary");
}

function safeText(value, fallback = "") {
  const s = String(value ?? "").trim();
  return s || fallback;
}

export async function authRenderNav() {
  const wrap = document.querySelector("#navAuth");
  if (!wrap) return;

  const data = await me();
  const user = data?.user || data;

  if (!user?.id) {
    wrap.innerHTML = `
      <a class="btn btn-outline-light btn-sm" href="/login">Connexion</a>
      <a class="btn btn-warning btn-sm" href="/register">Inscription</a>
    `;
    return;
  }

  const pseudo = safeText(user.pseudo, "User");
  const role = safeText(user.role, "PLAYER");

  wrap.innerHTML = "";

  const info = document.createElement("span");
  info.className = "text-light small me-2";
  info.textContent = `${pseudo} `;

  const badge = document.createElement("span");
  badge.className = "badge text-bg-secondary";
  badge.textContent = role;

  info.appendChild(badge);

  const button = document.createElement("button");
  button.className = "btn btn-outline-light btn-sm";
  button.id = "btnLogout";
  button.type = "button";
  button.textContent = "Logout";

  wrap.appendChild(info);
  wrap.appendChild(button);

  button.addEventListener("click", async () => {
    await logout();
    window.location.href = "/";
  });
}