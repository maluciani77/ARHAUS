// ========================================
// ASISTENTE DEL PANEL DEL CLIENTE
// Manda la pregunta sin recargar la página y agrega la respuesta al
// chat. Si algo de esto no está disponible, queda el formulario común.
// ========================================

(function () {
	'use strict';

	var raiz = document.querySelector('[data-asistente]');
	if (!raiz || !window.fetch || !window.FormData) return;

	var conversacion = raiz.querySelector('[data-conversacion]');
	var formulario = raiz.querySelector('[data-formulario]');
	var campo = formulario.querySelector('textarea');
	var boton = formulario.querySelector('[data-enviar]');
	var error = raiz.querySelector('[data-error]');
	var sugerencias = raiz.querySelector('[data-sugerencias]');
	var esperando = false;

	function agregar(clase, texto) {
		var item = document.createElement('li');
		item.className = 'asistente__mensaje asistente__mensaje--' + clase;
		var parrafo = document.createElement('p');
		// textContent + white-space: pre-line: el texto nunca se interpreta como HTML.
		parrafo.textContent = texto;
		item.appendChild(parrafo);
		conversacion.appendChild(item);
		item.scrollIntoView({ behavior: 'smooth', block: 'end' });
		return item;
	}

	function mostrarError(texto) {
		error.textContent = texto;
		error.hidden = !texto;
	}

	function enviar() {
		var pregunta = campo.value.trim();
		if (!pregunta || esperando) return;

		esperando = true;
		boton.disabled = true;
		mostrarError('');
		if (sugerencias) sugerencias.hidden = true;

		var datos = new FormData(formulario);
		var enviada = agregar('cliente', pregunta);
		campo.value = '';
		var pensando = agregar('asistente is-pensando', 'Pensando…');

		fetch(formulario.action, {
			method: 'POST',
			body: datos,
			headers: { 'X-Requested-With': 'fetch' },
			credentials: 'same-origin'
		})
			.then(function (respuesta) {
				return respuesta.json().catch(function () {
					return { error: 'No se pudo leer la respuesta. Probá de nuevo.' };
				});
			})
			.then(function (resultado) {
				pensando.parentNode.removeChild(pensando);
				if (resultado.respuesta) {
					agregar('asistente', resultado.respuesta);
				} else {
					// No se guardó: la pregunta vuelve al campo para reintentarla.
					enviada.parentNode.removeChild(enviada);
					mostrarError(resultado.error || 'Algo salió mal. Probá de nuevo.');
					campo.value = pregunta;
				}
			})
			.catch(function () {
				pensando.parentNode.removeChild(pensando);
				enviada.parentNode.removeChild(enviada);
				mostrarError('No hay conexión. Probá de nuevo en un momento.');
				campo.value = pregunta;
			})
			.then(function () {
				esperando = false;
				boton.disabled = false;
				campo.focus();
			});
	}

	formulario.addEventListener('submit', function (evento) {
		evento.preventDefault();
		enviar();
	});

	// Enter manda; Shift+Enter hace un salto de línea.
	campo.addEventListener('keydown', function (evento) {
		if (evento.key === 'Enter' && !evento.shiftKey && !evento.isComposing) {
			evento.preventDefault();
			enviar();
		}
	});

	raiz.querySelectorAll('[data-sugerencia]').forEach(function (sugerencia) {
		sugerencia.addEventListener('click', function () {
			campo.value = sugerencia.textContent;
			enviar();
		});
	});

	var ultima = document.getElementById('ultima');
	if (ultima) ultima.scrollIntoView({ block: 'end' });
})();
