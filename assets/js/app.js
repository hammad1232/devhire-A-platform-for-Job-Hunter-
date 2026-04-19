document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('themeToggle');
    const root = document.documentElement;
    const storedTheme = localStorage.getItem('devhire-theme') || 'light';

    const applyTheme = (theme) => {
        root.setAttribute('data-bs-theme', theme);
        if (themeToggle) {
            themeToggle.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
        }
    };

    applyTheme(storedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const nextTheme = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            localStorage.setItem('devhire-theme', nextTheme);
            applyTheme(nextTheme);
        });
    }

    document.querySelectorAll('[data-filter-input]').forEach((input) => {
        const targetSelector = input.getAttribute('data-filter-input');
        const target = document.querySelector(targetSelector);
        if (!target) {
            return;
        }

        input.addEventListener('input', () => {
            const term = input.value.toLowerCase().trim();
            target.querySelectorAll('[data-searchable]').forEach((item) => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(term) ? '' : 'none';
            });
        });
    });
});
