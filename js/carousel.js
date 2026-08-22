// ========================================
// HERO: las fotos se cruzan solas.
// Sin flechas ni puntos: el hero rota automáticamente.
// ========================================

document.addEventListener('DOMContentLoaded', function () {
    var hero = document.querySelector('.hero-carousel');
    var slides = document.querySelectorAll('.carousel-slide');

    if (!hero || slides.length === 0) return;

    // Entrada de "ARHAUS" (las piezas del logo aparecen escalonadas).
    var marca = hero.querySelector('.marca');

    function habilitarHover() {
        // La animacion de entrada usa "forwards" y deja clavados transform
        // y opacity. Al marcarla como terminada, el CSS la desactiva y el
        // hover puede volver a mover las letras.
        if (marca) marca.classList.add('entrada-lista');
    }

    function arrancarMarca() {
        hero.classList.add('is-listo');

        if (!marca) return;
        var ultima = marca.querySelector('.marca__tagline');
        var listo = false;
        function unaVez() {
            if (listo) return;
            listo = true;
            habilitarHover();
        }
        if (ultima) {
            ultima.addEventListener('animationend', unaVez, { once: true });
        }
        // Red de seguridad: si la animacion no corre (pestana en segundo
        // plano, por ejemplo), habilitar el hover igual.
        setTimeout(unaVez, 2600);
    }

    var preloader = document.getElementById('preloader');

    if (preloader && !preloader.classList.contains('is-oculto')) {
        // Hay pantalla de carga: esperar a que se vaya, si no la
        // animacion se reproduciria tapada y no se veria.
        document.addEventListener('arhaus:loader-oculto', function () {
            setTimeout(arrancarMarca, 250);
        }, { once: true });
        // Red de seguridad por si el aviso nunca llega.
        setTimeout(arrancarMarca, 5000);
    } else {
        // Sin pantalla de carga: dos frames de espera para que la
        // animacion de CSS se vea (si se aplica en el mismo tick, no).
        requestAnimationFrame(function () {
            requestAnimationFrame(arrancarMarca);
        });
    }

    if (slides.length === 1) return; // una sola foto: nada que rotar

    var actual = 0;
    var DURACION = 6500; // ms que se queda cada foto
    var intervalo = null;

    function mostrarSiguiente() {
        slides[actual].classList.remove('active');
        actual = (actual + 1) % slides.length;
        slides[actual].classList.add('active');
    }

    function arrancar() {
        if (intervalo === null) {
            intervalo = setInterval(mostrarSiguiente, DURACION);
        }
    }

    function parar() {
        clearInterval(intervalo);
        intervalo = null;
    }

    arrancar();

    // Si la pestaña pasa a segundo plano, pausar (no acumular saltos al volver).
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            parar();
        } else {
            arrancar();
        }
    });
});
