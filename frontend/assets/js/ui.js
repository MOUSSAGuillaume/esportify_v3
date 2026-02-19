export function setLoading(isLoading) {
  const el = document.querySelector("[data-loading]");
  if (!el) return;
  el.classList.toggle("d-none", !isLoading);
}

export function toast(msg, type = "info") {
  const wrap = document.getElementById("toastWrap");
  if (!wrap) return alert(msg);

  const div = document.createElement("div");
  div.className = `toast align-items-center text-bg-${type} border-0 show mb-2`;
  div.innerHTML = `
    <div class="d-flex">
      <div class="toast-body">${msg}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto"></button>
    </div>
  `;
  div.querySelector("button").onclick = () => div.remove();
  wrap.appendChild(div);
  setTimeout(() => div.remove(), 3500);
}