// ========================================
// VISOR DE FOTOS DE LA FICHA
// Tocar una foto de la galeria la abre a pantalla completa. Se pasa
// con las flechas (del teclado o de la pantalla) y se cierra con Esc,
// con la X o tocando afuera de la foto.
// ========================================

document.addEventListener('DOMContentLoaded', function () {
	var fotos = Array.prototype.slice.call(document.querySelectorAll('.ficha__foto'));
	var visor = document.querySelector('.visor');
	if (!fotos.length || !visor) return;

	var imagen = visor.querySelector('img');
	var cuenta = visor.querySelector('.visor__cuenta');
	var actual = 0;
	var quienAbrio = null;

	function mostrar(i) {
		actual = (i + fotos.length) % fotos.length;
		var foto = fotos[actual];
		imagen.src = foto.getAttribute('data-grande');
		imagen.alt = foto.querySelector('img').alt;
		cuenta.textContent = (actual + 1) + ' / ' + fotos.length;
	}

	function abrir(i) {
		quienAbrio = document.activeElement;
		mostrar(i);
		visor.classList.add('esta-abierto');
		document.body.style.overflow = 'hidden';
		visor.querySelector('.visor__cerrar').focus();
	}

	function cerrar() {
		visor.classList.remove('esta-abierto');
		document.body.style.overflow = '';
		imagen.removeAttribute('src');
		if (quienAbrio) quienAbrio.focus();
	}

	fotos.forEach(function (foto, i) {
		foto.addEventListener('click', function () { abrir(i); });
	});

	visor.querySelector('.visor__cerrar').addEventListener('click', cerrar);
	visor.querySelector('.visor__anterior').addEventListener('click', function () { mostrar(actual - 1); });
	visor.querySelector('.visor__siguiente').addEventListener('click', function () { mostrar(actual + 1); });

	// Tocar el fondo oscuro (no la foto ni los botones) tambien cierra
	visor.addEventListener('click', function (e) {
		if (e.target === visor) cerrar();
	});

	document.addEventListener('keydown', function (e) {
		if (!visor.classList.contains('esta-abierto')) return;
		if (e.key === 'Escape') cerrar();
		else if (e.key === 'ArrowLeft') mostrar(actual - 1);
		else if (e.key === 'ArrowRight') mostrar(actual + 1);
	});

	// En el celular, deslizar el dedo pasa de foto
	var inicioX = null;
	visor.addEventListener('touchstart', function (e) { inicioX = e.touches[0].clientX; }, { passive: true });
	visor.addEventListener('touchend', function (e) {
		if (inicioX === null) return;
		var dx = e.changedTouches[0].clientX - inicioX;
		if (Math.abs(dx) > 50) mostrar(actual + (dx < 0 ? 1 : -1));
		inicioX = null;
	});
});
