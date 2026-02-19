import { apiFetch } from "./api.js";
import { ROUTES } from "./config.js";

export async function login(email, password) {
  return apiFetch(ROUTES.login, { method: "POST", body: { email, password } });
}

export async function logout() {
  return apiFetch(ROUTES.logout, { method: "POST", body: {} });
}

export async function me() {
  return apiFetch(ROUTES.me, { method: "GET" });
}