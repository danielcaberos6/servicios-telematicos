document.querySelector(".menu-toggle")?.addEventListener("click", function () {
    const expanded = this.getAttribute("aria-expanded") === "true";
    this.setAttribute("aria-expanded", String(!expanded));
    document
        .querySelector("#navigation")
        .classList.toggle("is-open", !expanded);
});

document.querySelectorAll("form[data-confirm]").forEach((form) => {
    form.addEventListener("submit", (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});

document.querySelectorAll("[data-photo]").forEach((button) => {
    button.addEventListener("click", () => {
        document.querySelector("#main-photo").src = button.dataset.photo;
        document
            .querySelectorAll("[data-photo]")
            .forEach((item) =>
                item.classList.toggle("selected", item === button),
            );
    });
});

document.querySelectorAll("input[data-preview]").forEach((input) => {
    let urls = [];
    input.addEventListener("change", () => {
        urls.forEach(URL.revokeObjectURL);
        urls = [];
        const target = document.getElementById(input.dataset.preview);
        target.replaceChildren();
        const files = [...input.files];
        if (
            files.length > 5 ||
            files.some(
                (file) =>
                    file.size > 5 * 1024 * 1024 ||
                    !["image/jpeg", "image/png", "image/webp"].includes(
                        file.type,
                    ),
            )
        ) {
            input.setCustomValidity(
                "Selecciona hasta 5 fotos JPG, PNG o WebP de máximo 5 MB cada una.",
            );
            input.reportValidity();
            return;
        }
        input.setCustomValidity("");
        files.forEach((file) => {
            const url = URL.createObjectURL(file);
            urls.push(url);
            const image = document.createElement("img");
            image.src = url;
            image.alt = `Vista previa de ${file.name}`;
            target.append(image);
        });
    });

    const dropArea = input.closest(".upload-area");
    if (dropArea) {
        let dragCounter = 0;
        ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
            dropArea.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        dropArea.addEventListener("dragenter", () => {
            dragCounter++;
            dropArea.classList.add("is-dragover");
        });

        dropArea.addEventListener("dragleave", () => {
            dragCounter--;
            if (dragCounter <= 0) {
                dragCounter = 0;
                dropArea.classList.remove("is-dragover");
            }
        });

        dropArea.addEventListener("drop", (e) => {
            dragCounter = 0;
            dropArea.classList.remove("is-dragover");
            const droppedFiles = e.dataTransfer?.files;
            if (droppedFiles && droppedFiles.length > 0) {
                try {
                    const dt = new DataTransfer();
                    for (let i = 0; i < droppedFiles.length; i++) {
                        dt.items.add(droppedFiles[i]);
                    }
                    input.files = dt.files;
                } catch {
                    input.files = droppedFiles;
                }
                input.dispatchEvent(new Event("change", { bubbles: true }));
            }
        });
    }
});

document.querySelectorAll("[data-carrusel]").forEach((carrusel) => {
    const pista = carrusel.querySelector("[data-carrusel-pista]");
    const anterior = carrusel.querySelector("[data-carrusel-anterior]");
    const siguiente = carrusel.querySelector("[data-carrusel-siguiente]");
    const actualizar = () => {
        anterior.disabled = pista.scrollLeft <= 4;
        siguiente.disabled =
            pista.scrollLeft + pista.clientWidth >= pista.scrollWidth - 4;
    };
    anterior.addEventListener("click", () =>
        pista.scrollBy({ left: -pista.clientWidth }),
    );
    siguiente.addEventListener("click", () =>
        pista.scrollBy({ left: pista.clientWidth }),
    );
    pista.addEventListener("scroll", actualizar, { passive: true });
    window.addEventListener("resize", actualizar);
    actualizar();
});

document.querySelectorAll("[data-duration-presets]").forEach((container) => {
    const targetInput = document.getElementById(container.dataset.target);
    if (!targetInput) return;

    const buttons = container.querySelectorAll("[data-days]");
    const pad = (n) => String(n).padStart(2, "0");

    const formatLocalDateTime = (date) => {
        const y = date.getFullYear();
        const m = pad(date.getMonth() + 1);
        const d = pad(date.getDate());
        const h = pad(date.getHours());
        const min = pad(date.getMinutes());
        return `${y}-${m}-${d}T${h}:${min}`;
    };

    buttons.forEach((button) => {
        button.addEventListener("click", () => {
            const days = parseInt(button.dataset.days, 10);
            if (isNaN(days)) return;

            const targetDate = new Date();
            targetDate.setDate(targetDate.getDate() + days);

            targetInput.value = formatLocalDateTime(targetDate);
            targetInput.dispatchEvent(new Event("input", { bubbles: true }));
            targetInput.dispatchEvent(new Event("change", { bubbles: true }));

            buttons.forEach((btn) => btn.classList.toggle("is-active", btn === button));
        });
    });

    targetInput.addEventListener("input", () => {
        buttons.forEach((btn) => btn.classList.remove("is-active"));
    });
});
