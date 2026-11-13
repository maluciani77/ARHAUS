// ========================================
// MAQUETA 3D DE LA PORTADA
// La casa gira sola. Con mouse ademas se puede agarrar y girarla a mano;
// con el dedo no, porque en el celular el dedo se necesita para pasar de
// una foto a otra y si no uno queda trabado en esta.
// ========================================

document.addEventListener('DOMContentLoaded', function () {
	var maqueta = document.querySelector('.maqueta');
	if (!maqueta) return;

	var hayMouse = window.matchMedia
		? window.matchMedia('(hover: hover) and (pointer: fine)').matches
		: false;

	var pie = document.querySelector('.maqueta__pie');

	if (hayMouse) {
		maqueta.setAttribute('camera-controls', '');
		maqueta.setAttribute('disable-pan', '');
		maqueta.setAttribute('disable-zoom', '');
	} else if (pie) {
		// En el celular no hay mouse que agarre nada, asi que el cartelito
		// no puede decir que la agarren: la casa solo gira sola.
		pie.textContent = 'Maqueta 3D · la casa gira sola';
	}

	// El componente viene de afuera (jsdelivr). Si no llega —cae el CDN, o
	// alguien lo tiene bloqueado— la maqueta nunca se dibujaria y quedaria
	// medio panel vacio: se lo esconde y la foto se queda con todo.
	setTimeout(function () {
		if (!window.customElements || !customElements.get('model-viewer')) {
			var panel = maqueta.closest('.panel--partido');
			if (panel) panel.classList.add('sin-maqueta');
		}
	}, 8000);
});
