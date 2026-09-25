// Reveals marked sections as they scroll into view. Content stays visible when
// JavaScript, IntersectionObserver or motion is unavailable.
function initializePortalReveal() {
    const sections = document.querySelectorAll('[data-reveal]');
    const prefersReducedMotion = typeof window.matchMedia === 'function'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!sections.length || prefersReducedMotion || typeof window.IntersectionObserver !== 'function') return false;

    document.documentElement.classList.add('reveal-ready');
    const observer = new window.IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-revealed');
            observer.unobserve(entry.target);
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
    sections.forEach(section => observer.observe(section));
    return true;
}

initializePortalReveal();
