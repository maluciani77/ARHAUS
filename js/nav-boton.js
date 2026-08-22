// ========================================
// BOTON DEL MENU (arriba a la derecha)
// Chico y discreto; al tocarlo se despliegan los enlaces.
// ========================================

document.addEventListener('DOMContentLoaded', function () {
	var boton = document.querySelector('.nav__toggle');
	var panel = document.getElementById('menu-principal');
	if (!boton || !panel) return;

	var abierto = false;

	function abrir() {
		abierto = true;
		panel.hidden = false;
		// Forzar un reflow antes de agregar la clase: si se saca "hidden"
		// y se agrega la clase en el mismo tick, el navegador no anima la
		// transicion. (Se usa reflow y no requestAnimationFrame porque es
		// sincrono: funciona aunque la pestana no este dibujando frames.)
		void panel.offsetWidth;
		panel.classList.add('esta-abierto');
		boton.classList.add('esta-abierto');
		boton.setAttribute('aria-expanded', 'true');
		boton.setAttribute('aria-label', 'Cerrar menu');
	}

	function cerrar() {
		abierto = false;
		panel.classList.remove('esta-abierto');
		boton.classList.remove('esta-abierto');
		boton.setAttribute('aria-expanded', 'false');
		boton.setAttribute('aria-label', 'Abrir menu');

		// Recien ocultarlo del todo cuando termina de desvanecerse,
		// para no cortar la animacion.
		var listo = false;
		function ocultar() {
			if (listo || abierto) return;
			listo = true;
			panel.hidden = true;
		}
		panel.addEventListener('transitionend', ocultar, { once: true });
		setTimeout(ocultar, 500); // red de seguridad
	}

	boton.addEventListener('click', function (e) {
		e.stopPropagation();
		if (abierto) { cerrar(); } else { abrir(); }
	});

	// Cerrar al hacer clic afuera
	document.addEventListener('click', function (e) {
		if (abierto && !panel.contains(e.target) && e.target !== boton) {
			cerrar();
		}
	});

	// Cerrar con Escape
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && abierto) {
			cerrar();
			boton.focus();
		}
	});
});
