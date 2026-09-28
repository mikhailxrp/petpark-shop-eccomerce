(() => {
    const slider = document.querySelector('.hero-two-slider');
    const cta = document.querySelector('.hero-two-cta');

    if (!slider || !cta) {
        return;
    }

    const relocateNav = () => {
        const nav = slider.querySelector('.owl-nav');
        if (!nav || cta.contains(nav)) {
            return false;
        }

        cta.appendChild(nav);
        return true;
    };

    if (relocateNav()) {
        return;
    }

    const observer = new MutationObserver(() => {
        if (relocateNav()) {
            observer.disconnect();
        }
    });
    observer.observe(slider, { childList: true });
})();
