// home.js — version non-module (Option B)

window.addEventListener("layout:ready", () => {
  const search = document.getElementById("eventsSearch");
  const list = document.getElementById("eventsList");

  function filterCards(q) {
    const query = (q || "").trim().toLowerCase();
    const cards = list?.querySelectorAll(".event-card") || [];

    cards.forEach((card) => {
      const text = card.innerText.toLowerCase();
      card.style.display = text.includes(query) ? "" : "none";
    });
  }

  if (search) {
    search.addEventListener("input", (e) => {
      filterCards(e.target.value);
    });
  }
});