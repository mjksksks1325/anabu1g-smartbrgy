try {
    const theme = localStorage.getItem('smartbrgy_theme') === 'dark' ? 'dark' : 'light';
    localStorage.setItem('flux.appearance', theme);
} catch (_) {
    // Keep the browser's current appearance when storage is unavailable.
}

if (typeof document !== 'undefined') {
    const syncThemeButton = () => {
        const button = document.getElementById('staff-settings-theme-toggle');
        if (!button) return;
        try {
            button.textContent = localStorage.getItem('smartbrgy_theme') === 'dark' ? 'Light Mode' : 'Dark Mode';
        } catch (_) {
            button.textContent = 'Dark Mode';
        }
    };

    document.addEventListener('DOMContentLoaded', syncThemeButton);
    document.addEventListener('livewire:navigated', syncThemeButton);
    document.addEventListener('click', event => {
        if (!event.target.closest?.('#staff-settings-theme-toggle')) return;
        let theme = 'dark';
        try {
            theme = localStorage.getItem('smartbrgy_theme') === 'dark' ? 'light' : 'dark';
            localStorage.setItem('smartbrgy_theme', theme);
        } catch (_) {
            theme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
        }
        window.Flux?.applyAppearance?.(theme);
        document.documentElement.classList.toggle('dark', theme === 'dark');
        syncThemeButton();
    });
}
