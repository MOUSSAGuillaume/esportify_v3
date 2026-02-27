import { API_BASE_URL } from "../js/config.js";

export async function api(path, { method = "GET", body, csrf = false } = {}) {
  const headers = { "Content-Type": "application/json" };

  // CSRF si besoin
  if (csrf) {
    const token = localStorage.getItem("csrfToken");
    if (token) headers["X-CSRF-TOKEN"] = token;
  }

  const res = await fetch(`${API_BASE_URL}${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
    credentials: "include", // IMPORTANT: garde PHPSESSID
  });

  const text = await res.text();
  let data = null;
  try { data = text ? JSON.parse(text) : null; } catch { data = { raw: text }; }

  if (!res.ok) {
    const msg = data?.error || `Erreur HTTP ${res.status}`;
    const err = new Error(msg);
    err.status = res.status;
    err.data = data;
    throw err;
  }

  return data;
}

export async function fetchCsrf() {
  const data = await api("/csrf");
  if (data?.csrfToken) localStorage.setItem("csrfToken", data.csrfToken);
  return data?.csrfToken;
}