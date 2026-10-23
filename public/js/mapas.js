// Mapas Leaflet de SubastaYA. Si Leaflet o las teselas no cargan, cada mapa
// muestra su aviso y la página sigue funcionando con la ubicación textual.
const MAPA = {
    teselas: "https://tile.openstreetmap.org/{z}/{x}/{y}.png",
    atribucion:
        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    centro: [-16.5, -68.15],
};

const marcarIndisponible = (contenedor) => {
    contenedor.classList.add("is-unavailable");
    contenedor.querySelector("[data-mapa-aviso]")?.removeAttribute("hidden");
};

const crearMapa = (contenedor, opciones = {}) => {
    if (typeof window.L === "undefined") {
        marcarIndisponible(contenedor);
        return null;
    }
    const mapa = L.map(contenedor.querySelector("[data-mapa-lienzo]"), {
        scrollWheelZoom: false,
        worldCopyJump: true,
        ...opciones,
    });
    const capa = L.tileLayer(MAPA.teselas, {
        maxZoom: 19,
        attribution: MAPA.atribucion,
    }).addTo(mapa);
    let cargadas = 0;
    capa.on("tileload", () => cargadas++);
    // "load" llega cuando terminan las teselas visibles, también las fallidas.
    capa.on("load", () => {
        if (cargadas === 0) marcarIndisponible(contenedor);
    });

    return mapa;
};

const coordenadaValida = (lat, lng) =>
    Number.isFinite(lat) &&
    Number.isFinite(lng) &&
    Math.abs(lat) <= 90 &&
    Math.abs(lng) <= 180;

const leerPunto = (latInput, lngInput) => {
    if (latInput.value === "" || lngInput.value === "") return null;
    const punto = [Number(latInput.value), Number(lngInput.value)];

    return coordenadaValida(...punto) ? punto : null;
};

// Formulario de publicación: el marcador, el GPS y los campos ocultos comparten estado.
document.querySelectorAll("[data-mapa-selector]").forEach((contenedor) => {
    const latInput = document.getElementById(contenedor.dataset.lat);
    const lngInput = document.getElementById(contenedor.dataset.lng);
    const estado = document.getElementById(contenedor.dataset.estado);
    const gpsBtn = document.getElementById("btn-geolocalizacion");
    const centroBtn = contenedor.querySelector("[data-mapa-centro]");
    const inicial = leerPunto(latInput, lngInput);
    const mapa = crearMapa(contenedor);
    let marcador = null;

    const mostrarEstado = () => {
        estado.replaceChildren();
        if (latInput.value === "" || lngInput.value === "") return;
        const badge = document.createElement("span");
        badge.className = "gps-coords-badge";
        badge.innerHTML =
            '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>';
        badge.append(
            ` Coordenadas registradas: ${latInput.value}, ${lngInput.value} `,
        );
        const quitarBtn = document.createElement("button");
        quitarBtn.type = "button";
        quitarBtn.className = "btn-clear-gps";
        quitarBtn.id = "btn-quitar-gps";
        quitarBtn.title = "Quitar coordenadas";
        quitarBtn.setAttribute("aria-label", "Quitar coordenadas");
        quitarBtn.textContent = "✕";
        quitarBtn.addEventListener("click", quitar);
        badge.append(quitarBtn);
        estado.append(badge);
    };

    const fijar = (lat, lng, centrar = false) => {
        latInput.value = lat.toFixed(6);
        lngInput.value = lng.toFixed(6);
        if (mapa) {
            if (!marcador) {
                marcador = L.marker([lat, lng], {
                    draggable: true,
                    title: "Punto de encuentro",
                    alt: "Marcador del punto de encuentro",
                }).addTo(mapa);
                marcador.on("dragend", () => {
                    const punto = marcador.getLatLng().wrap();
                    fijar(punto.lat, punto.lng);
                });
            } else {
                marcador.setLatLng([lat, lng]);
            }
            if (centrar) mapa.setView([lat, lng], Math.max(mapa.getZoom(), 15));
        }
        mostrarEstado();
    };

    function quitar() {
        latInput.value = "";
        lngInput.value = "";
        marcador?.remove();
        marcador = null;
        mostrarEstado();
    }

    document.getElementById("btn-quitar-gps")?.addEventListener("click", quitar);

    if (mapa) {
        mapa.setView(inicial ?? MAPA.centro, inicial ? 15 : 12);
        if (inicial) fijar(...inicial);
        mapa.on("click", (evento) => {
            const punto = evento.latlng.wrap();
            fijar(punto.lat, punto.lng);
        });
        centroBtn.hidden = false;
        centroBtn.addEventListener("click", () => {
            const punto = mapa.getCenter().wrap();
            fijar(punto.lat, punto.lng);
        });
    }

    gpsBtn?.addEventListener("click", () => {
        if (!navigator.geolocation) {
            alert("Tu navegador no soporta geolocalización.");
            return;
        }
        const originalHtml = gpsBtn.innerHTML;
        gpsBtn.disabled = true;
        gpsBtn.innerHTML = "<span>Buscando GPS...</span>";
        navigator.geolocation.getCurrentPosition(
            (position) => {
                gpsBtn.disabled = false;
                gpsBtn.innerHTML = originalHtml;
                fijar(position.coords.latitude, position.coords.longitude, true);
            },
            (error) => {
                gpsBtn.disabled = false;
                gpsBtn.innerHTML = originalHtml;
                let message = "No se pudo obtener la ubicación.";
                if (error.code === error.PERMISSION_DENIED) {
                    message = "Permiso de geolocalización denegado en el navegador.";
                } else if (error.code === error.POSITION_UNAVAILABLE) {
                    message = "La información de ubicación no está disponible.";
                } else if (error.code === error.TIMEOUT) {
                    message = "Se agotó el tiempo de espera para obtener la ubicación.";
                }
                alert(message);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
        );
    });
});
