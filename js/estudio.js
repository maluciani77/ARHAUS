$(window).on("load", function(){
    let animacion = document.getElementById('titulo_animado');
    let posicionObjeto = animacion.getBoundingClientRect().top;
    console.log(posicionObjeto);
    let tamañoDePantalla =  window.innerHeight/1.5;
    
    if(posicionObjeto < tamañoDePantalla) {
        animacion.style.animation = 'aumentar 4s ease-out'
    }
});