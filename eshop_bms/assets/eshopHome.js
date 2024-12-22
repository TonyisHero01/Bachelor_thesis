document.addEventListener('DOMContentLoaded', function () {
    var swiper = new Swiper('.swiper-container', {
        slidesPerView: 1, // 每次显示一个 slide
        spaceBetween: 0, // 移除 slide 之间的间距
        loop: true,
        effect: 'fade', // 使用淡入淡出效果
        fadeEffect: {
            crossFade: true, // 使前一张图片淡出时，后一张图片淡入
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
function showCategory(category) {    
    window.location.href = 'category/' + category;
}

