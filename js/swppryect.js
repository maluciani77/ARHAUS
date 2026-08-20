import Swiper from 'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.mjs'

new Swiper(".swiper", {
   
    slidesPerView: 2,
    spaceBetween: 5,
    direction: "horizontal",
    allowTouchMove: true,
    // loop: true,
    // autoplay: true,
    //parallax: true,
    // autoplay: {
    //     delay: 2500,
    // },
});

let box = document.querySelectorAll('.box');
box.forEach(popup => popup.addEventListener('click', () => {
    popup.classList.toggle('active')
}))