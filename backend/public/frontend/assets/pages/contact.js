import { loadLayout } from "../js/include.js";
import { toast } from "../js/ui.js";
import { api, fetchCsrf } from "../js/api.js";

await loadLayout();

const form = document.getElementById("contactForm");
const btnSend = document.getElementById("btnSend");
const errorEl = document.getElementById("contactError");

const success = document.getElementById("contactSuccess");
const formWrap = document.getElementById("contactFormWrap");
const sendAnother = document.getElementById("sendAnother");

const nameEl = document.getElementById("name");
const emailEl = document.getElementById("email");
const subjectEl = document.getElementById("subject");
const messageEl = document.getElementById("message");
const honeyEl = document.getElementById("company");

function setLoading(isLoading) {
  if (!btnSend) return;
  btnSend.disabled = isLoading;
  btnSend.textContent = isLoading ? "Envoi..." : "Envoyer";
}

function showError(msg) {
  if (!errorEl) return;
  errorEl.textContent = msg;
  errorEl.classList.remove("d-none");
}

function hideError() {
  if (!errorEl) return;
  errorEl.textContent = "";
  errorEl.classList.add("d-none");
}

function validate() {
  let ok = true;

  // anti-spam
  if (honeyEl && honeyEl.value.trim().length > 0) return false;

  const fields = [nameEl, emailEl, subjectEl, messageEl];
  for (const el of fields) {
    if (!el) continue;

    // checkValidity gère email + required + minlength
    if (!el.value.trim() || !el.checkValidity()) {
      el.classList.add("is-invalid");
      ok = false;
    } else {
      el.classList.remove("is-invalid");
    }
  }

  return ok;
}

function resetForm() {
  form?.reset();
  [nameEl, emailEl, subjectEl, messageEl].forEach((el) => el?.classList.remove("is-invalid"));
  hideError();
}

function showSuccess() {
  formWrap?.classList.add("d-none");
  success?.classList.remove("d-none");
}

function showForm() {
  success?.classList.add("d-none");
  formWrap?.classList.remove("d-none");
}

// Submit
form?.addEventListener("submit", async (e) => {
  e.preventDefault();
  hideError();

  if (!validate()) {
    toast("Veuillez remplir correctement tous les champs.", "danger");
    return;
  }

  const payload = {
    name: nameEl.value.trim(),
    email: emailEl.value.trim(),
    subject: subjectEl.value.trim(),
    message: messageEl.value.trim(),
  };

  try {
    setLoading(true);

    // CSRF puis envoi
    await fetchCsrf();

    // ✅ Endpoint proposé : /contact
    // Si ton backend utilise un autre endpoint, dis-moi le chemin exact et j’adapte.
    await api("/contact", {
      method: "POST",
      csrf: true,
      body: payload,
    });

    toast("Message envoyé ✅", "success");
    showSuccess();
    resetForm();
  } catch (err) {
    const msg = err?.data?.error || err?.message || "Erreur lors de l'envoi du message";
    showError(msg);
    toast(msg, "danger");
  } finally {
    setLoading(false);
  }
});

sendAnother?.addEventListener("click", () => {
  showForm();
});