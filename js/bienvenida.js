// ========================================
// BIENVENIDA DEL PANEL DEL CLIENTE
// La animación la hace el CSS (3,5 s en total). Esto solo saca el cartel
// cuando termina, para que no quede tapando nada, y deja saltearlo con un
// clic o una tecla. Sin JavaScript el CSS igual lo deja invisible.
// ========================================

(function () {
	'use strict';

	var cartel = document.querySelector('[data-bienvenida]');
	if (!cartel) return;

	var DURACION_MS = 3500;

	function sacar() {
		if (!cartel.parentNode) return;
		cartel.parentNode.removeChild(cartel);
		document.removeEventListener('keydown', sacar);
	}

	function saltear() {
		cartel.classList.add('is-saliendo');
		window.setTimeout(sacar, 450);
	}

	cartel.addEventListener('animationend', function (evento) {
		if (evento.target === cartel) sacar();
	});
	cartel.addEventListener('click', saltear);
	document.addEventListener('keydown', saltear, { once: true });

	// Por si el navegador no dispara animationend (pestaña en segundo plano).
	window.setTimeout(sacar, DURACION_MS + 200);
})();
