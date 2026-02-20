import { loadLayout } from "../include.js";
import { login } from "../auth.js";
import { toast } from "../ui.js";

await loadLayout();

document.getElementById("loginForm")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  const fd = new FormData(e.currentTarget);
  const email = String(fd.get("email") || "");
  const password = String(fd.get("password") || "");

  try {
    await login(email, password);
    window.location.href = "./events.html";
  } catch (err) {
    toast(err.message || "Erreur", "danger");
  }
});