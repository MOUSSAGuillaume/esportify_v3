import { authRenderNav } from "./auth.js";

async function inject(selector, url) {
  const el = document.querySelector(selector);
  if (!el) return;
  const res = await fetch(url, { cache: "no-store" });
  el.innerHTML = await res.text();
}

export async function loadLayout() {
  await inject("#app-header", "./partials/header.html");
  await inject("#app-footer", "./partials/footer.html");
  await authRenderNav();
}