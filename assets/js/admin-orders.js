(function () {
    document.addEventListener("DOMContentLoaded", function () {
        var modal = document.getElementById("rkmOperationalOrderModal");
        var triggers = document.querySelectorAll("[data-rkm-operational-order-detail]");

        if (!modal || !triggers.length || !window.rkmOperationalOrders) {
            return;
        }

        var orders = Array.isArray(window.rkmOperationalOrders.orders)
            ? window.rkmOperationalOrders.orders
            : [];
        var ordersById = {};
        var activeOrderId = null;

        orders.forEach(function (order) {
            ordersById[String(order.id)] = order;
        });

        var title = document.getElementById("rkmOperationalOrderModalTitle");
        var meta = document.getElementById("rkmOperationalOrderModalMeta");
        var status = document.getElementById("rkmOperationalOrderModalStatus");
        var customer = document.getElementById("rkmOperationalOrderCustomer");
        var customerMeta = document.getElementById("rkmOperationalOrderCustomerMeta");
        var seller = document.getElementById("rkmOperationalOrderSeller");
        var sellerMeta = document.getElementById("rkmOperationalOrderSellerMeta");
        var paymentReadonly = document.getElementById("rkmOperationalOrderPaymentReadonly");
        var paymentTerm = document.getElementById("rkmOperationalOrderPaymentTerm");
        var paymentSummaryLines = document.getElementById("rkmOperationalOrderPaymentSummaryLines");
        var total = document.getElementById("rkmOperationalOrderTotal");
        var totalHint = document.getElementById("rkmOperationalOrderTotalHint");
        var items = document.getElementById("rkmOperationalOrderItems");
        var notes = document.getElementById("rkmOperationalOrderNotes");
        var editPanel = document.getElementById("rkmOperationalOrderEditPanel");
        var paymentToggleWrap = document.getElementById("rkmOperationalOrderPaymentToggleWrap");
        var paymentEditToggle = document.getElementById("rkmOperationalOrderPaymentEditToggle");
        var paymentTermInput = document.getElementById("rkmOperationalOrderPaymentTermInput");
        var paymentMethodInput = document.getElementById("rkmOperationalOrderPaymentMethodInput");
        var upfrontInput = document.getElementById("rkmOperationalOrderUpfrontInput");
        var creditBalanceInput = document.getElementById("rkmOperationalOrderCreditBalanceInput");
        var paymentNoteInput = document.getElementById("rkmOperationalOrderPaymentNoteInput");
        var modalSaveButton = document.getElementById("rkmOperationalOrderSaveBtn");
        var modalConfirmButton = document.getElementById("rkmOperationalOrderConfirmBtn");
        var modalWarehouseButton = document.getElementById("rkmOperationalOrderWarehouseBtn");
        var reviewCount = document.querySelector("[data-rkm-review-count]");
        var filterButtons = document.querySelectorAll("[data-rkm-order-filter]");
        var filterEmpty = document.querySelector("[data-rkm-order-filter-empty]");
        var activeFilter = "all";
        var closeControls = modal.querySelectorAll("[data-rkm-operational-order-close]");

        function escapeHtml(value) {
            return String(value || "")
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function decodeHtml(value) {
            var textarea = document.createElement("textarea");
            textarea.innerHTML = String(value || "");
            textarea.innerHTML = textarea.value;
            return textarea.value;
        }

        function escapeDisplay(value) {
            return escapeHtml(decodeHtml(value));
        }

        function getEditableStatuses() {
            return Array.isArray(window.rkmOperationalOrders.editable_statuses)
                ? window.rkmOperationalOrders.editable_statuses
                : [window.rkmOperationalOrders.review_status, "pending", "en-revision"];
        }

        function isEditable(order) {
            return Boolean(
                window.rkmOperationalOrders.can_edit
                && order
                && getEditableStatuses().indexOf(order.status) !== -1
            );
        }

        function formatMoney(amount) {
            var decimals = Number(window.rkmOperationalOrders.currency_decimals || 2);
            var symbol = decodeHtml(window.rkmOperationalOrders.currency_symbol || "$");
            var value = Number(amount || 0);

            return symbol + value.toLocaleString("es-VE", {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }

        function getVisualItemsTotal() {
            var inputs = items.querySelectorAll("[data-rkm-order-item-quantity]");
            var sum = 0;

            inputs.forEach(function (input) {
                var quantity = Math.max(1, parseInt(input.value || "1", 10));
                var unit = Number(input.getAttribute("data-unit-price") || 0);
                sum += unit * quantity;
            });

            return sum;
        }

        function updatePaymentFormState() {
            if (!paymentTermInput) {
                return;
            }

            var paymentEditing = Boolean(paymentEditToggle && paymentEditToggle.checked);
            var term = paymentTermInput.value || "";
            var itemsTotal = getVisualItemsTotal();
            var discountPercent = term === "cash" ? Number(window.rkmOperationalOrders.cash_discount_percent || 0) : 0;
            var discountAmount = Math.min(itemsTotal, Math.max(0, itemsTotal * (discountPercent / 100)));
            var finalTotal = Math.max(0, itemsTotal - discountAmount);
            var upfront = upfrontInput ? Number(upfrontInput.value || 0) : 0;
            var creditBalance = 0;
            var needsPaymentMethod = term === "cash" || term === "mixed";

            if (term === "credit") {
                creditBalance = finalTotal;
            } else if (term === "mixed") {
                creditBalance = Math.max(0, finalTotal - Math.max(0, upfront));
            }

            if (paymentMethodInput) {
                paymentMethodInput.disabled = !paymentEditing || !needsPaymentMethod;
                if (paymentEditing && !needsPaymentMethod) {
                    paymentMethodInput.value = "";
                }
            }

            if (upfrontInput) {
                upfrontInput.disabled = !paymentEditing || term !== "mixed";
                upfrontInput.required = paymentEditing && term === "mixed";
                upfrontInput.max = String(finalTotal);

                if (paymentEditing && term !== "mixed") {
                    upfrontInput.value = "";
                }
            }

            if (paymentTermInput) {
                paymentTermInput.disabled = !paymentEditing;
            }

            if (paymentNoteInput) {
                paymentNoteInput.disabled = !paymentEditing;
            }

            if (creditBalanceInput) {
                creditBalanceInput.value = formatMoney(creditBalance);
            }

            if (paymentEditing && total) {
                total.textContent = formatMoney(finalTotal);
            }

            if (totalHint) {
                totalHint.hidden = true;
                totalHint.textContent = "";
            }
        }

        function renderPaymentSummary(order) {
            var paymentMethodText = decodeHtml(order.payment_method || "").trim();
            var upfrontText = decodeHtml(order.upfront_amount_display || "").trim();
            var creditText = decodeHtml(order.credit_balance_display || "").trim();
            var paymentNoteText = decodeHtml(order.payment_note || "").trim();
            var creditContext = order.credit_context || {};
            var hasCreditTerm = Boolean(creditContext.has_credit);
            var creditNoticeText = decodeHtml(creditContext.notice || "").trim();
            var startedLabelText = decodeHtml(creditContext.started_label || "").trim();
            var dueLabelText = decodeHtml(creditContext.due_label || "").trim();
            var statusLabelText = decodeHtml(creditContext.status_label || "").trim();
            var lines = [];

            if (paymentTerm) {
                paymentTerm.textContent = "Condicion: " + (order.payment_term || "-");
            }

            if (paymentMethodText && paymentMethodText !== "-") {
                lines.push('<span>Forma: ' + escapeHtml(paymentMethodText) + '</span>');
            } else {
                lines.push('<span>Forma: Sin forma de pago</span>');
            }

            if (Number(order.upfront_amount || 0) > 0 && upfrontText !== "") {
                lines.push('<span>Monto inicial: ' + escapeHtml(upfrontText) + '</span>');
            }

            if (Number(order.credit_balance || 0) > 0 && creditText !== "") {
                lines.push('<span>Saldo a credito: ' + escapeHtml(creditText) + '</span>');
            }

            if (paymentNoteText) {
                lines.push('<span>Nota: ' + escapeHtml(paymentNoteText) + '</span>');
            }

            if (hasCreditTerm && creditNoticeText) {
                lines.push('<span class="rkm-admin-order-modal__payment-summary-note">' + escapeHtml(creditNoticeText) + '</span>');
            }

            if (hasCreditTerm && startedLabelText && dueLabelText) {
                lines.push('<span>Entregado: ' + escapeHtml(startedLabelText) + ' | Vence credito: ' + escapeHtml(dueLabelText) + '</span>');
            }

            if (hasCreditTerm && startedLabelText && statusLabelText) {
                lines.push('<span>Estado credito: ' + escapeHtml(statusLabelText) + '</span>');
            }

            if (paymentSummaryLines) {
                paymentSummaryLines.innerHTML = lines.join("");
                paymentSummaryLines.hidden = lines.length === 0;
            }
        }

        function setPaymentEditing(enabled) {
            if (editPanel) {
                editPanel.hidden = !enabled;
                editPanel.setAttribute("aria-hidden", enabled ? "false" : "true");
            }

            if (paymentReadonly) {
                paymentReadonly.hidden = enabled;
            }

            if (paymentEditToggle) {
                paymentEditToggle.checked = enabled;
            }

            updatePaymentFormState();

            if (!enabled && activeOrderId && ordersById[String(activeOrderId)]) {
                var order = ordersById[String(activeOrderId)];

                if (total) {
                    total.textContent = decodeHtml(order.total || "-");
                }
            }

            if (totalHint) {
                totalHint.hidden = true;
                totalHint.textContent = "";
            }
        }

        function renderSellerSummary(order) {
            if (!seller || !sellerMeta) {
                return;
            }

            var sellerName = decodeHtml(order.assigned_vendor_name || "");
            var sellerEmail = decodeHtml(order.assigned_vendor_email || "");
            var sellerRole = decodeHtml(order.assigned_vendor_role || "");
            var hasSeller = Boolean(sellerName);

            seller.textContent = hasSeller ? sellerName : "Sin vendedor asignado";
            sellerMeta.textContent = hasSeller
                ? [sellerEmail, sellerRole].filter(function (value) {
                    return value;
                }).join(" · ") || "Asignado al cliente del pedido."
                : "Sin asignacion comercial para este cliente.";
        }

        function renderItems(order) {
            var orderItems = Array.isArray(order.items) ? order.items : [];
            var editable = isEditable(order);

            if (!orderItems.length) {
                items.innerHTML = '<p class="rkm-admin-order-modal__empty">No hay productos registrados en este pedido.</p>';
                return;
            }

            items.innerHTML = orderItems.map(function (item) {
                var sku = item.sku ? '<small>SKU: ' + escapeHtml(item.sku) + '</small>' : "";
                var max = item.max_quantity !== null && item.max_quantity !== undefined ? Number(item.max_quantity) : null;
                var stock = item.stock_label ? '<small>' + escapeHtml(item.stock_label) + '</small>' : "";
                var quantityControl = editable
                    ? [
                        '<input type="number"',
                            ' min="1"',
                            max !== null ? ' max="' + escapeHtml(max) + '"' : "",
                            ' step="1"',
                            ' value="' + escapeHtml(item.quantity) + '"',
                            ' data-rkm-order-item-quantity',
                            ' data-item-id="' + escapeHtml(item.item_id) + '"',
                            ' data-unit-price="' + escapeHtml(item.unit_price_raw) + '"',
                        '>'
                    ].join("")
                    : escapeDisplay(item.quantity);

                return [
                    '<div class="rkm-admin-order-modal__item">',
                        '<div>',
                            '<strong>' + escapeHtml(item.name) + '</strong>',
                            sku,
                            stock,
                        '</div>',
                        '<div class="rkm-admin-order-modal__item-values">',
                            '<span><em>Cantidad</em>' + quantityControl + '</span>',
                            '<span><em>Unitario</em>' + escapeDisplay(item.unit_price) + '</span>',
                            '<strong><em>Subtotal</em><span data-rkm-order-item-subtotal>' + escapeDisplay(item.total) + '</span></strong>',
                        '</div>',
                    '</div>'
                ].join("");
            }).join("");

            items.querySelectorAll("[data-rkm-order-item-quantity]").forEach(function (input) {
                input.addEventListener("input", function () {
                    var quantity = Math.max(1, parseInt(input.value || "1", 10));
                    var max = input.getAttribute("max");

                    if (max && quantity > Number(max)) {
                        quantity = Number(max);
                        input.value = String(quantity);
                    }

                    var itemNode = input.closest(".rkm-admin-order-modal__item");
                    var subtotal = itemNode ? itemNode.querySelector("[data-rkm-order-item-subtotal]") : null;
                    var unit = Number(input.getAttribute("data-unit-price") || 0);

                    if (subtotal) {
                        subtotal.textContent = formatMoney(unit * quantity);
                    }

                    updatePaymentFormState();
                });
            });
        }

        function renderNotes(order) {
            var history = Array.isArray(order.audit_timeline)
                ? order.audit_timeline
                : (Array.isArray(order.operational_history)
                    ? order.operational_history
                    : (Array.isArray(order.internal_notes) ? order.internal_notes : []));

            if (!history.length) {
                notes.innerHTML = '<p class="rkm-admin-order-modal__empty">No hay historial operativo registrado.</p>';
                return;
            }

            notes.innerHTML = history.map(function (entry) {
                var metaBits = [];
                var dateText = decodeHtml(entry.date || "");
                var userText = decodeHtml(entry.user || "");
                var roleText = decodeHtml(entry.role || "");
                var actionText = decodeHtml(entry.action || "Movimiento operativo");
                var detailText = decodeHtml(entry.detail || "");

                if (dateText) {
                    metaBits.push(dateText);
                }
                if (userText) {
                    metaBits.push(userText);
                }
                if (roleText && roleText !== userText) {
                    metaBits.push(roleText);
                }

                return [
                    '<div class="rkm-admin-order-modal__note rkm-admin-order-modal__history-item">',
                        '<span>' + escapeHtml(metaBits.join(" · ")) + '</span>',
                        '<strong>' + escapeHtml(actionText) + '</strong>',
                        '<p>' + escapeHtml(detailText) + '</p>',
                    '</div>'
                ].join("");
            }).join("");

            notes.scrollTop = 0;
        }

        function isConfirmable(order) {
            var confirmableStatuses = Array.isArray(window.rkmOperationalOrders.confirmable_statuses)
                ? window.rkmOperationalOrders.confirmable_statuses
                : [window.rkmOperationalOrders.review_status, "pending", "en-revision"];

            return Boolean(
                window.rkmOperationalOrders.can_confirm
                && order
                && confirmableStatuses.indexOf(order.status) !== -1
            );
        }

        function isWarehouseSendable(order) {
            return Boolean(
                window.rkmOperationalOrders.can_confirm
                && order
                && order.status === window.rkmOperationalOrders.confirmed_status
            );
        }

        function setButtonLoading(button, isLoading, loadingLabel) {
            if (!button) {
                return;
            }

            button.disabled = isLoading;
            button.classList.toggle("is-loading", isLoading);

            if (isLoading) {
                button.setAttribute("data-original-label", button.textContent);
                button.textContent = loadingLabel || "Procesando...";
            } else if (button.getAttribute("data-original-label")) {
                button.textContent = button.getAttribute("data-original-label");
                button.removeAttribute("data-original-label");
            }
        }

        function showNotice(message, type) {
            var notice = document.querySelector("[data-rkm-admin-orders-notice]");

            if (!notice) {
                notice = document.createElement("div");
                notice.setAttribute("data-rkm-admin-orders-notice", "true");
                notice.className = "rkm-admin-orders-notice";

                var shell = document.querySelector(".rkm-admin-orders-shell");
                if (shell) {
                    shell.insertBefore(notice, shell.firstChild);
                }
            }

            notice.className = "rkm-admin-orders-notice is-" + (type || "success");
            notice.textContent = message;
        }

        function getRow(orderId) {
            var selector = '[data-rkm-operational-order-row][data-order-id="' + String(orderId).replace(/"/g, '\\"') + '"]';
            return document.querySelector(selector);
        }

        function getRowConfirmButton(orderId) {
            var row = getRow(orderId);
            return row ? row.querySelector("[data-rkm-confirm-operational-order]") : null;
        }

        function getRowWarehouseButton(orderId) {
            var row = getRow(orderId);
            return row ? row.querySelector("[data-rkm-send-operational-order-warehouse]") : null;
        }

        function getFilterStatuses(filterKey) {
            var allStatuses = ["rkm-review", "pending", "en-revision", "rkm-confirmed", "rkm-warehouse", "rkm-ready", "rkm-dispatched", "processing"];

            return {
                all: allStatuses,
                pending: ["rkm-review", "pending", "en-revision"],
                confirmed: ["rkm-confirmed"],
                warehouse: ["rkm-warehouse"],
                ready: ["rkm-ready"],
                dispatched: ["rkm-dispatched"]
            }[filterKey] || allStatuses;
        }

        function getFilterCounts() {
            var counts = {
                all: 0,
                pending: 0,
                confirmed: 0,
                warehouse: 0,
                ready: 0,
                dispatched: 0
            };

            orders.forEach(function (order) {
                var status = String(order.status || "");

                if (getFilterStatuses("all").indexOf(status) !== -1) {
                    counts.all += 1;
                }
                if (getFilterStatuses("pending").indexOf(status) !== -1) {
                    counts.pending += 1;
                }
                if (getFilterStatuses("confirmed").indexOf(status) !== -1) {
                    counts.confirmed += 1;
                }
                if (getFilterStatuses("warehouse").indexOf(status) !== -1) {
                    counts.warehouse += 1;
                }
                if (getFilterStatuses("ready").indexOf(status) !== -1) {
                    counts.ready += 1;
                }
                if (getFilterStatuses("dispatched").indexOf(status) !== -1) {
                    counts.dispatched += 1;
                }
            });

            return counts;
        }

        function refreshFilterCounts() {
            var counts = getFilterCounts();

            Object.keys(counts).forEach(function (key) {
                var badge = document.querySelector('[data-rkm-filter-count="' + key + '"]');
                if (badge) {
                    badge.textContent = String(counts[key]);
                }
            });

            if (reviewCount) {
                reviewCount.textContent = String(counts.pending);
            }
        }

        function applyOrderFilter(filterKey) {
            var statuses = getFilterStatuses(filterKey);
            var rows = document.querySelectorAll("[data-rkm-operational-order-row]");
            var visibleRows = 0;

            activeFilter = filterKey || "all";

            filterButtons.forEach(function (button) {
                var isActive = button.getAttribute("data-rkm-order-filter") === activeFilter;
                button.classList.toggle("is-active", isActive);
                button.setAttribute("aria-pressed", isActive ? "true" : "false");
            });

            rows.forEach(function (row) {
                var status = String(row.getAttribute("data-rkm-order-status") || "");
                var visible = statuses.indexOf(status) !== -1;

                row.hidden = !visible;

                if (visible) {
                    visibleRows += 1;
                }
            });

            if (filterEmpty) {
                filterEmpty.hidden = rows.length === 0 || visibleRows > 0;
            }
        }

        function bindWarehouseButton(button) {
            if (!button || button.getAttribute("data-rkm-bound") === "true") {
                return;
            }

            button.setAttribute("data-rkm-bound", "true");
            button.addEventListener("click", function () {
                sendToWarehouse(button.getAttribute("data-order-id"), button);
            });
        }

        function ensureRowWarehouseButton(order) {
            var row = getRow(order.id);
            var actions = row ? row.querySelector(".rkm-admin-orders-actions") : null;

            if (!actions || getRowWarehouseButton(order.id) || !isWarehouseSendable(order)) {
                return;
            }

            var button = document.createElement("button");
            button.type = "button";
            button.className = "rkm-admin-orders__btn rkm-admin-orders__btn--warehouse rkm-admin-orders-warehouse-btn";
            button.setAttribute("data-rkm-send-operational-order-warehouse", "true");
            button.setAttribute("data-order-id", order.id);
            button.textContent = "Enviar a almacen";
            actions.appendChild(button);
            bindWarehouseButton(button);
        }

        function refreshStatusBadge(order) {
            var row = getRow(order.id);
            var rowBadge = row ? row.querySelector("[data-rkm-order-status-badge]") : null;
            var className = "rkm-admin-orders-status rkm-admin-orders-status--" + (order.status || "");

            if (row) {
                row.setAttribute("data-rkm-order-status", order.status || "");
            }

            if (rowBadge) {
                rowBadge.className = className;
                rowBadge.textContent = order.status_label || order.status || "Estado";
            }

            if (String(activeOrderId) === String(order.id)) {
                status.className = className;
                status.textContent = order.status_label || order.status || "Estado";
            }
        }

        function refreshRowSummary(order) {
            var row = getRow(order.id);
            var rowTotal = row ? row.querySelector("[data-rkm-order-row-total]") : null;
            var rowPayment = row ? row.querySelector("[data-rkm-order-row-payment]") : null;

            if (rowTotal) {
                rowTotal.textContent = decodeHtml(order.total || "-");
            }

            if (rowPayment) {
                rowPayment.innerHTML = [
                    '<span>Condicion: ' + escapeHtml(order.payment_term || "-") + '</span>',
                    '<small>Forma: ' + escapeHtml(order.payment_method || "-") + '</small>'
                ].join("");
            }
        }

        function refreshConfirmControls(order) {
            var rowButton = getRowConfirmButton(order.id);
            var rowWarehouseButton = getRowWarehouseButton(order.id);

            if (rowButton && !isConfirmable(order)) {
                rowButton.remove();
            }

            if (rowWarehouseButton && !isWarehouseSendable(order)) {
                rowWarehouseButton.remove();
            }

            ensureRowWarehouseButton(order);

            if (modalConfirmButton) {
                modalConfirmButton.hidden = !isConfirmable(order);
                modalConfirmButton.disabled = !isConfirmable(order);
                modalConfirmButton.setAttribute("data-order-id", order.id || "");
            }

            if (modalWarehouseButton) {
                modalWarehouseButton.hidden = !isWarehouseSendable(order);
                modalWarehouseButton.setAttribute("data-order-id", order.id || "");
                modalWarehouseButton.disabled = !isWarehouseSendable(order);
                modalWarehouseButton.title = isWarehouseSendable(order) ? "" : "Confirma el pedido primero.";
            }

            if (modalSaveButton) {
                modalSaveButton.hidden = !isEditable(order);
                modalSaveButton.disabled = !isEditable(order);
                modalSaveButton.setAttribute("data-order-id", order.id || "");
            }

            if (paymentToggleWrap) {
                paymentToggleWrap.hidden = !isEditable(order);
            }

            if (paymentReadonly) {
                paymentReadonly.hidden = false;
            }

            if (!isEditable(order)) {
                setPaymentEditing(false);
            }
        }

        function decrementReviewCount() {
            if (!reviewCount) {
                return;
            }

            var current = Number(reviewCount.textContent || 0);
            reviewCount.textContent = String(Math.max(0, current - 1));
        }

        function applyUpdatedOrder(order) {
            var previous = ordersById[String(order.id)];
            var confirmableStatuses = Array.isArray(window.rkmOperationalOrders.confirmable_statuses)
                ? window.rkmOperationalOrders.confirmable_statuses
                : [window.rkmOperationalOrders.review_status, "pending", "en-revision"];
            var wasReview = previous && confirmableStatuses.indexOf(previous.status) !== -1;
            var isStillReview = order && confirmableStatuses.indexOf(order.status) !== -1;

            ordersById[String(order.id)] = order;
            orders = orders.map(function (current) {
                return String(current.id) === String(order.id) ? order : current;
            });

            refreshStatusBadge(order);
            refreshRowSummary(order);
            refreshConfirmControls(order);
            refreshFilterCounts();
            applyOrderFilter(activeFilter);

            if (String(activeOrderId) === String(order.id)) {
                renderSellerSummary(order);
                renderPaymentSummary(order);
                total.textContent = decodeHtml(order.total || "-");
                renderItems(order);
                if (isEditable(order)) {
                    hydratePaymentForm(order);
                } else {
                    setPaymentEditing(false);
                }
                renderNotes(order);
                refreshConfirmControls(order);
            }

            if (wasReview && !isStillReview) {
                decrementReviewCount();
            }
        }

        function hydratePaymentForm(order) {
            if (!paymentTermInput) {
                return;
            }

            paymentTermInput.value = order.payment_term_key || "";

            if (!paymentTermInput.value && paymentTermInput.options.length) {
                paymentTermInput.selectedIndex = 0;
            }

            if (paymentMethodInput) {
                paymentMethodInput.value = order.payment_method_id || "";
            }

            if (upfrontInput) {
                upfrontInput.value = order.upfront_amount ? String(order.upfront_amount) : "";
            }

            if (paymentNoteInput) {
                paymentNoteInput.value = order.payment_note || "";
            }

            setPaymentEditing(false);
            updatePaymentFormState();
        }

        function confirmOrder(orderId, sourceButton) {
            var order = ordersById[String(orderId)];

            if (!isConfirmable(order)) {
                showNotice("Este pedido no puede confirmarse desde su estado actual.", "error");
                return;
            }

            var modalButtonVisible = modalConfirmButton && String(modalConfirmButton.getAttribute("data-order-id")) === String(orderId);
            var rowButton = getRowConfirmButton(orderId);
            var buttons = [sourceButton, rowButton, modalButtonVisible ? modalConfirmButton : null].filter(function (button, index, list) {
                return button && list.indexOf(button) === index;
            });
            var formData = new FormData();

            formData.append("action", "rkm_confirm_operational_order");
            formData.append("order_id", orderId);
            formData.append("nonce", window.rkmOperationalOrders.nonce || "");

            buttons.forEach(function (button) {
                setButtonLoading(button, true, "Confirmando...");
            });

            fetch(window.rkmOperationalOrders.ajax_url, {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !payload.success) {
                        throw new Error(payload && payload.data && payload.data.message ? payload.data.message : "No se pudo confirmar el pedido.");
                    }

                    applyUpdatedOrder(payload.data.order);
                    showNotice(payload.data.message || "Pedido confirmado correctamente.", "success");
                })
                .catch(function (error) {
                    showNotice(error.message || "No se pudo confirmar el pedido.", "error");
                })
                .finally(function () {
                    buttons.forEach(function (button) {
                        setButtonLoading(button, false);
                    });
                });
        }

        function sendToWarehouse(orderId, sourceButton) {
            var order = ordersById[String(orderId)];

            if (!isWarehouseSendable(order)) {
                showNotice("Solo se pueden enviar a almacen pedidos confirmados.", "error");
                return;
            }

            var modalButtonVisible = modalWarehouseButton && String(modalWarehouseButton.getAttribute("data-order-id")) === String(orderId);
            var rowButton = getRowWarehouseButton(orderId);
            var buttons = [sourceButton, rowButton, modalButtonVisible ? modalWarehouseButton : null].filter(function (button, index, list) {
                return button && list.indexOf(button) === index;
            });
            var formData = new FormData();

            formData.append("action", "rkm_send_operational_order_to_warehouse");
            formData.append("order_id", orderId);
            formData.append("nonce", window.rkmOperationalOrders.nonce || "");

            buttons.forEach(function (button) {
                setButtonLoading(button, true, "Enviando...");
            });

            fetch(window.rkmOperationalOrders.ajax_url, {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !payload.success) {
                        throw new Error(payload && payload.data && payload.data.message ? payload.data.message : "No se pudo enviar el pedido a almacen.");
                    }

                    applyUpdatedOrder(payload.data.order);
                    showNotice(payload.data.message || "Pedido enviado a almacen correctamente.", "success");
                })
                .catch(function (error) {
                    showNotice(error.message || "No se pudo enviar el pedido a almacen.", "error");
                })
                .finally(function () {
                    buttons.forEach(function (button) {
                        setButtonLoading(button, false);
                    });
                });
        }

        function saveOrder(orderId, sourceButton) {
            var order = ordersById[String(orderId)];

            if (!isEditable(order)) {
                showNotice("Este pedido no puede editarse desde su estado actual.", "error");
                return;
            }

            var formData = new FormData();
            var valid = true;

            formData.append("action", "rkm_update_operational_order");
            formData.append("order_id", orderId);
            formData.append("nonce", window.rkmOperationalOrders.nonce || "");

            items.querySelectorAll("[data-rkm-order-item-quantity]").forEach(function (input) {
                var itemId = input.getAttribute("data-item-id");
                var quantity = Math.max(1, parseInt(input.value || "1", 10));
                var max = input.getAttribute("max");

                if (max && quantity > Number(max)) {
                    valid = false;
                }

                formData.append("items[" + itemId + "]", String(quantity));
            });

            if (!valid) {
                showNotice("Hay cantidades que superan el stock disponible.", "error");
                return;
            }

            formData.append("payment_update_enabled", paymentEditToggle && paymentEditToggle.checked ? "1" : "0");

            if (paymentEditToggle && paymentEditToggle.checked) {
                formData.append("payment_term", paymentTermInput ? paymentTermInput.value : "");
                formData.append("payment_method_id", paymentMethodInput && !paymentMethodInput.disabled ? paymentMethodInput.value : "");
                formData.append("upfront_amount", upfrontInput && !upfrontInput.disabled ? upfrontInput.value : "");
                formData.append("payment_note", paymentNoteInput ? paymentNoteInput.value : "");
            }

            setButtonLoading(sourceButton, true, "Guardando...");

            fetch(window.rkmOperationalOrders.ajax_url, {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !payload.success) {
                        throw new Error(payload && payload.data && payload.data.message ? payload.data.message : "No se pudo guardar el pedido.");
                    }

                    applyUpdatedOrder(payload.data.order);
                    showNotice(payload.data.message || "Pedido actualizado correctamente.", "success");
                })
                .catch(function (error) {
                    showNotice(error.message || "No se pudo guardar el pedido.", "error");
                })
                .finally(function () {
                    setButtonLoading(sourceButton, false);
                });
        }

        function openModal(order) {
            activeOrderId = order.id;
            title.textContent = "Pedido #" + (order.number || order.id || "");
            meta.textContent = "Fecha: " + (order.date || "Sin fecha");
            status.className = "rkm-admin-orders-status rkm-admin-orders-status--" + (order.status || "");
            status.textContent = order.status_label || order.status || "Estado";
            customer.textContent = order.customer_name || "Cliente sin nombre";
            customerMeta.textContent = [order.customer_email, order.customer_phone].filter(function (value) {
                return value && value !== "-";
            }).join(" - ") || "Sin datos de contacto";
            renderSellerSummary(order);
            renderPaymentSummary(order);
            total.textContent = decodeHtml(order.total || "-");
            if (totalHint) {
                totalHint.hidden = true;
                totalHint.textContent = "";
            }

            renderItems(order);
            if (isEditable(order)) {
                hydratePaymentForm(order);
            } else {
                setPaymentEditing(false);
            }
            renderNotes(order);
            refreshConfirmControls(order);

            modal.classList.add("is-active");
            modal.setAttribute("aria-hidden", "false");
            document.body.classList.add("rkm-admin-order-modal-open");
        }

        function closeModal() {
            activeOrderId = null;
            modal.classList.remove("is-active");
            modal.setAttribute("aria-hidden", "true");
            document.body.classList.remove("rkm-admin-order-modal-open");
        }

        triggers.forEach(function (button) {
            button.addEventListener("click", function () {
                var order = ordersById[String(button.getAttribute("data-order-id"))];

                if (order) {
                    openModal(order);
                }
            });
        });

        document.querySelectorAll("[data-rkm-confirm-operational-order]").forEach(function (button) {
            button.addEventListener("click", function () {
                confirmOrder(button.getAttribute("data-order-id"), button);
            });
        });

        document.querySelectorAll("[data-rkm-send-operational-order-warehouse]").forEach(bindWarehouseButton);

        filterButtons.forEach(function (button) {
            button.addEventListener("click", function () {
                applyOrderFilter(button.getAttribute("data-rkm-order-filter") || "all");
            });
        });

        refreshFilterCounts();
        applyOrderFilter(activeFilter);

        if (modalConfirmButton) {
            modalConfirmButton.addEventListener("click", function () {
                confirmOrder(modalConfirmButton.getAttribute("data-order-id"), modalConfirmButton);
            });
        }

        if (modalWarehouseButton) {
            modalWarehouseButton.addEventListener("click", function () {
                sendToWarehouse(modalWarehouseButton.getAttribute("data-order-id"), modalWarehouseButton);
            });
        }

        if (modalSaveButton) {
            modalSaveButton.addEventListener("click", function () {
                saveOrder(modalSaveButton.getAttribute("data-order-id"), modalSaveButton);
            });
        }

        [paymentTermInput, paymentMethodInput, upfrontInput].forEach(function (control) {
            if (control) {
                control.addEventListener("input", updatePaymentFormState);
                control.addEventListener("change", updatePaymentFormState);
            }
        });

        if (paymentEditToggle) {
            paymentEditToggle.addEventListener("change", function () {
                setPaymentEditing(paymentEditToggle.checked);
            });
        }

        closeControls.forEach(function (control) {
            control.addEventListener("click", closeModal);
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && modal.classList.contains("is-active")) {
                closeModal();
            }
        });
    });
})();
