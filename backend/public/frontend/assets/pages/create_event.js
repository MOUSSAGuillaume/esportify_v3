import { api, fetchCsrf } from "../js/api.js";
import { toast } from "../js/ui.js";

const form = document.getElementById("createEventForm");
const coverInput = document.getElementById("cover_image");
const coverPreview = document.getElementById("coverPreview");
const galleryInput = document.getElementById("gallery_images");
const galleryPreview = document.getElementById("galleryPreview");
const submitBtn = document.querySelector("#createEventForm button[type='submit']");

function previewCover(file) {
    if (!file || !coverPreview) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        coverPreview.innerHTML = `<img src="${e.target.result}" alt="Prévisualisation image de couverture">`;
        coverPreview.classList.remove("d-none");
    };
    reader.readAsDataURL(file);
}

function previewGallery(files) {
    if (!galleryPreview) return;
    galleryPreview.innerHTML = "";

    [...files].forEach((file) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = document.createElement("img");
            img.src = e.target.result;
            img.alt = "Image supplémentaire";
            galleryPreview.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
}

async function requireOrganizerOrAdmin() {
    const me = await api("/me");
    const user = me?.user ?? me;

    const role = String(user?.role || "").toUpperCase();
    if (!user || !["ADMIN", "ORGANIZER"].includes(role)) {
        window.location.href = "/index";
        return null;
    }

    return user;
}

function validateDates(start, end) {
    if (!start || !end) return false;
    return new Date(end).getTime() > new Date(start).getTime();
}

async function handleSubmit(e) {
    e.preventDefault();

    const title = document.getElementById("title")?.value.trim() || "";
    const game = document.getElementById("game")?.value.trim() || "";
    const description = document.getElementById("description")?.value.trim() || "";
    const maxPlayers = Number(document.getElementById("max_players")?.value || 0);
    const startDate = document.getElementById("start_date")?.value || "";
    const endDate = document.getElementById("end_date")?.value || "";

    if (!title || !description || maxPlayers < 2 || !startDate || !endDate) {
        toast("Veuillez remplir tous les champs obligatoires.", "danger");
        return;
    }

    if (!validateDates(startDate, endDate)) {
        toast("La date de fin doit être après la date de début.", "danger");
        return;
    }

    try {
        submitBtn?.setAttribute("disabled", "disabled");

        await fetchCsrf();

        await api("/events", {
            method: "POST",
            csrf: true,
            body: {
                title,
                description: game ? `${description}\n\nJeu : ${game}` : description,
                start_at: startDate.replace("T", " "),
                end_at: endDate.replace("T", " "),
                max_players: maxPlayers
            }
        });

        toast("Événement créé avec succès. Il sera soumis à validation.", "success");
        form.reset();

        if (coverPreview) {
            coverPreview.innerHTML = "";
            coverPreview.classList.add("d-none");
        }

        if (galleryPreview) {
            galleryPreview.innerHTML = "";
        }

        setTimeout(() => {
            window.location.href = "/create_event";
        }, 900);

    } catch (err) {
        toast(err?.message || "Erreur lors de la création", "danger");
    } finally {
        submitBtn?.removeAttribute("disabled");
    }
}

async function init() {
    try {
        await requireOrganizerOrAdmin();

        coverInput?.addEventListener("change", (e) => {
            const file = e.target.files?.[0];
            if (file) previewCover(file);
        });

        galleryInput?.addEventListener("change", (e) => {
            const files = e.target.files;
            if (files?.length) previewGallery(files);
        });

        form?.addEventListener("submit", handleSubmit);

    } catch (err) {
        toast(err?.message || "Erreur de chargement", "danger");
    }
}

init();