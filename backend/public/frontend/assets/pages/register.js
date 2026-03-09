/* Esportify — Register (module) */
/*import { loadLayout } from "../js/include.js";
await loadLayout();*/

(function () {
    "use strict";

    const els = {
        overlay: document.getElementById("console-overlay"),
        consoleText: document.getElementById("console-text"),
        signupBlock: document.getElementById("signup-block"),

        form: document.getElementById("signup-form"),
        errorMsg: document.getElementById("form-error"),

        popup: document.getElementById("confirmationPopup"),
        closePopupBtn: document.getElementById("closeConfirmationBtn"),

        email: document.getElementById("email"),
        pseudo: document.getElementById("pseudo"),
        password: document.getElementById("password"),
        confirmPassword: document.getElementById("confirm-password"),
    };

    if (!els.form || !els.signupBlock) return;

    // ===== Console overlay (optionnel)
    function runConsoleIntro() {
        if (!els.overlay || !els.consoleText) {
            els.signupBlock.style.display = "block";
            return;
        }

        const lines = [
            "Initialisation du système...",
            "Chargement des modules...",
            "Connexion à Esportify établie ✔",
            "Chargement de l'interface..."
        ];

        let i = 0;

        const typeLine = () => {
            if (i < lines.length) {
                els.consoleText.textContent += `${lines[i]}\n`;
                i += 1;
                window.setTimeout(typeLine, 650);
                return;
            }

            window.setTimeout(() => {
                els.overlay.style.display = "none";
                els.signupBlock.style.display = "block";
            }, 700);
        };

        typeLine();
    }

    // ===== Popup
    function openPopup() {
        if (!els.popup) return;
        els.popup.style.display = "flex";
    }

    function closePopup() {
        if (!els.popup) return;
        els.popup.style.display = "none";
        window.location.href = "/";
    }

    if (els.closePopupBtn) els.closePopupBtn.addEventListener("click", closePopup);

    if (els.popup) {
        els.popup.addEventListener("click", (e) => {
            if (e.target === els.popup) closePopup();
        });
    }

    window.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && els.popup && els.popup.style.display === "flex") {
            closePopup();
        }
    });

    // ===== Validation & submit
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;

    function setError(msg) {
        if (!els.errorMsg) return;
        els.errorMsg.textContent = msg || "";
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setError("");

        const email = (els.email?.value || "").trim();
        const pseudo = (els.pseudo?.value || "").trim();
        const password = (els.password?.value || "").trim();
        const confirmPassword = (els.confirmPassword?.value || "").trim();

        if (!email || !pseudo || !password || !confirmPassword) {
            setError("❌ Merci de remplir tous les champs.");
            return;
        }

        if (password !== confirmPassword) {
            setError("❌ Les mots de passe ne sont pas identiques.");
            return;
        }

        if (!passwordRegex.test(password)) {
            setError("❌ Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.");
            return;
        }

        try {
            const response = await fetch("/backend/signup.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
                body: new URLSearchParams({
                    email,
                    username: pseudo,
                    mot_de_passe: password,
                    confirmer_mot_de_passe: confirmPassword,
                }),
            });

            const result = await response.text();

            if ((result || "").toLowerCase().includes("success")) {
                openPopup();
            } else {
                setError(result || "❌ Inscription impossible. Veuillez réessayer.");
            }
        } catch (err) {
            console.error(err);
            setError("❌ Une erreur est survenue. Veuillez réessayer.");
        }
    }

    els.form.addEventListener("submit", handleSubmit);

    runConsoleIntro();
})();