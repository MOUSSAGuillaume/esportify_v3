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

export async function authRenderNav() {
  const wrap = document.querySelector("#navAuth");
  if (!wrap) return;

  const data = await me();
  const user = data?.user || data; // selon ton format API

  if (!user?.id) {
    wrap.innerHTML = `
      <a class="btn btn-outline-light btn-sm" href="/login">Connexion</a>
      <a class="btn btn-warning btn-sm" href="/register">Inscription</a>
    `;
    return;
  }

  const role = user.role || "";
  wrap.innerHTML = `
    <span class="text-light small me-2">${user.pseudo ?? "User"} <span class="badge text-bg-secondary">${role}</span></span>
    <button class="btn btn-outline-light btn-sm" id="btnLogout">Logout</button>
  `;

  document.getElementById("btnLogout")?.addEventListener("click", async () => {
    await logout();
    window.location.href = "/";
  });
}