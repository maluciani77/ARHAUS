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

	var preloader = document.getElementById('preloader');
	if (preloader && !preloader.classList.contains('is-oculto')) {
		document.addEventListener('arhaus:loader-oculto', function () {
			setTimeout(arrancarMarca, 250);
		}, { once: true });
		setTimeout(arrancarMarca, 5000);
	} else {
		requestAnimationFrame(function () {
			requestAnimationFrame(arrancarMarca);
		});
	}

	// ---------- Ir de una foto a otra ----------

	var indice = 0;
	var enMovimiento = false;
	var temporizadorMovimiento = null;
	var soportaSuave = 'scrollBehavior' in document.documentElement.style;

	function indiceActual() {
		return Math.round(galeria.scrollLeft / galeria.clientWidth);
	}

	function irA(nuevo) {
		nuevo = Math.max(0, Math.min(paneles.length - 1, nuevo));
		if (nuevo === indice && enMovimiento) return;

		indice = nuevo;
		enMovimiento = true;

		var destino = indice * galeria.clientWidth;
		if (soportaSuave) {
			galeria.scrollTo({ left: destino, behavior: 'smooth' });
		} else {
			galeria.scrollLeft = destino;
		}

		// Ventana en la que se ignoran gestos nuevos, para que un solo
		// giro de rueda (o el rebote del trackpad) no salte varias fotos.
		clearTimeout(temporizadorMovimiento);
		temporizadorMovimiento = setTimeout(function () {
			enMovimiento = false;
		}, 620);
	}

	// ---------- Rueda: un giro = una foto ----------

	galeria.addEventListener('wheel', function (e) {
		// Si el gesto ya es horizontal (trackpad), dejarlo pasar tal cual.
		if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;

		e.preventDefault();
		if (enMovimiento) return;   // todavia esta viajando: ignorar
		if (e.deltaY === 0) return;

		// OJO: no filtrar por el tamano de deltaY. Segun el navegador y el
		// mouse, la rueda informa PIXELES (deltaMode 0) o LINEAS (deltaMode 1,
		// donde un clic de rueda es apenas 3). Con un umbral, en esos casos
		// se descartaban todos los giros y la galeria no se movia nunca.
		// Alcanza con el signo para saber para donde ir.
		indice = indiceActual();
		irA(indice + (e.deltaY > 0 ? 1 : -1));
	}, { passive: false });

	// ---------- Arrastrar ----------

	var arrastrando = false;
	var inicioX = 0;
	var inicioScroll = 0;
	var seMovio = false;

	galeria.addEventListener('mousedown', function (e) {
		arrastrando = true;
		seMovio = false;
		inicioX = e.clientX;
		inicioScroll = galeria.scrollLeft;
		galeria.classList.add('esta-arrastrando');
	});

	window.addEventListener('mousemove', function (e) {
		if (!arrastrando) return;
		var avance = e.clientX - inicioX;
		if (Math.abs(avance) > 3) seMovio = true;
		galeria.scrollLeft = inicioScroll - avance;
	});

	window.addEventListener('mouseup', function () {
		if (!arrastrando) return;
		arrastrando = false;
		galeria.classList.remove('esta-arrastrando');
		// Al soltar, acomodar en la foto mas cercana.
		irA(indiceActual());
	});

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
