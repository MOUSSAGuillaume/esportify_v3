//import { loadLayout } from "../js/include.js";
import { login } from "../js/auth.js";
import { toast } from "../js/ui.js";
import { api, fetchCsrf } from "../js/api.js";

await loadLayout();

const form = document.getElementById("loginForm");
const emailEl = document.getElementById("email");
const passEl = document.getElementById("password");
const btnLogin = document.getElementById("btnLogin");
const loginError = document.getElementById("loginError");

const goRegister = document.getElementById("goRegister");

// Reset modal
const resetEmail = document.getElementById("resetEmail");
const resetBtn = document.getElementById("resetBtn");
const resetMessage = document.getElementById("resetMessage");

// Petit helper UI
function setLoading(isLoading) {
  if (btnLogin) btnLogin.disabled = isLoading;
  if (btnLogin) btnLogin.textContent = isLoading ? "Connexion..." : "Connexion";
}

function showError(msg) {
  if (!loginError) return;
  loginError.textContent = msg;
  loginError.classList.remove("d-none");
}

function hideError() {
  if (!loginError) return;
  loginError.textContent = "";
  loginError.classList.add("d-none");
}

function validateForm() {
  let ok = true;

  if (!emailEl.value.trim() || !emailEl.checkValidity()) {
    emailEl.classList.add("is-invalid");
    ok = false;
  } else {
    emailEl.classList.remove("is-invalid");
  }

  if (!passEl.value.trim() || passEl.value.trim().length < 6) {
    passEl.classList.add("is-invalid");
    ok = false;
  } else {
    passEl.classList.remove("is-invalid");
  }

  return ok;
}

// CTA inscription
goRegister?.addEventListener("click", () => {
  window.location.href = "./register.html"; // adapte si ton fichier s'appelle différemment
});

// Submit login
form?.addEventListener("submit", async (e) => {
  e.preventDefault();
  hideError();

  if (!validateForm()) return;

  try {
    setLoading(true);

    // CSRF puis login
    await fetchCsrf();
    await api("/login", {
      method: "POST",
      csrf: true,
      body: {
        email: emailEl.value.trim(),
        password: passEl.value,
      },
    });

    toast("Connexion réussie ✅", "success");

    // Redirection (choisis ta page)
    window.location.href = "./events.html";
  } catch (err) {
    const msg = err?.data?.error || err?.message || "Erreur de connexion";
    showError(msg);
    toast(msg, "danger");
  } finally {
    setLoading(false);
  }
});

// Reset password (pour l’instant placeholder)
resetBtn?.addEventListener("click", async () => {
  const email = (resetEmail?.value || "").trim();

  resetMessage?.classList.remove("d-none");
  resetMessage.textContent = "";

  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    resetMessage.className = "mt-3 mb-0 text-danger";
    resetMessage.textContent = "Veuillez entrer un email valide.";
    return;
  }

  // Tu n’as pas encore d’endpoint reset côté API => on met proprement en “bientôt dispo”
  resetMessage.className = "mt-3 mb-0 text-secondary";
  resetMessage.textContent = "Fonction de réinitialisation à brancher côté API (bientôt).";

  // Quand tu auras un endpoint, tu remplaceras par :
  // await api("/password/reset-request", { method:"POST", body:{ email }, csrf:true })
});