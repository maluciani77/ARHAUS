
// La flecha de "volver arriba" usa jQuery, pero no todas las paginas lo
// cargan (la portada horizontal, por ejemplo). Sin esta guarda el script
// cortaba aca y no llegaba a ocultar la pantalla de carga: la pagina
// quedaba tapada para siempre.
if (typeof window.jQuery !== 'undefined') {
	jQuery(document).ready(function ($) {
		$('.ir-arriba').hide();

		$('.ir-arriba').click(function () {
			$('body, html').animate({ scrollTop: '0px' }, 700);
		});

		$(window).scroll(function () {
			if ($(this).scrollTop() > 230) {
				$('.ir-arriba').fadeIn(300);
			} else {
				$('.ir-arriba').fadeOut(300);
			}
		});
	});
}


// document.querySelectorAll('.swiper-wrapper img').forEach(image =>{
// 	image.onclick = () =>{
// 		document.querySelector('.popup-image').style.display = 'block';
// 		document.querySelector('.popup-image img').src = image.getAttribute('src');
// 	}
// });

// document.querySelector('.popup-image span').onclick = () => {
// 	document.querySelector('.popup-image').style.display = 'none';
// }


function showHide(){
	var container = document.getElementsByClassName("contenido")[0];
	var titnosotros = document.getElementById("tit-nosotros");
	var contenido = document.getElementsByClassName("contenedor")[0];
	// container.style.display="hidde";

	if(container.style.display == "none"){
		// container.style.transition=".6s ease-in-out";
		container.style.display="flex";
		titnosotros.style.marginBottom="60px";
		contenido.style.borderBottom="1px solid #fff";
		contenido.style.paddingBottom="60px";
		
		// document.getElementById("text-btn").
		// innerHTML="...Ver menos";
	}else{
		container.style.display="none";
		// container.style.transition=".6s ease-in-out";
		titnosotros.style.marginBottom="0px";
		contenido.style.paddingBottom="20px";
		// document.getElementById("text-btn").
		// innerHTML="...Ver mas";
		
	}
}

const loader = document.getElementById("preloader");
// const cuerpo = document.querySelector('body');

// Algunas páginas no tienen #preloader: sólo activar el timeout si existe.
if (loader) {
	var loaderYaOculto = false;
	function hideLoader(){
		if (loaderYaOculto) return;
		loaderYaOculto = true;
		// Clase en vez de display:none, para que se desvanezca suave
		// (la transicion vive en el CSS de #preloader.is-oculto).
		loader.classList.add('is-oculto');
		// Aviso para que la portada arranque su animacion recien ahora,
		// y no se pierda por detras del velo de carga.
		document.dispatchEvent(new CustomEvent('arhaus:loader-oculto'));
	}

	// OJO: "window.onload" espera a que terminen de cargar TODOS los
	// recursos de la página (fuentes, CDNs externos, videos, etc.). Si
	// alguno tarda o falla, el preloader se queda trabado para siempre.
	// Con "DOMContentLoaded" alcanza (el HTML ya está listo) y además
	// dejamos una red de seguridad por si algo se cuelga igual.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function(){
			setTimeout(hideLoader, 1100);
		});
	} else {
		setTimeout(hideLoader, 1100);
	}
	setTimeout(hideLoader, 6000); // red de seguridad
}

// Abre el lightbox con la imagen clickeada
const overlay = document.querySelector('.image-container');
const popupImg = document.getElementById('lightbox-img');
const closeBtn = document.querySelector('.popup-close');

// El lightbox nuevo (con #lightbox-img / .popup-close) sólo existe en algunas
// páginas; sin esta guarda, el resto tiraba error de consola y frenaba el script.
if (overlay && popupImg && closeBtn) {
	document.querySelectorAll('.slide-item img').forEach(img => {
	  img.addEventListener('click', () => {
	    popupImg.src = img.src;
	    overlay.classList.add('open');
	    document.body.style.overflow = 'hidden'; // bloquea scroll de fondo
	  });
	});

	// Cerrar con botón
	closeBtn.addEventListener('click', closeLightbox);

	// Cerrar al clickear fuera de la imagen (en el fondo)
	overlay.addEventListener('click', (e) => {
	  if (e.target === overlay) closeLightbox();
	});

	// Cerrar con tecla ESC
	document.addEventListener('keydown', (e) => {
	  if (e.key === 'Escape' && overlay.classList.contains('open')) {
	    closeLightbox();
	  }
	});
}

function closeLightbox() {
  if (overlay) overlay.classList.remove('open');
  document.body.style.overflow = '';
}



// ========================================
// HALO QUE SIGUE AL CURSOR
// Un circulo muy tenue que se arrastra detras del mouse con retraso.
// Solo en equipos con mouse de verdad; nunca bloquea clics.
// ========================================

(function () {
	if (!window.matchMedia) return;

	// Sin mouse (tactil) o si el sistema pide menos animacion: no va.
	var tieneMouse = window.matchMedia('(hover: hover) and (pointer: fine)');
	var menosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)');
	if (!tieneMouse.matches || menosMovimiento.matches) return;

	function iniciar() {
		var halo = document.createElement('div');
		halo.className = 'cursor-halo';
		halo.setAttribute('aria-hidden', 'true');
		document.body.appendChild(halo);

		var destinoX = window.innerWidth / 2;
		var destinoY = window.innerHeight / 2;
		var x = destinoX;
		var y = destinoY;
		var animando = false;
		var primerMovimiento = true;

		function seguir() {
			// Interpolacion: el halo se acerca de a poco, por eso "arrastra".
			x += (destinoX - x) * 0.1;
			y += (destinoY - y) * 0.1;
			halo.style.transform = 'translate(' + x.toFixed(1) + 'px, ' + y.toFixed(1) + 'px)';

			if (Math.abs(destinoX - x) > 0.5 || Math.abs(destinoY - y) > 0.5) {
				requestAnimationFrame(seguir);
			} else {
				animando = false; // ya alcanzo al cursor: frenar el bucle
			}
		}

		// Zona oscura de la pagina (las fotos del hero): ahi el halo
		// aclara en vez de oscurecer. Se recalcula al scrollear/redimensionar.
		var zonaOscura = document.querySelector('.hero-carousel, .galeria-h');
		var limiteOscuro = 0;

		function medirZonaOscura() {
			limiteOscuro = zonaOscura ? zonaOscura.getBoundingClientRect().bottom : 0;
		}

		if (zonaOscura) {
			medirZonaOscura();
			window.addEventListener('scroll', medirZonaOscura, { passive: true });
			window.addEventListener('resize', medirZonaOscura);
		}

		document.addEventListener('mousemove', function (e) {
			destinoX = e.clientX;
			destinoY = e.clientY;

			if (zonaOscura) {
				halo.classList.toggle('sobre-oscuro', e.clientY < limiteOscuro);
			}

			if (primerMovimiento) {
				// La primera vez aparece ya en el cursor: si esperara al
				// bucle, se veria un instante arrancando de la esquina.
				primerMovimiento = false;
				x = destinoX;
				y = destinoY;
				halo.style.transform = 'translate(' + x + 'px, ' + y + 'px)';
			}

			halo.classList.add('is-visible');
			if (!animando) {
				animando = true;
				requestAnimationFrame(seguir);
			}
		}, { passive: true });

		// Al salir de la ventana se apaga, al volver reaparece.
		document.addEventListener('mouseleave', function () {
			halo.classList.remove('is-visible');
		});
	}

	if (document.body) {
		iniciar();
	} else {
		document.addEventListener('DOMContentLoaded', iniciar);
	}
})();
