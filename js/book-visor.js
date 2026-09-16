// ========================================
// VISOR DE FOTOS (panel del cliente)
// Al tocar una foto se abre a pantalla completa, sin recortar. Se pasa
// de una a otra con las flechas (pantalla o teclado) o deslizando en el
// celular, recorriendo todas las fotos del book en orden. Esc cierra.
// Sin JavaScript, cada foto es un enlace a la imagen original.
// ========================================

document.addEventListener('DOMContentLoaded', function () {
	var enlaces = Array.prototype.slice.call(document.querySelectorAll('[data-visor]'));
	if (!enlaces.length) return;

	var visor = document.createElement('div');
	visor.className = 'visor';
	visor.hidden = true;
	visor.setAttribute('role', 'dialog');
	visor.setAttribute('aria-modal', 'true');
	visor.setAttribute('aria-label', 'Foto ampliada');
	visor.innerHTML =
		'<button type="button" class="visor__anterior" aria-label="Foto anterior">&#8249;</button>' +
		'<figure class="visor__figura">' +
			'<img class="visor__img" alt="">' +
			'<figcaption class="visor__pie">' +
				'<span class="visor__texto"></span>' +
				'<span class="visor__contador"></span>' +
			'</figcaption>' +
		'</figure>' +
		'<button type="button" class="visor__siguiente" aria-label="Foto siguiente">&#8250;</button>' +
		'<button type="button" class="visor__cerrar" aria-label="Cerrar">&times;</button>';
	document.body.appendChild(visor);

	var img = visor.querySelector('.visor__img');
	var texto = visor.querySelector('.visor__texto');
	var contador = visor.querySelector('.visor__contador');
	var anterior = visor.querySelector('.visor__anterior');
	var siguiente = visor.querySelector('.visor__siguiente');
	var cerrar = visor.querySelector('.visor__cerrar');
	var botones = [anterior, siguiente, cerrar];

	var actual = 0;
	var focoPrevio = null;

	if (enlaces.length === 1) {
		anterior.hidden = true;
		siguiente.hidden = true;
	}

	function mostrar(i) {
		actual = (i + enlaces.length) % enlaces.length;
		var enlace = enlaces[actual];
		var miniatura = enlace.querySelector('img');
		img.src = enlace.href;
		img.alt = miniatura ? miniatura.alt : '';
		texto.textContent = enlace.getAttribute('data-pie') || '';
		contador.textContent = (actual + 1) + ' / ' + enlaces.length;
	}

	function abrir(i) {
		// Al cerrar, el foco vuelve a la foto que se tocó (no todos los
		// navegadores le dan foco a un enlace al hacerle clic).
		focoPrevio = enlaces[i];
		mostrar(i);
		visor.hidden = false;
		document.body.classList.add('visor-abierto');
		cerrar.focus();
	}

	function cerrarVisor() {
		visor.hidden = true;
		document.body.classList.remove('visor-abierto');
		img.removeAttribute('src');
		if (focoPrevio) focoPrevio.focus();
	}

	enlaces.forEach(function (enlace, i) {
		enlace.addEventListener('click', function (e) {
			// Ctrl/Cmd + clic sigue abriendo la foto en otra pestaña.
			if (e.ctrlKey || e.metaKey || e.shiftKey) return;
			e.preventDefault();
			abrir(i);
		});
	});

	anterior.addEventListener('click', function () { mostrar(actual - 1); });
	siguiente.addEventListener('click', function () { mostrar(actual + 1); });
	cerrar.addEventListener('click', cerrarVisor);

	// Tocar el fondo (fuera de la foto) también cierra.
	visor.addEventListener('click', function (e) {
		if (e.target === visor || e.target.classList.contains('visor__figura')) cerrarVisor();
	});

	document.addEventListener('keydown', function (e) {
		if (visor.hidden) return;
		if (e.key === 'Escape') {
			cerrarVisor();
		} else if (e.key === 'ArrowLeft') {
			mostrar(actual - 1);
		} else if (e.key === 'ArrowRight') {
			mostrar(actual + 1);
		} else if (e.key === 'Tab') {
			// Mantener el foco adentro del visor.
			var visibles = botones.filter(function (b) { return !b.hidden; });
			var posicion = visibles.indexOf(document.activeElement);
			var destino = e.shiftKey ? posicion - 1 : posicion + 1;
			e.preventDefault();
			visibles[(destino + visibles.length) % visibles.length].focus();
		}
	});

	// Deslizar en el celular
	var inicioX = null;
	visor.addEventListener('touchstart', function (e) {
		inicioX = e.touches.length === 1 ? e.touches[0].clientX : null;
	}, { passive: true });
	visor.addEventListener('touchend', function (e) {
		if (inicioX === null) return;
		var avance = e.changedTouches[0].clientX - inicioX;
		inicioX = null;
		if (Math.abs(avance) > 50) mostrar(actual + (avance < 0 ? 1 : -1));
	});
});
