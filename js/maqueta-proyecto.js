// ========================================
// MAQUETA 3D COMO PORTADA DE LA FICHA DEL PROYECTO
//
// Al entrar a Pilar del Este lo primero que se ve es la maqueta, no una
// foto. Va en blanco y negro con la luz de adentro prendida, y el
// encuadre inicial sale de lo que el admin guardó en el panel.
// ========================================

document.addEventListener('DOMContentLoaded', function () {
	var visor = document.getElementById('maqueta3d-visor');
	if (!visor) return;

	var CONFIG = 'maquetas/lote-424/config.json';
	var boton = document.getElementById('maqueta3d-luz');
	var piezas = null;
	var prendida = true;
	var brillo = 1;
	var vista = null;

	visor.addEventListener('load', function () {
		var cargando = visor.querySelector('.ficha__cargando');
		if (cargando) cargando.remove();

		piezas = MaquetaLuz.preparar(visor);
		Maqueta3D.cargarConfig(CONFIG).then(function (config) {
			brillo = config.brillo || 1;
			vista = config.camara || {};
			MaquetaLuz.prender(visor, piezas, prendida, brillo);
			encuadrar();
			if (boton) boton.hidden = false;
		});
	});

	/**
	 * La vista guardada está pensada para una pantalla ancha. En el
	 * celular la portada queda alta y angosta, así que hay que alejarse.
	 */
	function encuadrar() {
		var orbita = (vista && vista.orbita) || '22deg 78deg 35m';
		var fov = (vista && vista.fov) || '32deg';

		if (window.innerWidth < 700) {
			orbita = orbita.replace(/([\d.]+)(m|%)/, function (todo, n, unidad) {
				return Math.round(parseFloat(n) * 1.7) + unidad;
			});
			fov = '36deg';
		}

		requestAnimationFrame(function () {
			visor.cameraOrbit = orbita;
			visor.fieldOfView = fov;
			visor.jumpCameraToGoal();
		});
	}

	if (boton) {
		boton.addEventListener('click', function () {
			if (!piezas) return;
			prendida = !prendida;
			MaquetaLuz.prender(visor, piezas, prendida, brillo);
			boton.setAttribute('aria-pressed', prendida ? 'true' : 'false');
			boton.textContent = prendida ? 'Luz de adentro' : 'Luz apagada';
		});
	}
});
