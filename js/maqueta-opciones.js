// ========================================
// MAQUETA 3D (lo usan el panel del admin y la página del proyecto)
//
// El GLB que sale de SimLab trae todos los materiales con el mismo brillo,
// así que cada material se agrupa por su nombre (revoque, techo, madera,
// carpintería, vidrio...) y se le da el acabado y el color que le toca.
// Los mismos grupos están en maquetas/corregir_glb.py.
// ========================================

(function (global) {
	'use strict';

	var GRUPOS = [
		['vidrio',      /glass|translucent|<slategray>|<yellow>/i],
		['agua',        /aquamarine|water|pileta|pool/i],
		['carpinteria', /metal de ventanas|metal_panel|metal_steel|aluminum|aluminio|corrogate|corrugated|acero/i],
		['metal',       /\bmetal\b|metal_|chrome|hrom|<gold>|gold\b|<silver>|silver\b|\*23\b/i],
		['madera',      /wood|madera|madeira|woodgrain|veneer|fencing|oak|roble|\*32\b|\*24\b|\*35\b|aura_media|color_a01/i],
		['techo',       /blacktop|asphalt|membrana|roof/i],
		['piedra',      /stone|granite|granito|paver|flagstone/i],
		['vegetacion',  /vegetation|agapan|heliconia|juniper|palo ?verde|bark|grass|cesped|color g08/i],
		['revoque',     /cladding|stucco|^white$|whitesmoke|coolgray|floralwhite|<auto>|color_002|color_00[56]|color_h10|0129|\*39\b|\*17\b|light oliv/i],
		['pantalla',    /tv_|material-5[789]|material-60|akran|e505/i],
		['tela',        /stacy|sophie|skirt|shirt|pants|hair|skin|shoe/i],
	];

	// rugosidad y metalness por grupo
	var ACABADOS = {
		vidrio: [0.05, 0], agua: [0.04, 0], carpinteria: [0.30, 1], metal: [0.32, 1],
		madera: [0.55, 0], techo: [0.90, 0], piedra: [0.75, 0], vegetacion: [0.90, 0],
		revoque: [0.85, 0], pantalla: [0.30, 0], tela: [0.85, 0], otros: [0.75, 0]
	};

	var LUCES = {
		suave:   { nombre: 'Día suave',   entorno: 'neutral', tono: 'neutral',  sombra: 0.9, suavidad: 1.0, exposicion: 1.00 },
		marcada: { nombre: 'Sol marcado', entorno: 'neutral', tono: 'commerce', sombra: 1.6, suavidad: 0.3, exposicion: 0.95 },
		estudio: { nombre: 'Estudio',     entorno: 'legacy',  tono: 'neutral',  sombra: 1.0, suavidad: 0.8, exposicion: 1.05 }
	};

	var PALETAS = [
		{ id: 'sobria',  nombre: 'Blanco cálido y negro',   revoque: '#f2ece2', techo: '#bdb9b1', carpinteria: '#2c2c2c', madera: '#d9b98f', vidrio: '#dde5e6' },
		{ id: 'calida',  nombre: 'Arena y madera',          revoque: '#e7d9c1', techo: '#b9aa92', carpinteria: '#7a6547', madera: '#d9a765', vidrio: '#e8e0d2' },
		{ id: 'piedra',  nombre: 'Gris piedra y antracita', revoque: '#cdcac3', techo: '#9b9b97', carpinteria: '#3a3d40', madera: '#c3a986', vidrio: '#dbe2e4' },
		{ id: 'minimal', nombre: 'Blanco puro y aluminio',  revoque: '#fafaf8', techo: '#d3d3d0', carpinteria: '#b3b7ba', madera: '#e2cbab', vidrio: '#e2eaec' },
		{ id: 'grafito', nombre: 'Grafito y madera',        revoque: '#5f6263', techo: '#4d5051', carpinteria: '#2b2d2e', madera: '#d9b585', vidrio: '#d6dee0' }
	];

	var POR_DEFECTO = {
		paleta: { revoque: '#f2ece2', techo: '#bdb9b1', carpinteria: '#2c2c2c', madera: '#d9b98f', vidrio: '#dde5e6' },
		luz: 'suave',
		brillo: 1,
		camara: { orbita: '22deg 72deg 52%', fov: '28deg' }
	};

	/** Los colores del glTF van en lineal; los hex de CSS son sRGB. */
	function aLineal(hex) {
		var n = parseInt(String(hex).slice(1), 16);
		return [(n >> 16) & 255, (n >> 8) & 255, n & 255].map(function (v) {
			var s = v / 255;
			return s <= 0.04045 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
		});
	}

	/** Mezcla el color original con el de la paleta (para materiales con textura). */
	function tenir(color, hex, fuerza) {
		var t = aLineal(hex);
		return [
			color[0] * (1 - fuerza) + t[0] * fuerza,
			color[1] * (1 - fuerza) + t[1] * fuerza,
			color[2] * (1 - fuerza) + t[2] * fuerza,
			color[3]
		];
	}

	function grupoDe(nombre) {
		for (var i = 0; i < GRUPOS.length; i++) {
			if (GRUPOS[i][1].test(nombre || '')) return GRUPOS[i][0];
		}
		return 'otros';
	}

	/** Lee los materiales del modelo una sola vez, guardando su color original. */
	function leerMateriales(visor) {
		return visor.model.materials.map(function (mat) {
			var pbr = mat.pbrMetallicRoughness;
			return {
				pbr: pbr,
				color: pbr.baseColorFactor.slice(),
				textura: !!(pbr.baseColorTexture && pbr.baseColorTexture.texture),
				grupo: grupoDe(mat.name)
			};
		});
	}

	/** Aplica la paleta y la luz guardadas al visor. */
	function aplicar(visor, materiales, config) {
		var paleta = Object.assign({}, POR_DEFECTO.paleta, (config && config.paleta) || {});
		var luz = LUCES[(config && config.luz)] || LUCES.suave;
		var brillo = (config && config.brillo) || 1;

		materiales.forEach(function (m) {
			var acabado = ACABADOS[m.grupo] || ACABADOS.otros;
			var nuevo = m.color;

			if (m.grupo === 'revoque') {
				nuevo = m.textura ? tenir(m.color, paleta.revoque, 0.8) : aLineal(paleta.revoque).concat(m.color[3]);
			} else if (m.grupo === 'techo') {
				nuevo = m.textura ? tenir(m.color, paleta.techo, 0.75) : aLineal(paleta.techo).concat(m.color[3]);
			} else if (m.grupo === 'carpinteria') {
				nuevo = m.textura ? tenir(m.color, paleta.carpinteria, 0.75) : aLineal(paleta.carpinteria).concat(m.color[3]);
			} else if (m.grupo === 'madera') {
				nuevo = m.textura ? tenir(m.color, paleta.madera, 0.45) : aLineal(paleta.madera).concat(m.color[3]);
			} else if (m.grupo === 'vidrio') {
				nuevo = aLineal(paleta.vidrio).concat(m.color[3]);
			}

			m.pbr.setBaseColorFactor(nuevo);
			m.pbr.setRoughnessFactor(acabado[0]);
			m.pbr.setMetallicFactor(acabado[1]);
		});

		visor.exposure = luz.exposicion * brillo;
		visor.shadowIntensity = luz.sombra;
		visor.shadowSoftness = luz.suavidad;
		visor.environmentImage = luz.entorno;
		visor.toneMapping = luz.tono;
	}

	/**
	 * Pone la cámara en la vista guardada. Se hace en el cuadro siguiente
	 * porque, apenas termina de cargar, el visor encuadra solo el modelo y
	 * pisaría la vista.
	 */
	function ubicarCamara(visor, config) {
		var camara = (config && config.camara) || POR_DEFECTO.camara;
		requestAnimationFrame(function () {
			if (camara.orbita) visor.cameraOrbit = camara.orbita;
			if (camara.fov) visor.fieldOfView = camara.fov;
			visor.jumpCameraToGoal();
		});
	}

	/** Trae la configuración guardada; si no hay, devuelve la de fábrica. */
	function cargarConfig(url) {
		return fetch(url, { cache: 'no-store' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.catch(function () { return null; })
			.then(function (c) {
				if (!c) return JSON.parse(JSON.stringify(POR_DEFECTO));
				return {
					paleta: Object.assign({}, POR_DEFECTO.paleta, c.paleta || {}),
					luz: LUCES[c.luz] ? c.luz : 'suave',
					brillo: typeof c.brillo === 'number' ? c.brillo : 1,
					camara: Object.assign({}, POR_DEFECTO.camara, c.camara || {}),
					actualizado: c.actualizado || null
				};
			});
	}

	global.Maqueta3D = {
		GRUPOS: GRUPOS,
		LUCES: LUCES,
		PALETAS: PALETAS,
		POR_DEFECTO: POR_DEFECTO,
		leerMateriales: leerMateriales,
		aplicar: aplicar,
		ubicarCamara: ubicarCamara,
		cargarConfig: cargarConfig
	};
})(window);
