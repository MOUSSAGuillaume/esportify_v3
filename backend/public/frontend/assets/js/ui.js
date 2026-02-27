export function toast(message, type = "secondary") {
  const wrap = document.querySelector("#toastWrap");
  if (!wrap) return;

  const id = `t_${Math.random().toString(16).slice(2)}`;
  const html = `
    <div id="${id}" class="toast align-items-center text-bg-${type} border-0 mb-2" role="alert">
      <div class="d-flex">
        <div class="toast-body">${escapeHtml(message)}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  `;
  wrap.insertAdjacentHTML("beforeend", html);

  const el = document.getElementById(id);
  const t = new bootstrap.Toast(el, { delay: 2500 });
  t.show();
  el.addEventListener("hidden.bs.toast", () => el.remove());
}

function escapeHtml(s) {
  return String(s)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}