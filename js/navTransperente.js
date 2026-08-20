var nav = document.getElementById('nav');

window.onscroll = function(){
    
    if(window.pageYOffset > 100) {
        nav.style.background = "rgba(2,2,2,0.6)";
    }
    else {
        nav.style.background = "transparent";
    }
}

function visible(){

    document.getElementById('nav').style.background = "rgba(2,2,2,0.6)"
}

function tsp(){

    document.getElementById('nav').style.background = "transparent"
}

let ubicacionPrincipal = window.pageYOffset;
let $nav = document.querySelector('#nav');

window.addEventListener('scroll', function(){
    let ubicacionActual = window.pageYOffset;
    console.log(ubicacionActual);

    if(ubicacionPrincipal >= ubicacionActual) {
        $nav.style.top = "0px";
    } else {
        $nav.style.top = "-76px"
    }

    ubicacionPrincipal = ubicacionActual;
})
