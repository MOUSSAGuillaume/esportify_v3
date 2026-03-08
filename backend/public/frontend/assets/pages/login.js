import { toast } from "../js/ui.js";
import { api, fetchCsrf } from "../js/api.js";

const form = document.getElementById("loginForm");
const emailEl = document.getElementById("email");
const passEl = document.getElementById("password");
const btnLogin = document.getElementById("btnLogin");
const loginError = document.getElementById("loginError");
const goRegister = document.getElementById("goRegister");

// optionnels
const resetBtn = document.getElementById("resetBtn");
const resetEmail = document.getElementById("resetEmail");
const resetMessage = document.getElementById("resetMessage");

function setLoading(isLoading) {
  if (!btnLogin) return;
  btnLogin.disabled = isLoading;
  btnLogin.textContent = isLoading ? "Connexion..." : "Connexion";
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

  if (!emailEl?.value.trim() || !emailEl.checkValidity()) {
    emailEl?.classList.add("is-invalid");
    ok = false;
  } else {
    emailEl?.classList.remove("is-invalid");
  }

  if (!passEl?.value.trim() || passEl.value.trim().length < 6) {
    passEl?.classList.add("is-invalid");
    ok = false;
  } else {
    passEl?.classList.remove("is-invalid");
  }

  return ok;
}

goRegister?.addEventListener("click", () => {
  window.location.href = "/register";
});

form?.addEventListener("submit", async (e) => {
  e.preventDefault();
  hideError();

  if (!validateForm()) return;

  try {
    setLoading(true);

    await fetchCsrf();
    const data = await api("/login", {
      method: "POST",
      csrf: true,
      body: {
        email: emailEl.value.trim(),
        password: passEl.value,
      },
    });

    toast("Connexion réussie ✅", "success");

    const user = data?.user ?? null;
    const role = String(user?.role || "").toUpperCase();

    // Redirection cohérente
    if (role === "ADMIN") {
      window.location.href = "/profile";
      return;
    }

    if (role === "ORGANIZER") {
      window.location.href = "/profile";
      return;
    }

    // joueur par défaut
    window.location.href = "/profile";
  } catch (err) {
    const msg = err?.data?.error || err?.message || "Erreur de connexion";
    showError(msg);
    toast(msg, "danger");
  } finally {
    setLoading(false);
  }
});

// Reset password (placeholder propre)
resetBtn?.addEventListener("click", async () => {
  const email = (resetEmail?.value || "").trim();

  if (!resetMessage) return;

  resetMessage.classList.remove("d-none");
  resetMessage.textContent = "";

  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    resetMessage.className = "mt-3 mb-0 text-danger";
    resetMessage.textContent = "Veuillez entrer un email valide.";
    return;
  }

  resetMessage.className = "mt-3 mb-0 text-secondary";
  resetMessage.textContent = "Fonction de réinitialisation à brancher côté API (bientôt).";
});