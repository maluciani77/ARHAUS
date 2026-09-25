// ========================================
// ASISTENTE DEL PANEL DEL CLIENTE
// Dos cosas: abre y cierra el cajón que aparece a un costado —sin irse
// de la página, para poder seguir mirando la obra mientras se pregunta—
// y manda la pregunta sin recargar. Si algo de esto no está disponible,
// el botón sigue siendo un enlace a la página del asistente y el chat
// sigue siendo un formulario común.
// ========================================

(function () {
	'use strict';

	// ---------- El cajón ----------

	var cajon = document.querySelector('[data-asistente-cajon]');
	var abrir = document.querySelector('[data-asistente-abrir]');
	var LLAVE = 'arhaus:asistente-abierto';

	if (cajon && abrir && window.fetch) {
		var cerrarBoton = cajon.querySelector('[data-asistente-cerrar]');

		function guardar(abierto) {
			// Cada página del panel se carga de nuevo desde el servidor. Sin
			// esto, el cajón se cerraría solo al cambiar de sección.
			try {
				if (abierto) { sessionStorage.setItem(LLAVE, '1'); }
				else { sessionStorage.removeItem(LLAVE); }
			} catch (e) {}
		}

		function abrirCajon(conFoco) {
			cajon.hidden = false;
			// Un reflow antes de la clase: si no, no hay transición.
			void cajon.offsetWidth;
			cajon.classList.add('esta-abierto');
			abrir.setAttribute('aria-expanded', 'true');
			guardar(true);
			var campo = cajon.querySelector('textarea');
			if (conFoco && campo) campo.focus();
			alFondo();
		}

		function cerrarCajon() {
			cajon.classList.remove('esta-abierto');
			abrir.setAttribute('aria-expanded', 'false');
			guardar(false);
			var listo = false;
			function ocultar() {
				if (listo || cajon.classList.contains('esta-abierto')) return;
				listo = true;
				cajon.hidden = true;
			}
			cajon.addEventListener('transitionend', ocultar, { once: true });
			setTimeout(ocultar, 450);
		}

		abrir.addEventListener('click', function (evento) {
			evento.preventDefault();   // el href queda para quien no tenga JS
			if (cajon.classList.contains('esta-abierto')) { cerrarCajon(); }
			else { abrirCajon(true); }
		});

		if (cerrarBoton) cerrarBoton.addEventListener('click', cerrarCajon);

		document.addEventListener('keydown', function (evento) {
			if (evento.key === 'Escape' && cajon.classList.contains('esta-abierto')) {
				cerrarCajon();
				abrir.focus();
			}
		});

		// Si venía abierto de la página anterior, se abre solo (sin robar
		// el foco: la persona vino a mirar otra cosa).
		try {
			if (sessionStorage.getItem(LLAVE) === '1') abrirCajon(false);
		} catch (e) {}
	}

	// ---------- El chat ----------

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
		alFondo();
		return item;
	}

	// OJO: se mueve el scroll DE LA LISTA, no el de la página. Con
	// scrollIntoView, abrir el cajón arrastraba la página de atrás.
	function alFondo() {
		var lista = document.querySelector('[data-conversacion]');
		if (lista) lista.scrollTop = lista.scrollHeight;
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

	alFondo();
})();
