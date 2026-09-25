// ========================================
// PORTADA HORIZONTAL
// Fotos a pantalla completa. Un giro de rueda = una foto.
// Tambien se puede arrastrar o usar las flechas.
// ========================================

document.addEventListener('DOMContentLoaded', function () {
	var galeria = document.querySelector('.galeria-h');
	if (!galeria) return;

	var paneles = galeria.querySelectorAll('.panel');
	var marca = galeria.querySelector('.marca');

	// ---------- Entrada de la marca ----------

	function habilitarHover() {
		// La animacion de entrada usa "forwards" y deja clavados transform
		// y opacity; al marcarla terminada el CSS la desactiva y el hover
		// vuelve a poder mover las letras.
		if (marca) marca.classList.add('entrada-lista');
	}

	function arrancarMarca() {
		galeria.classList.add('is-listo');
		if (!marca) return;

		var ultima = marca.querySelector('.marca__tagline');
		var listo = false;
		function unaVez() {
			if (listo) return;
			listo = true;
			habilitarHover();
		}
		if (ultima) ultima.addEventListener('animationend', unaVez, { once: true });
		setTimeout(unaVez, 2600); // red de seguridad
	}

	// El logo no aparece de entrada: primero se deja ver el video solo, y
	// a los 3,5 segundos de abrir la pagina se arma la marca encima.
	var ESPERA_MARCA = 3500;

	function faltaParaLaMarca() {
		// performance.now() cuenta desde que se abrio la pagina, asi que
		// el velo de carga no le suma tiempo: si tardo 1 segundo, aca
		// quedan 3. El minimo es para no encimar la marca con el velo
		// justo cuando se esta desvaneciendo.
		var pasado = (window.performance && performance.now) ? performance.now() : ESPERA_MARCA;
		return Math.max(400, ESPERA_MARCA - pasado);
	}

	var preloader = document.getElementById('preloader');
	if (preloader && !preloader.classList.contains('is-oculto')) {
		document.addEventListener('arhaus:loader-oculto', function () {
			setTimeout(arrancarMarca, faltaParaLaMarca());
		}, { once: true });
		// Red de seguridad por si ese aviso nunca llega (el velo se saca
		// solo a los 6 segundos como maximo).
		setTimeout(arrancarMarca, 6400);
	} else {
		setTimeout(arrancarMarca, faltaParaLaMarca());
	}

	// ---------- Ir de una foto a otra ----------

	var indice = 0;
	var enMovimiento = false;
	var temporizadorMovimiento = null;
	var animacion = false;
	var destinoViaje = 0;
	var menosMovimiento = window.matchMedia
		? window.matchMedia('(prefers-reduced-motion: reduce)').matches
		: false;

	function indiceActual() {
		return Math.round(galeria.scrollLeft / galeria.clientWidth);
	}

	// Un cuadro de animacion. Se pide por las dos vias a la vez y manda la
	// que llegue primero: requestAnimationFrame es la buena, pero hay
	// situaciones (ventana tapada por otra, pestana en segundo plano) en
	// las que el navegador deja de dar cuadros y la galeria se quedaria
	// clavada a mitad de camino. El reloj de respaldo la termina igual.
	var cuadroRaf = null;
	var cuadroReloj = null;

	function pedirCuadro(hacer) {
		var usado = false;
		function unaVez() {
			if (usado) return;
			usado = true;
			cortarCuadro();
			hacer(now());
		}
		cuadroRaf = requestAnimationFrame(unaVez);
		cuadroReloj = setTimeout(unaVez, 32);
	}

	function cortarCuadro() {
		if (cuadroRaf !== null) { cancelAnimationFrame(cuadroRaf); cuadroRaf = null; }
		if (cuadroReloj !== null) { clearTimeout(cuadroReloj); cuadroReloj = null; }
	}

	function now() {
		return window.performance && performance.now ? performance.now() : Date.now();
	}

	function cortarViaje() {
		animacion = false;
		cortarCuadro();
	}

	function plantarEnDestino() {
		cortarViaje();
		galeria.scrollLeft = destinoViaje;
		galeria.classList.remove('esta-moviendose');
	}

	// Si la pestana se va a segundo plano mientras la galeria viaja, los
	// cuadros se congelan: se la deja ya puesta en la foto de destino.
	document.addEventListener('visibilitychange', function () {
		if (document.hidden && animacion) plantarEnDestino();
	});

	// El viaje de una foto a otra lo anima el propio JS, cuadro a cuadro,
	// en lugar de pedirle scrollTo({behavior:'smooth'}) al navegador. Es a
	// proposito: el scroll suave del navegador se pelea con el scroll-snap
	// (lo cancela a mitad de camino) y ademas hay navegadores que lo tienen
	// apagado, y ahi la galeria no se movia nunca.
	function animarHasta(destino) {
		cortarViaje();
		destino = Math.round(destino);
		destinoViaje = destino;
		var desde = galeria.scrollLeft;
		var avance = destino - desde;

		// Mientras viaja, el snap obligatorio estorba: se apaga y se
		// vuelve a prender al llegar, ya parado en la foto justa.
		galeria.classList.add('esta-moviendose');

		if (menosMovimiento || document.hidden || Math.abs(avance) < 2) {
			plantarEnDestino();
			return;
		}

		var arranque = now();
		var DURACION = 520;
		animacion = true;

		(function paso(ahora) {
			if (!animacion) return;
			var parte = Math.min(1, (ahora - arranque) / DURACION);
			var suave = 1 - Math.pow(1 - parte, 3);   // frena al final
			galeria.scrollLeft = desde + avance * suave;
			if (parte < 1) {
				pedirCuadro(paso);
			} else {
				plantarEnDestino();
			}
		})(arranque);
	}

	function irA(nuevo) {
		nuevo = Math.max(0, Math.min(paneles.length - 1, nuevo));
		if (nuevo === indice && enMovimiento) return;

		indice = nuevo;
		enMovimiento = true;
		animarHasta(indice * galeria.clientWidth);

		// Ventana en la que se ignoran gestos nuevos, para que un solo
		// giro de rueda (o el rebote del trackpad) no salte varias fotos.
		clearTimeout(temporizadorMovimiento);
		temporizadorMovimiento = setTimeout(function () {
			enMovimiento = false;
		}, 620);
	}

	// ---------- Rueda: un gesto = una foto ----------

	// Un solo gesto (trackpad con inercia, rueda que gira libre) manda
	// muchos eventos durante mas de un segundo. Solo se toma el primero:
	// el gesto se da por terminado recien tras un rato sin eventos.
	var PAUSA_ENTRE_GESTOS = 220;
	var ultimaRueda = 0;

	galeria.addEventListener('wheel', function (e) {
		// Si el gesto ya es horizontal (trackpad), dejarlo pasar tal cual.
		if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;

		e.preventDefault();
		if (e.deltaY === 0) return;

		var ahora = Date.now();
		var mismoGesto = ahora - ultimaRueda < PAUSA_ENTRE_GESTOS;
		ultimaRueda = ahora;
		if (mismoGesto || enMovimiento) return;   // sigue el mismo gesto o todavia viaja

		// OJO: no filtrar por el tamano de deltaY. Segun el navegador y el
		// mouse, la rueda informa PIXELES (deltaMode 0) o LINEAS (deltaMode 1,
		// donde un clic de rueda es apenas 3). Con un umbral, en esos casos
		// se descartaban todos los giros y la galeria no se movia nunca.
		// Alcanza con el signo para saber para donde ir.
		indice = indiceActual();
		irA(indice + (e.deltaY > 0 ? 1 : -1));
	}, { passive: false });

	// ---------- Arrastrar con el mouse ----------

	// Se usan eventos de puntero y no de mouse porque asi el navegador
	// avisa aunque el cursor se vaya de la ventana. En el celular no se
	// tocan: el dedo ya arrastra solo, y meterse al medio lo empeora.

	var arrastrando = false;
	var inicioX = 0;
	var inicioScroll = 0;
	var seMovio = false;
	var punteroId = null;

	galeria.addEventListener('pointerdown', function (e) {
		if (e.pointerType === 'touch') return;   // el dedo se arregla solo
		if (e.button !== 0) return;              // solo el boton izquierdo
		// La maqueta 3D se gira con el mismo gesto: ahi el arrastre es
		// de ella, no de la galeria.
		if (e.target.closest('a, button, model-viewer')) return;

		arrastrando = true;
		seMovio = false;
		punteroId = e.pointerId;
		inicioX = e.clientX;
		inicioScroll = galeria.scrollLeft;
		galeria.classList.add('esta-arrastrando');

		// Cortar el viaje que pudiera estar en curso: si no, sigue
		// corriendo solo y se pelea con la mano.
		cortarViaje();
		clearTimeout(temporizadorMovimiento);
		enMovimiento = false;

		try { galeria.setPointerCapture(e.pointerId); } catch (err) {}
	});

	galeria.addEventListener('pointermove', function (e) {
		if (!arrastrando || e.pointerId !== punteroId) return;
		e.preventDefault();
		var avance = e.clientX - inicioX;
		if (Math.abs(avance) > 3) seMovio = true;
		galeria.scrollLeft = inicioScroll - avance;
	});

	function soltar(e) {
		if (!arrastrando || (e && e.pointerId !== punteroId)) return;
		arrastrando = false;
		punteroId = null;

		// Al soltar, acomodar en la foto mas cercana. Si el arrastre fue
		// largo, la de al lado; si fue un tironcito, vuelve a la misma.
		var avance = e ? e.clientX - inicioX : 0;
		var salto = 0;
		if (Math.abs(avance) > galeria.clientWidth * 0.12) {
			salto = avance < 0 ? 1 : -1;
		}
		var desde = Math.round(inicioScroll / galeria.clientWidth);
		var cercana = indiceActual();

		// OJO CON EL ORDEN: primero se marca que sigue en movimiento y
		// recien despues se saca la clase de arrastre. Las dos apagan el
		// snap; si por un instante quedan las dos afuera, el navegador
		// devuelve la galeria de un saltito a la foto anterior y encima
		// cancela el viaje que arranca aca abajo.
		galeria.classList.add('esta-moviendose');
		galeria.classList.remove('esta-arrastrando');

		irA(salto ? desde + salto : cercana);
	}

	galeria.addEventListener('pointerup', soltar);
	galeria.addEventListener('pointercancel', soltar);
	window.addEventListener('blur', function () { soltar(null); });

	// Que arrastrar no dispare el click de los enlaces
	galeria.addEventListener('click', function (e) {
		if (seMovio) {
			e.preventDefault();
			e.stopPropagation();
		}
	}, true);

	// Evitar el "fantasma" de arrastre nativo de las imagenes
	galeria.querySelectorAll('img').forEach(function (img) {
		img.addEventListener('dragstart', function (e) { e.preventDefault(); });
	});

	// ---------- Flechas del teclado ----------

	document.addEventListener('keydown', function (e) {
		if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
		e.preventDefault();
		indice = indiceActual();
		irA(indice + (e.key === 'ArrowRight' ? 1 : -1));
	});

	// Si cambia el tamano de la ventana, recolocar en la foto actual
	window.addEventListener('resize', function () {
		galeria.scrollLeft = indice * galeria.clientWidth;
	});
});

// ========================================
// VIDEO DE LA PORTADA
// Aparece con un fundido cuando ya tiene imagen para mostrar. Si no
// llega a reproducirse (celular en ahorro de energia, autoplay
// bloqueado), abajo queda la foto de respaldo y no se nota.
// ========================================

document.addEventListener('DOMContentLoaded', function () {
	var video = document.querySelector('.panel__video');
	if (!video) return;

	function mostrar() {
		video.classList.add('esta-lista');
	}

	if (video.readyState >= 2) {
		mostrar();
	} else {
		video.addEventListener('loadeddata', mostrar, { once: true });
	}

	// Algunos navegadores no arrancan el autoplay hasta que la pestana
	// esta a la vista; se reintenta y, si igual no va, queda la foto.
	var intento = video.play();
	if (intento && typeof intento.catch === 'function') {
		intento.catch(function () {
			document.addEventListener('click', function () {
				video.play().then(mostrar, function () {});
			}, { once: true });
		});
	}
});
