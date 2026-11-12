// Active le thème choisi et conserve la préférence dans le navigateur.
const themeToggle = document.querySelector('[data-theme-toggle]');

function applyTheme(theme) {
    const isDark = theme === 'dark';
    document.documentElement.dataset.theme = isDark ? 'dark' : 'light';

    if (themeToggle) {
        const icon = themeToggle.querySelector('.tw-toggle-icon');
        const label = themeToggle.querySelector('.tw-toggle-label');
        themeToggle.setAttribute('aria-pressed', String(isDark));
        themeToggle.dataset.state = isDark ? 'dark' : 'light';

        if (icon && label) {
            icon.textContent = isDark ? '☀' : '☾';
            label.textContent = isDark ? 'Thème clair' : 'Thème sombre';
        }
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
