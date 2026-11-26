// ---------- Report / Add form (index.html and admin.html) ----------
const reportBtn = document.querySelector(".reportBtn");
const bookFormContainer = document.querySelector(".bookFormContainer");

if (reportBtn && bookFormContainer) {
    const closeForm = bookFormContainer.querySelector(".closeBtn");

    reportBtn.addEventListener("click", () => {
        bookFormContainer.classList.remove("displayNone");
    });

    closeForm.addEventListener("click", () => {
        bookFormContainer.classList.add("displayNone");
    });
}

// ---------- Book Now confirmation (index.html only) ----------
const confirmContainer = document.querySelector(".confirmContainer");

if (confirmContainer) {
    const bookBtns = document.querySelectorAll(".bookBtn");
    const confirmInput = confirmContainer.querySelector("#confirm");
    const cancel = confirmContainer.querySelector(".cancel");
    const closeConfirm = confirmContainer.querySelector(".closeBtn");

    const hideConfirm = () => {
        confirmContainer.classList.add("displayNone");
    };

    bookBtns.forEach(btn => {
        btn.addEventListener("click", event => {
            const venue = event.target
                .closest(".venueContainer")
                .querySelector(".venue")
                .textContent.trim();

            confirmInput.value = venue;
            confirmContainer.classList.remove("displayNone");
        });
    });

    closeConfirm.addEventListener("click", hideConfirm);
    cancel.addEventListener("click", hideConfirm);
}

// ---------- Footer year ----------
const currentYear = document.querySelector("#currentYear");
if (currentYear) {
    currentYear.textContent = new Date().getFullYear();
}
