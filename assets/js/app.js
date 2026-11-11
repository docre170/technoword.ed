// Active le thème choisi et conserve la préférence dans le navigateur.
const themeToggle = document.querySelector('[data-theme-toggle]');

function applyTheme(theme) {
    const isDark = theme === 'dark';
    document.documentElement.dataset.theme = isDark ? 'dark' : 'light';

    if (themeToggle) {
        themeToggle.setAttribute('aria-pressed', String(isDark));
        themeToggle.innerHTML = isDark
            ? '<span aria-hidden="true">☀</span><span class="d-none d-sm-inline">Thème clair</span>'
            : '<span aria-hidden="true">☾</span><span class="d-none d-sm-inline">Thème sombre</span>';
    }
}

applyTheme(localStorage.getItem('tw-theme') || 'light');

if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem('tw-theme', nextTheme);
        applyTheme(nextTheme);
    });
}
