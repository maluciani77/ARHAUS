// ========================================
// FORMULARIO DE CONTACTO
// Manda el mensaje sin irse de la página y muestra el resultado ahí
// mismo. Si esto no corre, el formulario sigue siendo uno común: cae en
// contacto.php, que manda el mail igual.
// ========================================

(function () {
	'use strict';

	var formulario = document.querySelector('[data-contacto]');
	if (!formulario || !window.fetch || !window.FormData) return;

	var aviso = formulario.querySelector('[data-aviso]');
	var boton = formulario.querySelector('[data-enviar]');
	var textoBoton = boton.textContent;
	var enviando = false;

	function mostrar(clase, texto) {
		aviso.className = 'contacto__aviso contacto__aviso--' + clase;
		aviso.textContent = texto;
		aviso.hidden = false;
	}

	formulario.addEventListener('submit', function (evento) {
		// checkValidity: si falta algo, se deja que el navegador avise a su
		// manera en vez de mandar y que conteste el servidor.
		if (!formulario.checkValidity()) return;

		evento.preventDefault();
		if (enviando) return;

		enviando = true;
		boton.disabled = true;
		boton.textContent = 'Enviando…';
		aviso.hidden = true;

		fetch(formulario.action, {
			method: 'POST',
			body: new FormData(formulario),
			headers: { 'X-Requested-With': 'fetch' },
			credentials: 'same-origin'
		})
			.then(function (respuesta) {
				return respuesta.json().catch(function () {
					return { error: 'No se pudo leer la respuesta. Probá de nuevo.' };
				});
			})
			.then(function (resultado) {
				if (resultado && resultado.ok) {
					mostrar('ok', resultado.mensaje);
					formulario.reset();
				} else {
					mostrar('mal', (resultado && resultado.error) || 'Algo salió mal. Probá de nuevo.');
				}
			})
			.catch(function () {
				mostrar('mal', 'No hay conexión. Probá de nuevo en un momento.');
			})
			.then(function () {
				enviando = false;
				boton.disabled = false;
				boton.textContent = textoBoton;
			});
	});
})();
