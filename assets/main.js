// ---------- Report / Add form (index.php and admin.php) ----------
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

// ---------- Book Now confirmation (index.php only) ----------
const confirmContainer = document.querySelector(".confirmContainer");

if (confirmContainer) {
    const bookBtns = document.querySelectorAll(".bookNow");
    const confirmInput = confirmContainer.querySelector("#confirm");
    const confirmReportId = confirmContainer.querySelector("#confirmReportId");
    const cancel = confirmContainer.querySelector(".cancel");
    const closeConfirm = confirmContainer.querySelector(".closeBtn");

    const hideConfirm = () => {
        confirmContainer.classList.add("displayNone");
    };

    bookBtns.forEach(btn => {
        btn.addEventListener("click", event => {
            const card = event.target.closest(".venueContainer");

            confirmInput.value = card.querySelector(".venue").textContent.trim();
            confirmReportId.value = card.dataset.reportId;
            confirmContainer.classList.remove("displayNone");
        });
    });

    closeConfirm.addEventListener("click", hideConfirm);
    cancel.addEventListener("click", hideConfirm);
}

// ---------- Venue search (index.php report form) ----------
// Suggests official venues from assets/venues.json. Read-only: never touches the database.
const venueInput = document.querySelector("#venue");
const venueList = document.querySelector("#venueSuggestions");

if (venueInput && venueList) {
    let venues = [];
    let matches = [];
    let active = -1;

    // "LWGF (A) - BL15" -> "lwgfab15", "Block 02" -> "b2"
    const normalize = text => text
        .toLowerCase()
        .replace(/\b(block|bl)\s*(?=\d)/g, "b")
        .replace(/(^|\D)0+(?=\d)/g, "$1")
        .replace(/[^a-z0-9]/g, "");

    fetch("assets/venues.json")
        .then(res => res.json())
        .then(data => {
            venues = data.map(v => ({
                ...v,
                key: normalize(`${v.name} ${v.block ?? ""}`),
                blockKey: normalize(v.block ?? ""),
                words: normalize(`${v.name} ${v.block ?? ""} ${v.type}`)
            }));
        })
        .catch(() => { /* list unavailable: the server still checks the name */ });

    const isOfficial = name => venues.some(v => v.name === name);

    const close = () => {
        venueList.hidden = true;
        venueInput.setAttribute("aria-expanded", "false");
        active = -1;
    };

    const choose = venue => {
        venueInput.value = venue.name;
        venueInput.setCustomValidity("");
        close();
    };

    const render = () => {
        venueList.replaceChildren();
        if (matches.length === 0) {
            const li = document.createElement("li");
            li.className = "noMatch";
            li.textContent = "No venue found. Try the block, e.g. B 15";
            venueList.append(li);
        }
        matches.forEach((v, i) => {
            const li = document.createElement("li");
            li.setAttribute("role", "option");
            li.className = i === active ? "active" : "";
            const name = document.createElement("strong");
            name.textContent = v.name;
            const info = document.createElement("small");
            info.textContent = [v.block, v.type, v.capacity ? `${v.capacity} seats` : null]
                .filter(Boolean).join(" · ");
            li.append(name, info);
            li.addEventListener("mousedown", e => { e.preventDefault(); choose(v); });
            venueList.append(li);
        });
        venueList.hidden = false;
        venueInput.setAttribute("aria-expanded", "true");
    };

    venueInput.addEventListener("input", () => {
        const query = venueInput.value.trim();
        venueInput.setCustomValidity("");
        if (query.length < 1 || venues.length === 0) { close(); return; }

        const whole = normalize(query);
        const tokens = query.split(/\s+/).map(normalize).filter(Boolean);

        // Exact block first ("b2" -> Block 2, not Block 20), then closest name matches.
        const score = v => (v.blockKey === whole ? 2 : 0) + (v.key.includes(whole) ? 1 : 0);

        matches = venues
            .filter(v => v.key.includes(whole) || tokens.every(t => v.words.includes(t)))
            .sort((a, b) => (score(b) - score(a)) || a.name.localeCompare(b.name))
            .slice(0, 12);
        active = -1;
        render();
    });

    venueInput.addEventListener("keydown", e => {
        if (venueList.hidden) return;
        if (e.key === "ArrowDown") { active = Math.min(active + 1, matches.length - 1); render(); e.preventDefault(); }
        else if (e.key === "ArrowUp") { active = Math.max(active - 1, 0); render(); e.preventDefault(); }
        else if (e.key === "Enter" && active >= 0) { choose(matches[active]); e.preventDefault(); }
        else if (e.key === "Escape") { close(); }
    });

    venueInput.addEventListener("blur", close);

    // Block the form if the typed name isn't an official venue.
    venueInput.form.addEventListener("submit", e => {
        if (venues.length && !isOfficial(venueInput.value.trim())) {
            venueInput.setCustomValidity("Choose a venue from the list.");
            venueInput.reportValidity();
            e.preventDefault();
        }
    });
}

// ---------- Footer year ----------
const currentYear = document.querySelector("#currentYear");
if (currentYear) {
    currentYear.textContent = new Date().getFullYear();
}
