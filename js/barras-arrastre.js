// ========================================
// LAS BARRAS DE SOLAPAS SE ARRASTRAN
// Cuando una solapa tiene muchas hojas (Planos tiene diez), la barra no
// entra en la pantalla. En el celular se desliza con el dedo, pero en la
// computadora no había forma de moverla: la rueda del mouse mueve la
// página, no la barra. Con esto se agarra y se arrastra.
// ========================================

(function () {
	'use strict';

	var barras = document.querySelectorAll('.cliente-subnav');
	if (!barras.length) return;

	/* Cuánto hay que correr el mouse para que esto cuente como arrastre y
	   no como un click con pulso tembloroso. */
	var UMBRAL = 4;

	barras.forEach(function (barra) {
		var apretado = false;     // el botón está hundido
		var arrastrando = false;  // ...y además ya se movió: es un arrastre
		var seMovio = false;
		var inicioX = 0;
		var inicioScroll = 0;
		var punteroId = null;

		/** Sobra ancho para moverse: recién ahí tiene sentido el cursor de mano. */
		function revisarSiEntra() {
			barra.classList.toggle('se-puede-arrastrar', barra.scrollWidth > barra.clientWidth + 2);
		}

		revisarSiEntra();
		window.addEventListener('resize', revisarSiEntra);

		barra.addEventListener('pointerdown', function (e) {
			// El dedo ya desliza solo; meterse al medio lo empeora.
			if (e.pointerType === 'touch' || e.button !== 0) return;
			if (barra.scrollWidth <= barra.clientWidth) return;

			apretado = true;
			arrastrando = false;
			seMovio = false;
			punteroId = e.pointerId;
			inicioX = e.clientX;
			inicioScroll = barra.scrollLeft;
			// OJO: acá NO se toma el puntero. Si se lo toma apenas se
			// aprieta, el navegador le manda el click a la barra en vez de
			// a la solapa que está debajo del cursor, y entrar a Eléctricos
			// o a Sanitarios deja de funcionar. Se lo toma más abajo,
			// recién cuando la mano de verdad se movió.
		});

		barra.addEventListener('pointermove', function (e) {
			if (!apretado || e.pointerId !== punteroId) return;
			var avance = e.clientX - inicioX;

			if (!arrastrando) {
				if (Math.abs(avance) < UMBRAL) return;   // todavía es un click
				arrastrando = true;
				seMovio = true;
				barra.classList.add('esta-arrastrando');
				// Ahora sí: con el puntero tomado, la barra sigue
				// recibiendo el movimiento aunque el cursor se vaya afuera.
				try { barra.setPointerCapture(e.pointerId); } catch (err) {}
			}

			e.preventDefault();
			barra.scrollLeft = inicioScroll - avance;
		});

		function soltar(e) {
			if (!apretado || (e && e.pointerId !== punteroId)) return;
			if (arrastrando && punteroId !== null) {
				try { barra.releasePointerCapture(punteroId); } catch (err) {}
			}
			apretado = false;
			arrastrando = false;
			punteroId = null;
			barra.classList.remove('esta-arrastrando');
		}

		barra.addEventListener('pointerup', soltar);
		barra.addEventListener('pointercancel', soltar);
		window.addEventListener('blur', function () { soltar(null); });

		// Que arrastrar no abra la solapa que quedó abajo del cursor
		barra.addEventListener('click', function (e) {
			if (seMovio) {
				e.preventDefault();
				e.stopPropagation();
			}
		}, true);

		// La rueda del mouse, estando encima de la barra, la mueve de costado
		barra.addEventListener('wheel', function (e) {
			if (barra.scrollWidth <= barra.clientWidth) return;
			if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;  // el trackpad ya la mueve
			if (e.deltaY === 0) return;
			e.preventDefault();
			barra.scrollLeft += e.deltaY;
		}, { passive: false });
	});
})();
