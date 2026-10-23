// La altura cambia al abrir el menú móvil o distribuir los enlaces en dos filas.
const cabecera = document.querySelector(".site-header");
if (cabecera) {
    const ajustarCabecera = () => document.documentElement.style.setProperty(
        "--header-height", `${cabecera.getBoundingClientRect().height}px`
    );
    ajustarCabecera();
    if ("ResizeObserver" in window) new ResizeObserver(ajustarCabecera).observe(cabecera);
    else window.addEventListener("resize", ajustarCabecera);
}

document.querySelectorAll("[data-aviso-exito]").forEach((aviso) => {
    const cerrar = aviso.querySelector(".alert-close");
    let temporizador;
    const pausar = () => window.clearTimeout(temporizador);
    const programar = () => {
        pausar();
        if (!aviso.matches(":hover") && !aviso.contains(document.activeElement)) {
            temporizador = window.setTimeout(() => aviso.remove(), 6000);
        }
    };
    cerrar.hidden = false;
    cerrar.addEventListener("click", () => {
        pausar();
        aviso.remove();
    });
    aviso.addEventListener("mouseenter", pausar);
    aviso.addEventListener("mouseleave", programar);
    aviso.addEventListener("focusin", pausar);
    aviso.addEventListener("focusout", (event) => {
        if (!aviso.contains(event.relatedTarget)) {
            // El navegador actualiza el foco después de este evento.
            window.setTimeout(programar, 0);
        }
    });
    programar();
});

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

// Formularios GET del catálogo: omiten los campos vacíos para que la URL
// y la paginación solo lleven los filtros aplicados.
document.querySelectorAll("form[data-sin-vacios]").forEach((form) => {
    form.addEventListener("submit", (event) => {
        if (event.defaultPrevented) return;
        event.preventDefault();
        const params = new URLSearchParams();
        for (const [name, value] of new FormData(form, event.submitter)) {
            if (typeof value === "string" && value.trim() !== "") {
                params.append(name, value);
            }
        }
        const query = params.toString();
        window.location.assign(form.action + (query ? `?${query}` : ""));
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

// Contar las fotos conservadas junto a las nuevas, también al reemplazarlas.
document.querySelectorAll("[data-fotos-subasta]").forEach((input) => {
    const form = input.form;
    const eliminaciones = [...form.querySelectorAll('input[name="eliminar_imagenes[]"]')];
    const aviso = form.querySelector("#photo-error");
    const actualizarFotos = (mostrarAviso = true) => {
        const conservadas = eliminaciones.filter((checkbox) => !checkbox.checked).length;
        const total = conservadas + input.files.length;
        input.required = conservadas === 0;
        const mensaje = total < 1 ? "Debes subir al menos una foto del artículo."
            : total > 5 ? "La subasta puede tener como máximo 5 fotos en total."
            : "";
        // El otro control de vista previa valida el formato y el tamaño.
        const formatoInvalido = [...input.files].some((file) => file.size > 5 * 1024 * 1024 || !["image/jpeg", "image/png", "image/webp"].includes(file.type));
        if (!formatoInvalido) input.setCustomValidity(mensaje);
        if (mostrarAviso) aviso.textContent = formatoInvalido ? input.validationMessage : mensaje;
    };
    input.addEventListener("change", actualizarFotos);
    eliminaciones.forEach((checkbox) => checkbox.addEventListener("change", actualizarFotos));
    input.addEventListener("invalid", () => { aviso.textContent = input.validationMessage; });
    actualizarFotos(false);
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

// Los campos representan hora de Bolivia, aunque el navegador use otra zona.
const formatBoliviaDateTime = (date) => {
    const parts = Object.fromEntries(new Intl.DateTimeFormat("en-CA", {
        timeZone: "America/La_Paz", year: "numeric", month: "2-digit", day: "2-digit",
        hour: "2-digit", minute: "2-digit", hourCycle: "h23",
    }).formatToParts(date).map(({ type, value }) => [type, value]));
    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
};

document.querySelectorAll("[data-cierre-subasta]").forEach((input) => {
    const openedAt = performance.now();
    const serverNow = Number(input.dataset.serverNow);
    const error = document.getElementById("deadline-error");
    let touched = false;
    const update = (showError = touched) => {
        // El mínimo avanza también si se deja el formulario abierto.
        const now = serverNow + performance.now() - openedAt;
        input.min = formatBoliviaDateTime(new Date((Math.floor(now / 60000) + 1) * 60000));
        const message = input.value && input.value < input.min
            ? "La fecha y hora de cierre deben ser posteriores al momento actual (hora de Bolivia)." : "";
        input.setCustomValidity(message);
        input.setAttribute("aria-invalid", String(Boolean(message)));
        if (showError) error.textContent = message;
    };
    input.addEventListener("input", () => { touched = true; update(true); });
    input.addEventListener("focus", () => update());
    input.addEventListener("invalid", () => { touched = true; update(true); });
    input.form.addEventListener("submit", (event) => {
        update(true);
        if (!input.checkValidity()) {
            event.preventDefault();
            input.reportValidity();
        }
    });
    window.addEventListener("pageshow", () => update());
    window.setInterval(() => update(), 1000);
    update();
});

document.querySelectorAll("[data-duration-presets]").forEach((container) => {
    const targetInput = document.getElementById(container.dataset.target);
    if (!targetInput) return;

    const buttons = container.querySelectorAll("[data-days]");

    buttons.forEach((button) => {
        button.addEventListener("click", () => {
            const days = parseInt(button.dataset.days, 10);
            if (isNaN(days)) return;

            const targetDate = new Date(Date.now() + (days * 86400000));

            targetInput.value = formatBoliviaDateTime(targetDate);
            targetInput.dispatchEvent(new Event("input", { bubbles: true }));
            targetInput.dispatchEvent(new Event("change", { bubbles: true }));

            buttons.forEach((btn) => btn.classList.toggle("is-active", btn === button));
        });
    });

    targetInput.addEventListener("input", () => {
        buttons.forEach((btn) => btn.classList.remove("is-active"));
    });
});
