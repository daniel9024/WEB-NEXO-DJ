/* =========================================================
   MENÚ MÓVIL
   ========================================================= */

const navbar = document.getElementById("navbar");
const menuToggle = document.getElementById("menuToggle");
const mainNav = document.getElementById("mainNav");

function setMenu(open) {
    mainNav.classList.toggle("active", open);
    navbar.classList.toggle("menu-open", open);
    menuToggle.setAttribute("aria-expanded", String(open));
    menuToggle.setAttribute("aria-label", open ? "Cerrar menú" : "Abrir menú");
}

menuToggle.addEventListener("click", function () {
    setMenu(!mainNav.classList.contains("active"));
});

mainNav.querySelectorAll("a").forEach(function (link) {
    link.addEventListener("click", function () {
        setMenu(false);
    });
});

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
        setMenu(false);
    }
});


/* =========================================================
   DESPLAZAMIENTO A CADA SECCIÓN
   Lleva el título de la sección justo debajo del menú,
   saltando el espacio superior (padding) de cada sección.
   ========================================================= */

const SCROLL_GAP = 24;

function scrollToSection(section) {
    if (section.id === "inicio") {
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
    }

    const paddingTop = parseFloat(getComputedStyle(section).paddingTop) || 0;
    const sectionTop = section.getBoundingClientRect().top + window.scrollY;
    const top = sectionTop + paddingTop - navbar.offsetHeight - SCROLL_GAP;

    window.scrollTo({ top: Math.max(top, 0), behavior: "smooth" });
}

document.querySelectorAll('a[href^="#"]').forEach(function (link) {
    link.addEventListener("click", function (event) {
        const id = link.getAttribute("href").slice(1);
        const section = document.getElementById(id);

        if (!section || id === "main") return;

        event.preventDefault();
        scrollToSection(section);
        history.pushState(null, "", "#" + id);
    });
});


/* =========================================================
   NAVBAR CON FONDO AL HACER SCROLL
   ========================================================= */

function updateNavbar() {
    navbar.classList.toggle("scrolled", window.scrollY > 20);
}

updateNavbar();
window.addEventListener("scroll", updateNavbar, { passive: true });


/* =========================================================
   LINK ACTIVO SEGÚN LA SECCIÓN VISIBLE
   ========================================================= */

const navLinks = mainNav.querySelectorAll('a[href^="#"]:not(.nav-button)');

const sectionObserver = new IntersectionObserver(
    function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;

            navLinks.forEach(function (link) {
                link.classList.toggle(
                    "active",
                    link.getAttribute("href") === "#" + entry.target.id
                );
            });
        });
    },
    { rootMargin: "-45% 0px -50% 0px" }
);

document.querySelectorAll("main section[id]").forEach(function (section) {
    sectionObserver.observe(section);
});


/* =========================================================
   ANIMACIÓN SUAVE AL HACER SCROLL
   ========================================================= */

const revealObserver = new IntersectionObserver(
    function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add("visible");
                revealObserver.unobserve(entry.target);
            }
        });
    },
    { threshold: 0.12 }
);

document.querySelectorAll(".reveal").forEach(function (element) {
    revealObserver.observe(element);
});


const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;


/* =========================================================
   COMPARACIÓN DE VELOCIDAD (ilustración)
   ========================================================= */

const race = document.getElementById("race");
const slowBar = document.getElementById("slowBar");
const slowTime = document.getElementById("slowTime");
const slowStatus = document.getElementById("slowStatus");
const fastBar = document.getElementById("fastBar");
const fastDone = document.getElementById("fastDone");

// Tiempo simulado de espera del servidor (en segundos) al terminar la animación
const SLOW_FINAL_SECONDS = 2 * 3600 + 17 * 60 + 42;
const SLOW_DURATION_MS = 9000;

function formatClock(totalSeconds) {
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = Math.floor(totalSeconds % 60);
    return [h, m, s].map(function (n) {
        return String(n).padStart(2, "0");
    }).join(":");
}

function showRaceFinal() {
    fastBar.style.transform = "scaleX(1)";
    fastDone.classList.add("is-shown");
    slowBar.style.transform = "scaleX(0.22)";
    slowTime.textContent = formatClock(SLOW_FINAL_SECONDS);
    slowStatus.textContent = "Esperando respuesta del servidor…";
}

function runRace() {
    fastBar.style.transform = "scaleX(1)";
    setTimeout(function () {
        fastDone.classList.add("is-shown");
    }, 900);

    slowBar.style.transform = "scaleX(0.22)";

    const start = performance.now();

    function tick(now) {
        const progress = Math.min((now - start) / SLOW_DURATION_MS, 1);
        slowTime.textContent = formatClock(progress * SLOW_FINAL_SECONDS);

        if (progress > 0.66) {
            slowStatus.textContent = "Esperando respuesta del servidor…";
        } else if (progress > 0.25) {
            slowStatus.textContent = "Procesando…";
        }

        if (progress < 1) {
            requestAnimationFrame(tick);
        }
    }

    requestAnimationFrame(tick);
}

if (race) {
    if (reducedMotion) {
        showRaceFinal();
    } else {
        const raceObserver = new IntersectionObserver(
            function (entries) {
                if (entries[0].isIntersecting) {
                    raceObserver.disconnect();
                    setTimeout(runRace, 400);
                }
            },
            { threshold: 0.2 }
        );

        raceObserver.observe(race);
    }
}


/* =========================================================
   AÑO ACTUAL EN EL FOOTER
   ========================================================= */

document.getElementById("year").textContent = new Date().getFullYear();


/* =========================================================
   FORMULARIO DE SOLICITUD DE ACCESO
   Envía los datos a enviar.php sin recargar la página.
   ========================================================= */

const formAcceso = document.getElementById("formAcceso");

if (formAcceso) {
    const estado = document.getElementById("formEstado");
    const boton = formAcceso.querySelector('button[type="submit"]');
    const ERROR_GENERAL = "No pudimos enviar tu solicitud. Escríbenos a contacto@chiledj.cl.";

    function mostrarEstado(texto, tipo) {
        estado.textContent = texto;
        estado.className = "form-status" + (tipo ? " is-" + tipo : "");
    }

    // Mismas reglas que enviar.php. Cada una devuelve el error o "" si está bien.
    const reglas = {
        nombre: function (v) {
            if (v.length < 3 || v.length > 80) return "Escribe tu nombre y apellido (3 a 80 caracteres).";
            if (!/^[\p{L}'. -]+$/u.test(v)) return "El nombre solo puede tener letras y espacios.";
            if (v.split(/\s+/).length < 2) return "Escribe tu nombre y tu apellido.";
            return "";
        },
        correo: function (v) {
            return v.length <= 120 && /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(v)
                ? "" : "Escribe un correo válido, por ejemplo nombre@empresa.cl.";
        },
        telefono: function (v) {
            const digitos = v.replace(/\D/g, "");
            return v === "" || digitos.length === 9 || (digitos.length === 11 && digitos.startsWith("56"))
                ? "" : "El teléfono debe tener 9 dígitos, por ejemplo +56 9 1234 5678.";
        },
        perfil: function (v) {
            return v ? "" : "Selecciona tu perfil.";
        },
    };

    function validarCampo(campo) {
        const regla = reglas[campo.name];
        const error = regla ? regla(campo.value.trim()) : "";
        campo.setCustomValidity(error);
        if (campo.name === "acepta" && !campo.checked) {
            campo.setCustomValidity("Debes aceptar que te contactemos.");
        }
        if (campo.checkValidity()) {
            campo.removeAttribute("aria-invalid");
        } else {
            campo.setAttribute("aria-invalid", "true");
        }
        return campo.validationMessage;
    }

    // Teléfono: solo números, espacios y "+"
    formAcceso.telefono.addEventListener("input", function () {
        this.value = this.value.replace(/[^0-9+ ]/g, "");
    });

    // Contador del mensaje
    const contador = document.getElementById("mensajeContador");
    formAcceso.mensaje.addEventListener("input", function () {
        contador.textContent = this.value.length + " / " + this.maxLength;
    });

    // Quita la marca de error apenas la persona corrige el campo
    formAcceso.addEventListener("input", function (event) {
        if (event.target.hasAttribute("aria-invalid")) validarCampo(event.target);
    });

    formAcceso.addEventListener("submit", async function (event) {
        event.preventDefault();

        // Valida todo y muestra el error del primer campo con problemas
        let primero = null;
        let mensaje = "";
        Array.from(formAcceso.elements).forEach(function (campo) {
            if (!campo.name || campo.name === "sitio_web") return;
            const error = validarCampo(campo);
            if (error && !primero) {
                primero = campo;
                mensaje = error;
            }
        });

        if (primero) {
            primero.focus();
            mostrarEstado(mensaje, "error");
            return;
        }

        boton.disabled = true;
        mostrarEstado("Enviando…");

        try {
            const respuesta = await fetch(formAcceso.action, {
                method: "POST",
                body: new FormData(formAcceso),
            });
            const datos = await respuesta.json().catch(function () {
                return { ok: false, mensaje: ERROR_GENERAL };
            });

            if (datos.ok) {
                formAcceso.reset();
                contador.textContent = "0 / " + formAcceso.mensaje.maxLength;
                mostrarEstado(datos.mensaje, "ok");
            } else {
                mostrarEstado(datos.mensaje || ERROR_GENERAL, "error");
            }
        } catch (error) {
            mostrarEstado(ERROR_GENERAL, "error");
        } finally {
            boton.disabled = false;
        }
    });
}
