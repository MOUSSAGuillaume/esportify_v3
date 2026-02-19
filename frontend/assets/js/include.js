async function loadPartial(selector, url) {
  const el = document.querySelector(selector);
  if (!el) return;
  const res = await fetch(url);
  el.innerHTML = await res.text();
}

export async function loadLayout() {
  await loadPartial("#app-header", "./partials/header.html");
  await loadPartial("#app-footer", "./partials/footer.html");
}