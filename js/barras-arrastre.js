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

	barras.forEach(function (barra) {
		var arrastrando = false;
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

			arrastrando = true;
			seMovio = false;
			punteroId = e.pointerId;
			inicioX = e.clientX;
			inicioScroll = barra.scrollLeft;
			barra.classList.add('esta-arrastrando');
			try { barra.setPointerCapture(e.pointerId); } catch (err) {}
		});

		barra.addEventListener('pointermove', function (e) {
			if (!arrastrando || e.pointerId !== punteroId) return;
			e.preventDefault();
			var avance = e.clientX - inicioX;
			if (Math.abs(avance) > 3) seMovio = true;
			barra.scrollLeft = inicioScroll - avance;
		});

		function soltar(e) {
			if (!arrastrando || (e && e.pointerId !== punteroId)) return;
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
