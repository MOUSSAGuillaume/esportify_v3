import { API_BASE, ROUTES } from "./config.js";

let csrfToken = null;

export async function ensureCsrf() {
  if (csrfToken) return csrfToken;

  const res = await fetch(API_BASE + ROUTES.csrf, { credentials: "include" });
  const data = await res.json();
  csrfToken = data.csrfToken;
  return csrfToken;
}

export async function apiFetch(path, { method = "GET", body = null, headers = {} } = {}) {
  const opts = {
    method,
    credentials: "include",
    headers: {
      "Accept": "application/json",
      ...headers,
    },
  };

  if (body !== null) {
    // CSRF sur toutes les requêtes qui écrivent
    const token = await ensureCsrf();
    opts.headers["Content-Type"] = "application/json";
    opts.headers["X-CSRF-TOKEN"] = token;
    opts.body = JSON.stringify(body);
  }

  const res = await fetch(API_BASE + path, opts);

  // essaye de lire json même en erreur
  let data = null;
  try { data = await res.json(); } catch { /* ignore */ }

  if (!res.ok) {
    const msg = data?.error || `HTTP ${res.status}`;
    const err = new Error(msg);
    err.status = res.status;
    err.data = data;
    throw err;
  }

  return data;
}