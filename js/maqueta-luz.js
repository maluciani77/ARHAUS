// ========================================
// LUZ DE ADENTRO DE LA MAQUETA EN BLANCO Y NEGRO
//
// El modelo maqueta-bn.glb ya viene en grises y con las aberturas
// marcadas como emisivas. Pero una maqueta blanca a plena luz no puede
// mostrar luz adentro: la ventana iluminada y la pared dan el mismo
// valor, asi que el efecto desaparece. Por eso, al prender la luz, todo
// lo demas se oscurece; es lo mismo que pasa con una maqueta de verdad,
// que la lamparita se nota de noche y no a pleno sol.
//
// Lo usan la pagina del proyecto y la pieza del mosaico de proyectos.
// ========================================

(function (global) {
	'use strict';

	var LUZ = [1, 0.95, 0.86];   // blanco apenas calido
	var SOMBRA = 0.16;           // cuanto baja el resto con la luz prendida

	/** Separa las aberturas que emiten luz del resto, guardando su gris. */
	function preparar(visor) {
		var piezas = { ventanas: [], resto: [] };
		if (!visor.model) return piezas;
		visor.model.materials.forEach(function (m) {
			var e = m.emissiveFactor;
			if (e && (e[0] > 0 || e[1] > 0 || e[2] > 0)) {
				piezas.ventanas.push(m);
			} else {
				piezas.resto.push({ mat: m, gris: m.pbrMetallicRoughness.baseColorFactor.slice() });
			}
		});
		return piezas;
	}

	/** Prende o apaga la luz de adentro. `brillo` escala la exposicion. */
	function prender(visor, piezas, prendida, brillo) {
		var k = prendida ? SOMBRA : 1;
		var escala = brillo || 1;

		piezas.resto.forEach(function (r) {
			var g = r.gris;
			r.mat.pbrMetallicRoughness.setBaseColorFactor([g[0] * k, g[1] * k, g[2] * k, g[3]]);
		});
		piezas.ventanas.forEach(function (m) {
			m.setEmissiveFactor(prendida ? LUZ : [0, 0, 0]);
			m.pbrMetallicRoughness.setBaseColorFactor(prendida ? [0.02, 0.02, 0.02, 1] : [0.82, 0.82, 0.82, 1]);
		});

		visor.exposure = (prendida ? 1.3 : 0.85) * escala;
		visor.shadowIntensity = prendida ? 0.7 : 1.5;
		visor.shadowSoftness = prendida ? 0.6 : 0.35;
	}

	/**
	 * Para los visores que no tienen boton: en cuanto cargan, quedan con
	 * la luz prendida. Se marca con data-luz-adentro en el HTML.
	 */
	function enchufarSolos() {
		var visores = document.querySelectorAll('model-viewer[data-luz-adentro]');
		Array.prototype.forEach.call(visores, function (visor) {
			visor.addEventListener('load', function () {
				prender(visor, preparar(visor), true, 1);
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', enchufarSolos);
	} else {
		enchufarSolos();
	}

	global.MaquetaLuz = { preparar: preparar, prender: prender };
})(window);
