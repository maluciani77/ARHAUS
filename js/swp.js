import Swiper from 'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.mjs';

new Swiper(".swiper", { 
    direction: "horizontal",
    loop: true,
    autoplay: true,
    allowTouchMove: false,
    autoplay: {
       delay: 5000,
    },
});

new Swiper(".swiper2", { 
    direction: "horizontal",
    loop: true,
    allowTouchMove: true,
    spaceBetween: 5,
    slidesPerView: 2,

    
    
    // autoplay:  {
    //     delay: 3000,
    // },
});
