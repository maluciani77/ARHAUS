// ========================================
// MAQUETA 3D EN LA PÁGINA DEL PROYECTO
// Carga el modelo recién cuando el visitante lo pide (pesa unos 15 MB) y
// le aplica los colores que el admin guardó desde el panel.
// ========================================

document.addEventListener('DOMContentLoaded', function () {
	var visor = document.getElementById('maqueta3d-visor');
	var portada = document.getElementById('maqueta3d-portada');
	if (!visor || !portada) return;

	var MODELO = 'maquetas/lote-424/maqueta.glb';
	var CONFIG = 'maquetas/lote-424/config.json';
	var cargando = false;

	visor.addEventListener('load', function () {
		var materiales = Maqueta3D.leerMateriales(visor);
		Maqueta3D.cargarConfig(CONFIG).then(function (config) {
			Maqueta3D.aplicar(visor, materiales, config);
			Maqueta3D.ubicarCamara(visor, config);
			portada.remove();
		});
	});

	portada.addEventListener('click', function () {
		if (cargando) return;
		cargando = true;
		portada.querySelector('span').textContent = 'Cargando la maqueta…';
		visor.src = MODELO;
	});

	visor.addEventListener('error', function () {
		cargando = false;
		portada.querySelector('span').textContent = 'No se pudo cargar la maqueta. Probá de nuevo.';
	});
});
