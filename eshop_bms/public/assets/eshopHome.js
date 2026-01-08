document.addEventListener('DOMContentLoaded', function () {
    // eslint-disable-next-line no-new
    new Swiper('.swiper-container', {
        slidesPerView: 1,
        spaceBetween: 0,
        loop: true,
        effect: 'fade',
        fadeEffect: {
            crossFade: true,
        },
        autoplay: {
            delay: 5000,
            disableOnInteraction: false,
        },
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
    });
});

/**
 * Redirects to the selected category page.
 *
 * @param {string|number} category - Category identifier.
 */
function showCategory(category) {
    window.location.href = `category/${category}`;
}