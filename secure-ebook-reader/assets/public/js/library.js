/**
 * Filtrage dynamique de la Bibliothèque Client
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('library-search-input');
        const grid = document.getElementById('secure-ebook-grid');

        if (!searchInput || !grid) return;

        const cards = grid.querySelectorAll('.secure-ebook-card');

        searchInput.addEventListener('input', function (e) {
            const query = e.target.value.toLowerCase().trim();

            cards.forEach(card => {
                const titleEl = card.querySelector('.card-title');
                const authorEl = card.querySelector('.card-author');

                const title = titleEl ? titleEl.textContent.toLowerCase() : '';
                const author = authorEl ? authorEl.textContent.toLowerCase() : '';

                if (title.includes(query) || author.includes(query)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
})();
