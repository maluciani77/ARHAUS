'use strict'

// const galery = document.querySelector('.galery');
// const feed = document.querySelector('.contenedor-galery');

// const token = 'IGQWRNWnVIWHhzZA3ZAySVIyQWpjck5USEozalNqUjRKand0VlM0ZAE9MTHZAFTnI0T1plSW9zaDVsZAHFsSncxVGttT09nTjMzVWlwbHFKcjRTX0Rzd3hjMldhempxTU1MR3NQR3J2VnVTV3ZAmMm1VMk9MMjFadnVjSjgZD';
// const url = `https://graph.instagram.com/694617269268990?fields=id,media_type,media_url,username,timestamp&access_token=${token}`;

// fetch(url)
// .then(res => res.json())
// .then(data => CrearHtml(data.data))

// function CrearHtml(data){
//     for (const img of data) {
//         galery.innerHTML += `
//         <div class="image overflow">
//         <img loading="lazy" src="${img.media_url}" alt="nada">
//         <div class="opacity-hover">
//             <a href="${img.permalink}" class="caption">
//                 <p>
//                     ${img.caption.slice(0, 100)}
//                 </p>
//             </a>
//         </div>
//        </div>
//         `;
//     }
// }
document.querySelectorAll('.swiper-wrapper img').forEach(image =>{
	image.onclick = () =>{
		document.querySelector('.popup-image').style.display = 'block';
		document.querySelector('.popup-image img').src = image.getAttribute('src');
	}
});

document.querySelector('.popup-image span').onclick = () => {
	document.querySelector('.popup-image').style.display = 'none';
}
