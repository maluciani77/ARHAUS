// ========================================
// BOTON DEL MENU (arriba a la derecha)
// Chico y discreto; al tocarlo se despliegan los enlaces.
// En la compu ni hace falta tocarlo: se abre solo al pasar el mouse.
// ========================================

document.addEventListener('DOMContentLoaded', function () {
	var boton = document.querySelector('.nav__toggle');
	var panel = document.getElementById('menu-principal');
	if (!boton || !panel) return;

	var nav = boton.closest('nav');
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
		cancelarCierre();
		if (abierto) { cerrar(); } else { abrir(); }
	});

	// ---------- Abrir al pasar el mouse ----------

	// Solo donde hay de verdad un mouse. En el celular y en la tablet la
	// consulta da que no, los oyentes de abajo no se enganchan y el menu
	// sigue abriendose con un toque, que es lo logico ahi: un dedo no
	// "pasa por encima" de nada, y si no, el menu se abriria de onda.
	var hayMouse = window.matchMedia
		? window.matchMedia('(hover: hover) and (pointer: fine)').matches
		: false;

	var temporizadorCierre = null;

	function cancelarCierre() {
		if (temporizadorCierre) {
			clearTimeout(temporizadorCierre);
			temporizadorCierre = null;
		}
	}

	// No se cierra en el acto: entre el signo mas y la tarjeta hay un
	// pedacito de pantalla vacia, y sin esta espera el menu se cerraba
	// justo cuando el mouse iba en camino a elegir un enlace.
	function cerrarConDemora() {
		cancelarCierre();
		temporizadorCierre = setTimeout(function () {
			temporizadorCierre = null;
			cerrar();
		}, 240);
	}

	if (hayMouse && nav) {
		// El menu es hijo del nav, asi que pasar del boton a la tarjeta
		// no cuenta como salir: el navegador ya tiene en cuenta a los
		// hijos, aunque la tarjeta se dibuje afuera de la barra.
		nav.addEventListener('mouseenter', function () {
			cancelarCierre();
			if (!abierto) abrir();
		});

		nav.addEventListener('mouseleave', cerrarConDemora);
	}

	// Cerrar al hacer clic afuera
	document.addEventListener('click', function (e) {
		if (abierto && !panel.contains(e.target) && e.target !== boton) {
			cancelarCierre();
			cerrar();
		}
	});

	// Cerrar con Escape
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && abierto) {
			cancelarCierre();
			cerrar();
			boton.focus();
		}
	});
});
