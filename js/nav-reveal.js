// ========================================
// Efecto de scroll de la portada.
//
// Al bajar: "ARHAUS" se desvanece subiendo y encogiéndose un poco,
// las fotos del hero acompañan con un parallax suave, y pasada la
// mitad del recorrido aparece la barra de arriba con el logo y el
// menú (Home / Proyectos / Estudio / Contacto / Login).
// ========================================

(function () {
	var nav = document.querySelector('nav');
	var hero = document.querySelector('.hero-carousel');
	var marca = document.querySelector('.hero-brand');
	var fotos = document.querySelector('.carousel-container');

	if (!nav) return;

	// Sin hero (páginas internas) la barra va sólida siempre: nada que hacer.
	if (!hero) return;

	var pendiente = false;

	function limitar(valor, minimo, maximo) {
		return Math.min(Math.max(valor, minimo), maximo);
	}

	function pintar() {
		pendiente = false;

		var y = window.scrollY || window.pageYOffset || 0;
		// El efecto se completa en el 65% de la altura del hero.
		var recorrido = Math.max(hero.offsetHeight * 0.65, 1);
		var avance = limitar(y / recorrido, 0, 1);

		// Suavizado (ease-out): arranca más rápido y frena al final.
		var suave = 1 - Math.pow(1 - avance, 2);

		if (marca) {
			marca.style.opacity = String(1 - suave);
			marca.style.transform =
				'translate(-50%, calc(-50% - ' + (suave * 90).toFixed(1) + 'px)) ' +
				'scale(' + (1 - suave * 0.08).toFixed(3) + ')';
		}

		// Parallax: las fotos bajan más despacio que la página.
		if (fotos) {
			fotos.style.transform = 'translateY(' + (avance * 12).toFixed(1) + '%)';
		}

		// Pasada la mitad, aparece la barra con el menú.
		nav.classList.toggle('nav--scrolled', avance > 0.5);
	}

	function alScrollear() {
		if (!pendiente) {
			pendiente = true;
			requestAnimationFrame(pintar);
		}
	}

	window.addEventListener('scroll', alScrollear, { passive: true });
	window.addEventListener('resize', alScrollear);
	pintar();
})();
