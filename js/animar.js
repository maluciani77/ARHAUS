let animado = document.querySelectorAll(".animado");

function mostrarScroll() {
	let scrollTop = document.documentElement.scrollTop;
	for (var i=0; i<animado.length; i++) {
		let alturaAnimado = animado[i].offsetTop;
		if(alturaAnimado - 500 < scrollTop) {
			animado[i].style.opacity = 1;
            animado[i].classList.add("mostrarArriba");
		}
	}
}

window.addEventListener('scroll', mostrarScroll);
mostrarScroll(); // por si la página carga ya scrolleada

// Nota: acá había una inicialización del plugin "slick" sobre
// .project-detail / .project-strip. Ni esos elementos ni el plugin
// existen en el sitio, así que solo tiraba un error en cada carga.
