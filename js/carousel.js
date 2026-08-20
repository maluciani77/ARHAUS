// ========================================
// CARRUSEL AUTOMÁTICO CON CONTROLES
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.carousel-slide');
    const indicators = document.querySelectorAll('.indicator');
    const prevBtn = document.querySelector('.carousel-control.prev');
    const nextBtn = document.querySelector('.carousel-control.next');

    let currentSlide = 0;
    let autoplayInterval;
    const autoplayDelay = 5000; // 5 segundos

    // Función para mostrar slide
    function showSlide(index) {
        // Remover active de todos
        slides.forEach(slide => slide.classList.remove('active'));
        indicators.forEach(indicator => indicator.classList.remove('active'));

        // Normalizar índice (circular)
        if (index >= slides.length) {
            currentSlide = 0;
        } else if (index < 0) {
            currentSlide = slides.length - 1;
        } else {
            currentSlide = index;
        }

        // Activar el slide actual
        slides[currentSlide].classList.add('active');
        indicators[currentSlide].classList.add('active');
    }

    // Función para avanzar al siguiente
    function nextSlide() {
        showSlide(currentSlide + 1);
    }

    // Función para retroceder
    function prevSlide() {
        showSlide(currentSlide - 1);
    }

    // Autoplay
    function startAutoplay() {
        autoplayInterval = setInterval(nextSlide, autoplayDelay);
    }

    function stopAutoplay() {
        clearInterval(autoplayInterval);
    }

    // Event listeners para controles
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            nextSlide();
            stopAutoplay();
            startAutoplay(); // Reiniciar autoplay
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            prevSlide();
            stopAutoplay();
            startAutoplay();
        });
    }

    // Event listeners para indicadores
    indicators.forEach((indicator, index) => {
        indicator.addEventListener('click', () => {
            showSlide(index);
            stopAutoplay();
            startAutoplay();
        });
    });

    // Pausar autoplay al hacer hover
    const carousel = document.querySelector('.hero-carousel');
    if (carousel) {
        carousel.addEventListener('mouseenter', stopAutoplay);
        carousel.addEventListener('mouseleave', startAutoplay);
    }

    // Soporte para teclado
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') {
            prevSlide();
            stopAutoplay();
            startAutoplay();
        } else if (e.key === 'ArrowRight') {
            nextSlide();
            stopAutoplay();
            startAutoplay();
        }
    });

    // Soporte para touch/swipe en móvil
    let touchStartX = 0;
    let touchEndX = 0;

    if (carousel) {
        carousel.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        });

        carousel.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        });
    }

    function handleSwipe() {
        if (touchEndX < touchStartX - 50) {
            // Swipe left
            nextSlide();
            stopAutoplay();
            startAutoplay();
        }
        if (touchEndX > touchStartX + 50) {
            // Swipe right
            prevSlide();
            stopAutoplay();
            startAutoplay();
        }
    }

    // Iniciar autoplay
    startAutoplay();
});
