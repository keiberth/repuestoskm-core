document.addEventListener("DOMContentLoaded", function () {
    const section = document.querySelector(".rkm-order-products");
    const searchForm = document.getElementById("rkmCatalogSearchForm");

    if (!section || !searchForm) {
        return;
    }

    const cards = Array.from(section.querySelectorAll(".rkm-product-card"));
    const liveSearchInput = document.querySelector("[data-rkm-live-search]");
    const stockFilter = document.querySelector("[data-rkm-stock-filter]");
    const visibleCount = section.querySelector("[data-rkm-visible-count]");
    const emptyState = section.querySelector("[data-rkm-empty-state]");
    const pageInput = searchForm.querySelector('input[name="rkm_page"]');

    if (!cards.length || !liveSearchInput || !stockFilter || !visibleCount || !emptyState || !pageInput) {
        return;
    }

    function normalizeValue(value) {
        return String(value || "")
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/\s+/g, " ")
            .trim();
    }

    function getCardSearchText(card) {
        const titleText = card.querySelector(".rkm-product-card__title")?.textContent || "";
        const descriptionText = card.querySelector(".rkm-product-card__excerpt")?.textContent || "";

        return normalizeValue([
            card.dataset.name,
            card.dataset.productName,
            titleText,
            card.dataset.sku,
            card.dataset.productSku,
            card.dataset.description,
            card.dataset.productDescription,
            descriptionText,
        ].join(" "));
    }

    function cardMatchesSearch(card, searchValue) {
        if (!searchValue) {
            return true;
        }

        const searchableText = getCardSearchText(card);

        return searchableText.includes(searchValue);
    }

    function cardMatchesStock(card, stockValue) {
        if (stockValue === "all") {
            return true;
        }

        const stock = Number(card.dataset.stock || 0);

        if (stockValue === "in") {
            return stock > 0;
        }

        if (stockValue === "out") {
            return stock <= 0;
        }

        return true;
    }

    function updateVisibleCount(visibleItems) {
        const label = visibleItems === 1 ? "producto visible" : "productos visibles";
        visibleCount.textContent = `${visibleItems} ${label} en esta página`;
    }

    function applyFilters() {
        const searchValue = normalizeValue(liveSearchInput.value);
        const stockValue = stockFilter.value;

        let visibleItems = 0;

        cards.forEach(function (card) {
            const shouldShow = cardMatchesSearch(card, searchValue) && cardMatchesStock(card, stockValue);

            card.hidden = !shouldShow;

            if (shouldShow) {
                visibleItems += 1;
            }
        });

        updateVisibleCount(visibleItems);
        emptyState.hidden = visibleItems !== 0;

        return visibleItems;
    }

    liveSearchInput.addEventListener("input", applyFilters);
    stockFilter.addEventListener("change", applyFilters);
    searchForm.addEventListener("submit", function () {
        pageInput.value = "1";
    });

    applyFilters();
});
