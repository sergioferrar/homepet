class TabelaDefault {
    constructor(config) {
        this.cardContainer = document.getElementById(config.cardContainerId);
        this.pagination = document.getElementById(config.paginationId);
        this.searchInput = document.getElementById(config.searchInputId);
        this.itemsPerPage = config.itemsPerPage || 5;
        this.currentPage = 1;
        this.cards = Array.from(this.cardContainer.querySelectorAll('.card-item'));
        this.filteredCards = this.cards.slice();

        if (this.searchInput) {
            this.searchInput.addEventListener('input', () => {
                this.applyFilter();
                this.goToPage(1);
            });
        }
        this.render();
    }

    applyFilter() {
        const value = this.searchInput.value.trim().toLowerCase();
        if (!value) {
            this.filteredCards = this.cards.slice();
        } else {
            this.filteredCards = this.cards.filter(card =>
                card.getAttribute('data-search').toLowerCase().includes(value)
            );
        }
    }

    goToPage(page) {
        if (page < 1) page = 1;
        if (page > this.totalPages()) page = this.totalPages();
        this.currentPage = page;
        this.render();
    }

    totalPages() {
        return Math.ceil(this.filteredCards.length / this.itemsPerPage) || 1;
    }

    render() {
        // Esconde todos os cards primeiro
        this.cards.forEach(card => card.style.display = 'none');
        // Mostra apenas os que estão na página atual
        const start = (this.currentPage - 1) * this.itemsPerPage;
        const end = start + this.itemsPerPage;
        this.filteredCards.slice(start, end).forEach(card => card.style.display = '');
        this.renderPagination();
    }

    renderPagination() {
        // Limpa a paginação
        this.pagination.innerHTML = '';
        const totalPages = this.totalPages();
        if (totalPages <= 1) return;

        // Botão "Anterior"
        const prevLi = document.createElement('li');
        prevLi.className = 'page-item' + (this.currentPage === 1 ? ' disabled' : '');
        prevLi.innerHTML = `<a class="page-link" href="#">«</a>`;
        prevLi.onclick = e => { e.preventDefault(); if (this.currentPage > 1) this.goToPage(this.currentPage - 1); };
        this.pagination.appendChild(prevLi);

        // Mostra 5 páginas no máximo: [1] ... [n-1] [n] [n+1] ... [total]
        let start = Math.max(1, this.currentPage - 2);
        let end = Math.min(totalPages, this.currentPage + 2);

        if (this.currentPage <= 3) end = Math.min(5, totalPages);
        if (this.currentPage >= totalPages - 2) start = Math.max(1, totalPages - 4);

        if (start > 1) {
            this.pagination.appendChild(this.makePageLi(1));
            if (start > 2) this.pagination.appendChild(this.makeDotsLi());
        }
        for (let i = start; i <= end; i++) {
            this.pagination.appendChild(this.makePageLi(i));
        }
        if (end < totalPages) {
            if (end < totalPages - 1) this.pagination.appendChild(this.makeDotsLi());
            this.pagination.appendChild(this.makePageLi(totalPages));
        }

        // Botão "Próxima"
        const nextLi = document.createElement('li');
        nextLi.className = 'page-item' + (this.currentPage === totalPages ? ' disabled' : '');
        nextLi.innerHTML = `<a class="page-link" href="#">»</a>`;
        nextLi.onclick = e => { e.preventDefault(); if (this.currentPage < totalPages) this.goToPage(this.currentPage + 1); };
        this.pagination.appendChild(nextLi);
    }

    makePageLi(page) {
        const li = document.createElement('li');
        li.className = 'page-item' + (page === this.currentPage ? ' active' : '');
        li.innerHTML = `<a class="page-link" href="#">${page}</a>`;
        li.onclick = e => { e.preventDefault(); this.goToPage(page); };
        return li;
    }
    makeDotsLi() {
        const li = document.createElement('li');
        li.className = 'page-item disabled';
        li.innerHTML = `<span class="page-link">...</span>`;
        return li;
    }
}

window.TabelaDefault = TabelaDefault;
