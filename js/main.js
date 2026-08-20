
$(document).ready(function(){
	$('.ir-arriba').hide();
 
	$('.ir-arriba').click(function(){
		$('body, html').animate({
			scrollTop: '0px'
		}, 700);
	});
 
	$(window).scroll(function(){
		if( $(this).scrollTop() > 230 ){
			$('.ir-arriba').fadeIn(300);
		} else {
			$('.ir-arriba').fadeOut(300);
		}
	});
 
});


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
	function hideLoader(){
		loader.style.display = 'none';
		// cuerpo.style.background = 'none';
	}

	// OJO: "window.onload" espera a que terminen de cargar TODOS los
	// recursos de la página (fuentes, CDNs externos, videos, etc.). Si
	// alguno tarda o falla, el preloader se queda trabado para siempre.
	// Con "DOMContentLoaded" alcanza (el HTML ya está listo) y además
	// dejamos una red de seguridad por si algo se cuelga igual.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function(){
			setTimeout(hideLoader, 800);
		});
	} else {
		setTimeout(hideLoader, 800);
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

