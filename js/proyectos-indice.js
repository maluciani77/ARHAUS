// ========================================
// MOSAICO DE PROYECTOS
// Pasar el cursor por un cuadro muestra la maqueta 3D (o el render) de
// ESE proyecto en el medio de la grilla. Al salir, el medio queda vacio.
// ========================================

document.addEventListener('DOMContentLoaded', function () {
	var mosaico = document.querySelector('.mosaico');
	if (!mosaico) return;

	var centro = mosaico.querySelector('.mosaico__centro');
	var leyenda = mosaico.querySelector('.mosaico__leyenda');
	var cuadros = Array.prototype.slice.call(mosaico.querySelectorAll('.mosaico__cuadro'));
	var piezas = Array.prototype.slice.call(mosaico.querySelectorAll('.mosaico__pieza'));

	var hayMouse = window.matchMedia
		? window.matchMedia('(hover: hover) and (pointer: fine)').matches
		: false;

	// ---------- Las maquetas se traen recien cuando hacen falta ----------

	// Entre las dos maquetas y los renders hay varios megas. Si se
	// cargaran todos al abrir la pagina, el mosaico tardaria un monton en
	// aparecer. Cada pieza guarda su archivo en data-src y se lo pone
	// encima la primera vez que alguien la mira.
	function traer(pieza) {
		if (!pieza || pieza.getAttribute('src')) return;
		var origen = pieza.getAttribute('data-src');
		if (origen) pieza.setAttribute('src', origen);
	}

	// Con mouse, las maquetas se pueden agarrar y girar a mano
	if (hayMouse) {
		piezas.forEach(function (pieza) {
			if (pieza.tagName.toLowerCase() !== 'model-viewer') return;
			pieza.setAttribute('camera-controls', '');
			pieza.setAttribute('disable-pan', '');
			pieza.setAttribute('disable-zoom', '');
		});
	}

	// ---------- Mostrar y esconder ----------

	var activo = null;
	var temporizadorApagado = null;
	var temporizadoresOcultar = {};

	function piezaDe(cuadro) {
		var clave = cuadro.getAttribute('data-pieza');
		return piezas.filter(function (p) { return p.getAttribute('data-pieza') === clave; })[0];
	}

	function mostrar(cuadro) {
		if (temporizadorApagado) {
			clearTimeout(temporizadorApagado);
			temporizadorApagado = null;
		}
		if (activo === cuadro) return;
		activo = cuadro;

		cuadros.forEach(function (otro) {
			otro.classList.toggle('esta-activo', otro === cuadro);
		});

		var laQueVa = piezaDe(cuadro);

		piezas.forEach(function (pieza) {
			var clave = pieza.getAttribute('data-pieza');
			if (pieza === laQueVa) {
				if (temporizadoresOcultar[clave]) {
					clearTimeout(temporizadoresOcultar[clave]);
					delete temporizadoresOcultar[clave];
				}
				traer(pieza);
				pieza.classList.add('se-esta-mostrando');
				// Un reflow antes de encender: si se saca display:none y se
				// pone la opacidad en el mismo tick, no hay transicion.
				void pieza.offsetWidth;
				pieza.classList.add('esta-a-la-vista');
			} else {
				apagarPieza(pieza);
			}
		});

		if (leyenda) {
			leyenda.textContent = cuadro.getAttribute('data-leyenda') || '';
			leyenda.classList.add('esta-a-la-vista');
		}
	}

	function apagarPieza(pieza) {
		if (!pieza.classList.contains('se-esta-mostrando')) return;
		pieza.classList.remove('esta-a-la-vista');
		var clave = pieza.getAttribute('data-pieza');
		if (temporizadoresOcultar[clave]) clearTimeout(temporizadoresOcultar[clave]);
		// Recien se saca de pantalla cuando termino de desvanecerse. Asi
		// el visor 3D deja de dibujar y no gasta de gusto.
		temporizadoresOcultar[clave] = setTimeout(function () {
			delete temporizadoresOcultar[clave];
			if (!pieza.classList.contains('esta-a-la-vista')) {
				pieza.classList.remove('se-esta-mostrando');
			}
		}, 500);
	}

	function esconder() {
		activo = null;
		cuadros.forEach(function (c) { c.classList.remove('esta-activo'); });
		piezas.forEach(apagarPieza);
		if (leyenda) leyenda.classList.remove('esta-a-la-vista');
	}

	// No se apaga en el acto: la maqueta del medio tapa parte de los
	// cuadros, asi que al ir del cuadro a la maqueta el cursor "sale" por
	// un instante. Con esta espera, ese paso no la apaga.
	function esconderConDemora() {
		if (temporizadorApagado) clearTimeout(temporizadorApagado);
		temporizadorApagado = setTimeout(function () {
			temporizadorApagado = null;
			esconder();
		}, 180);
	}

	if (hayMouse) {
		cuadros.forEach(function (cuadro) {
			cuadro.addEventListener('mouseenter', function () { mostrar(cuadro); });
			cuadro.addEventListener('mouseleave', esconderConDemora);
		});

		if (centro) {
			// Estar sobre la maqueta cuenta como seguir mirando el proyecto
			centro.addEventListener('mouseenter', function () {
				if (temporizadorApagado) {
					clearTimeout(temporizadorApagado);
					temporizadorApagado = null;
				}
			});
			centro.addEventListener('mouseleave', esconderConDemora);
		}
	}

	// Con el teclado pasa lo mismo al llegar a cada cuadro
	cuadros.forEach(function (cuadro) {
		cuadro.addEventListener('focus', function () { mostrar(cuadro); });
		cuadro.addEventListener('blur', function () {
			setTimeout(function () {
				if (!mosaico.contains(document.activeElement)) esconder();
			}, 0);
		});
	});

	// ---------- En pantalla angosta, la foto de cada cuadro ----------

	// Ahi el medio no se muestra y cada cuadro trae su propia foto. Se
	// cargan cuando la pagina ya esta a la vista, no antes.
	function traerFotos() {
		mosaico.querySelectorAll('.mosaico__foto[data-src]').forEach(function (img) {
			if (!img.getAttribute('src')) img.src = img.getAttribute('data-src');
		});
	}

	// OJO: se mira el ANCHO y no si hay mouse. Una ventana chica en una
	// computadora tiene mouse igual, y si se decidiera por el mouse esas
	// fotos se quedaban sin cargar y aparecia el cuadradito roto.
	var angosta = window.matchMedia ? window.matchMedia('(max-width: 900px)') : null;

	function siHaceFaltaTraerFotos() {
		if (!angosta || angosta.matches) traerFotos();
	}

	if (document.readyState === 'complete') {
		setTimeout(siHaceFaltaTraerFotos, 150);
	} else {
		window.addEventListener('load', function () { setTimeout(siHaceFaltaTraerFotos, 150); }, { once: true });
	}

	// Y tambien si achican la ventana despues
	if (angosta && angosta.addEventListener) {
		angosta.addEventListener('change', siHaceFaltaTraerFotos);
	}
});
