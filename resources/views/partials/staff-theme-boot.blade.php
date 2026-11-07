{{--
    Staff theme, applied in <head> before first paint so a page never flashes the wrong theme.
    `smartbrgy_theme` stays the staff preference (light when unset, as before). An account whose only saved
    choice is the Flux appearance setting (`flux.appearance`) keeps that choice. The Flux key is kept in step,
    so the staff sign-in and account settings pages, which read it, match the workspace.
    public/js/personnel-navigation.js keeps the same keys when the theme is toggled.
--}}
<script>
    (() => {
        let theme = 'light';
        try {
            const staffTheme = localStorage.getItem('smartbrgy_theme');
            const fluxAppearance = localStorage.getItem('flux.appearance');
            theme = staffTheme === 'dark' || staffTheme === 'light' ? staffTheme : (fluxAppearance === 'dark' ? 'dark' : 'light');
            localStorage.setItem('smartbrgy_theme', theme);
            localStorage.setItem('flux.appearance', theme);
        } catch (_) {
            // Storage is unavailable (private mode or blocked): use the light theme for this page only.
        }
        document.documentElement.dataset.theme = theme;
        document.documentElement.classList.toggle('dark', theme === 'dark');
    })();
</script>
