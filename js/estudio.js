$(window).on("load", function () {
    var animacion = document.getElementById('titulo_animado');

    // #titulo_animado no existe en estudio.html: sin esta guarda, el
    // script cortaba con "Cannot read properties of null" en cada carga.
    if (!animacion) return;

    var posicionObjeto = animacion.getBoundingClientRect().top;
    var tamanoDePantalla = window.innerHeight / 1.5;

    if (posicionObjeto < tamanoDePantalla) {
        animacion.style.animation = 'aumentar 4s ease-out';
    }
});
