(function () {
    const slider = document.querySelector('.hero-slider');
    if (!slider) return;

    const slides = slider.querySelectorAll('.slide');
    const dots = slider.querySelectorAll('.dot');
    const prevBtn = slider.querySelector('.slider-prev');
    const nextBtn = slider.querySelector('.slider-next');

    if (slides.length <= 1) return;

    let current = 0;
    let autoplayTimer = null;
    const INTERVAL = 5000;

    function goTo(index) {
        slides[current].classList.remove('active');
        slides[current].setAttribute('aria-hidden', 'true');
        if (dots[current]) {
            dots[current].classList.remove('active');
            dots[current].setAttribute('aria-selected', 'false');
        }

        current = (index + slides.length) % slides.length;

        slides[current].classList.add('active');
        slides[current].setAttribute('aria-hidden', 'false');
        if (dots[current]) {
            dots[current].classList.add('active');
            dots[current].setAttribute('aria-selected', 'true');
        }
    }

    function next() { goTo(current + 1); }
    function prev() { goTo(current - 1); }

    function startAutoplay() {
        stopAutoplay();
        autoplayTimer = setInterval(next, INTERVAL);
    }

    function stopAutoplay() {
        if (autoplayTimer) clearInterval(autoplayTimer);
    }

    if (prevBtn) prevBtn.addEventListener('click', function () { prev(); startAutoplay(); });
    if (nextBtn) nextBtn.addEventListener('click', function () { next(); startAutoplay(); });

    dots.forEach(function (dot) {
        dot.addEventListener('click', function () {
            goTo(parseInt(this.dataset.index, 10));
            startAutoplay();
        });
    });

    // Pause on hover
    slider.addEventListener('mouseenter', stopAutoplay);
    slider.addEventListener('mouseleave', startAutoplay);

    // Pause when not visible
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) stopAutoplay();
        else startAutoplay();
    });

    // Touch swipe support
    let touchStartX = 0;
    slider.addEventListener('touchstart', function (e) {
        touchStartX = e.changedTouches[0].screenX;
        stopAutoplay();
    }, { passive: true });

    slider.addEventListener('touchend', function (e) {
        const diff = e.changedTouches[0].screenX - touchStartX;
        if (Math.abs(diff) > 50) {
            if (diff < 0) next();
            else prev();
        }
        startAutoplay();
    }, { passive: true });

    // Keyboard navigation
    slider.setAttribute('tabindex', '0');
    slider.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') { prev(); startAutoplay(); }
        if (e.key === 'ArrowRight') { next(); startAutoplay(); }
    });

    startAutoplay();
})();
