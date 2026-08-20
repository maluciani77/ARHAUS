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

$(".project-detail").slick({
	slidesToShow: 1,
	arrows: false,
	asNavFor: ".project-strip",
	autoplay: true,
	autoplaySpeed: 3000
});

$(".project-strip").slick({
	slidesToShow: 5,
	slidesToScroll: 1,
	arrows: false,
	asNavFor: ".project-detail",
	dots: false,
	infinite: true,
	centerMode: true,
	focusOnSelect: true
});
