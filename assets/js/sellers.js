document.addEventListener("DOMContentLoaded", function () {
    document.documentElement.classList.add("rkm-sellers-ready");

    var ordersTable = document.querySelector(".rkm-sellers-orders-table");

    if (ordersTable) {
        ordersTable.setAttribute("data-rkm-sellers-table-ready", "true");
    }

    var historySelect = document.querySelector("[data-rkm-sellers-history-select]");

    if (historySelect) {
        var historyForm = historySelect.form;

        if (historyForm) {
            historyForm.addEventListener("submit", function (event) {
                var formData = new FormData(historyForm);
                var query = new URLSearchParams(formData).toString();
                var targetUrl = historyForm.action.split("#")[0];

                event.preventDefault();
                window.location.href = targetUrl + (query ? "?" + query : "") + "#rkm-seller-customer-history";
            });

            historySelect.addEventListener("change", function () {
                if (typeof historyForm.requestSubmit === "function") {
                    historyForm.requestSubmit();
                    return;
                }

                historyForm.dispatchEvent(new Event("submit", { cancelable: true }));
            });
        }
    }
});
