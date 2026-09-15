// ========================================
// SUBIDA DE VARIAS FOTOS DE UNA (paneles admin / arquitecto)
// Se eligen todas juntas, pero se mandan de a una: cada envío es chico,
// así nunca se pasa del límite de tamaño total del servidor, y se ve el
// progreso de cada foto. Si una falla, las demás siguen.
// ========================================

(function () {
	'use strict';

	var LIMITE_BYTES = 8 * 1024 * 1024; // igual que en lib/uploads.php

	if (!window.FormData || !window.XMLHttpRequest) return; // queda el envío común

	function pesoLegible(bytes) {
		if (bytes >= 1024 * 1024) {
			return (bytes / 1024 / 1024).toFixed(1).replace('.', ',') + ' MB';
		}
		return Math.max(1, Math.round(bytes / 1024)) + ' KB';
	}

	function enviarUna(url, base, archivo, alProgreso) {
		return new Promise(function (resolver) {
			var datos = new FormData();
			base.forEach(function (par) { datos.append(par[0], par[1]); });
			datos.append('fotos[]', archivo, archivo.name);

			var xhr = new XMLHttpRequest();
			xhr.open('POST', url);
			xhr.setRequestHeader('X-Requested-With', 'fetch');

			xhr.upload.addEventListener('progress', function (e) {
				if (e.lengthComputable) alProgreso(e.loaded / e.total);
			});

			xhr.addEventListener('load', function () {
				var respuesta = null;
				try { respuesta = JSON.parse(xhr.responseText); } catch (err) { /* no era JSON */ }

				if (respuesta && typeof respuesta.subidas === 'number') {
					if (respuesta.subidas > 0) {
						resolver({ ok: true });
					} else {
						// El servidor antepone "archivo.jpg: "; aca el nombre ya se ve.
						var motivo = (respuesta.errores && respuesta.errores[0]) || 'No se pudo subir.';
						resolver({ ok: false, error: motivo.replace(archivo.name + ': ', '') });
					}
				} else if (/login\.html/.test(xhr.responseURL || '')) {
					resolver({ ok: false, error: 'Se cerró la sesión. Volvé a entrar y probá de nuevo.', cortar: true });
				} else if (xhr.status === 413) {
					resolver({ ok: false, error: 'La foto es demasiado pesada para el servidor.' });
				} else {
					resolver({ ok: false, error: 'El servidor respondió con un error (' + xhr.status + ').' });
				}
			});

			xhr.addEventListener('error', function () {
				resolver({ ok: false, error: 'Se cortó la conexión.' });
			});

			xhr.send(datos);
		});
	}

	document.querySelectorAll('form[data-subida-multiple]').forEach(function (form) {
		var input = form.querySelector('input[type="file"]');
		var lista = form.querySelector('.panel-subida__lista');
		var resumen = form.querySelector('.panel-subida__resumen');
		var boton = form.querySelector('button[type="submit"]');
		var textoBoton = boton.textContent;
		var url = window.location.pathname + window.location.search;

		var filas = [];
		var subiendo = false;
		var terminadoConErrores = false;

		function marcarError(fila, mensaje) {
			fila.li.classList.remove('is-subiendo');
			fila.li.classList.add('is-error');
			fila.estado.textContent = 'Error';
			var detalle = document.createElement('span');
			detalle.className = 'panel-subida__error';
			detalle.textContent = mensaje;
			fila.li.appendChild(detalle);
		}

		function mostrarSeleccion() {
			var archivos = Array.prototype.slice.call(input.files || []);
			lista.innerHTML = '';
			filas = [];
			terminadoConErrores = false;
			resumen.hidden = true;

			archivos.forEach(function (archivo) {
				var li = document.createElement('li');
				li.className = 'panel-subida__item';

				var nombre = document.createElement('span');
				nombre.className = 'panel-subida__nombre';
				nombre.textContent = archivo.name; // textContent: el nombre nunca se interpreta como HTML

				var estado = document.createElement('span');
				estado.className = 'panel-subida__estado';
				estado.textContent = pesoLegible(archivo.size);

				var barra = document.createElement('span');
				barra.className = 'panel-subida__barra';
				var relleno = document.createElement('span');
				barra.appendChild(relleno);

				li.appendChild(nombre);
				li.appendChild(estado);
				li.appendChild(barra);
				lista.appendChild(li);

				var fila = { archivo: archivo, li: li, estado: estado, relleno: relleno };
				if (archivo.size > LIMITE_BYTES) {
					marcarError(fila, 'Supera los 8 MB, no se va a subir.');
				}
				filas.push(fila);
			});

			lista.hidden = archivos.length === 0;
			boton.textContent = archivos.length > 1 ? 'Subir ' + archivos.length + ' fotos' : textoBoton;
		}

		input.addEventListener('change', mostrarSeleccion);

		form.addEventListener('submit', function (e) {
			if (terminadoConErrores) {
				// El botón ya dice "Ver las fotos subidas".
				e.preventDefault();
				window.location.href = url;
				return;
			}
			if (!filas.length) return;
			e.preventDefault();
			if (subiendo) return;

			// Los demás campos (token, etapa, descripción) se copian ANTES de
			// deshabilitar el input: un campo deshabilitado no entra al FormData.
			var base = [];
			new FormData(form).forEach(function (valor, clave) {
				if (clave !== 'fotos[]') base.push([clave, valor]);
			});

			subiendo = true;
			boton.disabled = true;
			input.disabled = true;
			resumen.hidden = true;

			var total = filas.length;
			var subidas = 0;
			var i = 0;
			var cortado = false;

			function siguiente() {
				if (i >= total || cortado) return terminar();
				var fila = filas[i++];

				if (fila.li.classList.contains('is-error')) return siguiente(); // ya descartada (pesada)

				boton.textContent = 'Subiendo ' + i + ' de ' + total + '…';
				fila.li.classList.add('is-subiendo');
				fila.estado.textContent = '0%';

				enviarUna(url, base, fila.archivo, function (p) {
					fila.relleno.style.width = Math.round(p * 100) + '%';
					fila.estado.textContent = p >= 1 ? 'Procesando…' : Math.round(p * 100) + '%';
				}).then(function (r) {
					if (r.ok) {
						subidas++;
						fila.li.classList.remove('is-subiendo');
						fila.li.classList.add('is-ok');
						fila.relleno.style.width = '100%';
						fila.estado.textContent = '✓ Subida';
					} else {
						marcarError(fila, r.error);
						if (r.cortar) cortado = true;
					}
					siguiente();
				});
			}

			function terminar() {
				subiendo = false;
				resumen.hidden = false;

				if (subidas === total) {
					resumen.classList.remove('is-error');
					resumen.textContent = subidas === 1
						? 'Listo: se subió la foto.'
						: 'Listo: se subieron las ' + subidas + ' fotos.';
					boton.textContent = 'Listo';
					setTimeout(function () { window.location.href = url; }, 900);
					return;
				}

				resumen.classList.add('is-error');
				resumen.textContent = 'Se subieron ' + subidas + ' de ' + total +
					'. Las que tienen error no se guardaron.';
				input.disabled = false;
				boton.disabled = false;

				if (subidas > 0) {
					terminadoConErrores = true;
					boton.textContent = 'Ver las fotos subidas';
				} else {
					boton.textContent = textoBoton;
				}
			}

			siguiente();
		});
	});
})();
