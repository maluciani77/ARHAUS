$(document).ready(function() {
var scroll = $(window).scrollTop();
$('#posicion').text(scroll);
$(window).scroll(function(event) {
    var scroll = $(window).scrollTop();
    $('#posicion').text(scroll);
    /*Cambia el color del div cuando es distinto a 0*/
    if($(window).width() > 700){
        if(scroll>670){
        $('.topnav a').css({
            color: '#1a1a1a'
        })
    }else{
        $('.topnav a').css({
            color: 'white'
        });
    }
}});
});


