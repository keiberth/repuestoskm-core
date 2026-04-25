document.addEventListener("DOMContentLoaded", function () {
    document.documentElement.classList.add("rkm-sellers-ready");

    var ordersTable = document.querySelector(".rkm-sellers-orders-table");

    if (ordersTable) {
        ordersTable.setAttribute("data-rkm-sellers-table-ready", "true");
    }

    var historySelect = document.querySelector("[data-rkm-sellers-history-select]");

    if (historySelect) {
        historySelect.addEventListener("change", function () {
            if (historySelect.form) {
                historySelect.form.submit();
            }
        });
    }
});
