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
        var paymentTerm = document.getElementById("rkmOperationalOrderPaymentTerm");
        var paymentMethod = document.getElementById("rkmOperationalOrderPaymentMethod");
        var total = document.getElementById("rkmOperationalOrderTotal");
        var items = document.getElementById("rkmOperationalOrderItems");
        var notes = document.getElementById("rkmOperationalOrderNotes");
        var modalConfirmButton = document.getElementById("rkmOperationalOrderConfirmBtn");
        var modalWarehouseButton = document.getElementById("rkmOperationalOrderWarehouseBtn");
        var reviewCount = document.querySelector("[data-rkm-review-count]");
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
            return textarea.value;
        }

        function escapeDisplay(value) {
            return escapeHtml(decodeHtml(value));
        }

        function renderItems(order) {
            var orderItems = Array.isArray(order.items) ? order.items : [];

            if (!orderItems.length) {
                items.innerHTML = '<p class="rkm-admin-order-modal__empty">No hay productos registrados en este pedido.</p>';
                return;
            }

            items.innerHTML = orderItems.map(function (item) {
                var sku = item.sku ? '<small>SKU: ' + escapeHtml(item.sku) + '</small>' : "";

                return [
                    '<div class="rkm-admin-order-modal__item">',
                        '<div>',
                            '<strong>' + escapeHtml(item.name) + '</strong>',
                            sku,
                        '</div>',
                        '<div class="rkm-admin-order-modal__item-values">',
                            '<span><em>Cantidad</em>' + escapeDisplay(item.quantity) + '</span>',
                            '<span><em>Unitario</em>' + escapeDisplay(item.unit_price) + '</span>',
                            '<strong><em>Subtotal</em>' + escapeDisplay(item.total) + '</strong>',
                        '</div>',
                    '</div>'
                ].join("");
            }).join("");
        }

        function renderNotes(order) {
            var noteBlocks = [];

            if (order.payment_note) {
                noteBlocks.push({
                    label: "Observacion de pago",
                    content: order.payment_note
                });
            }

            if (order.customer_note) {
                noteBlocks.push({
                    label: "Nota del pedido",
                    content: order.customer_note
                });
            }

            if (Array.isArray(order.internal_notes)) {
                order.internal_notes.forEach(function (note) {
                    if (!note.content) {
                        return;
                    }

                    noteBlocks.push({
                        label: note.date || "Nota interna",
                        content: note.content
                    });
                });
            }

            if (!noteBlocks.length) {
                notes.innerHTML = '<p class="rkm-admin-order-modal__empty">No hay notas internas registradas.</p>';
                return;
            }

            notes.innerHTML = noteBlocks.map(function (note) {
                return [
                    '<div class="rkm-admin-order-modal__note">',
                        '<span>' + escapeHtml(note.label) + '</span>',
                        '<p>' + escapeHtml(note.content) + '</p>',
                    '</div>'
                ].join("");
            }).join("");
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
            button.className = "rkm-admin-orders__btn rkm-admin-orders__btn--primary rkm-admin-orders-warehouse-btn";
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

            if (rowBadge) {
                rowBadge.className = className;
                rowBadge.textContent = order.status_label || order.status || "Estado";
            }

            if (String(activeOrderId) === String(order.id)) {
                status.className = className;
                status.textContent = order.status_label || order.status || "Estado";
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
                modalConfirmButton.setAttribute("data-order-id", order.id || "");
            }

            if (modalWarehouseButton) {
                modalWarehouseButton.hidden = !isWarehouseSendable(order);
                modalWarehouseButton.setAttribute("data-order-id", order.id || "");
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

            ordersById[String(order.id)] = order;
            orders = orders.map(function (current) {
                return String(current.id) === String(order.id) ? order : current;
            });

            refreshStatusBadge(order);
            refreshConfirmControls(order);

            if (String(activeOrderId) === String(order.id)) {
                renderNotes(order);
            }

            if (wasReview) {
                decrementReviewCount();
            }
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
            paymentTerm.textContent = "Condicion: " + (order.payment_term || "-");
            paymentMethod.textContent = "Forma: " + (order.payment_method || "-");
            total.textContent = decodeHtml(order.total || "-");

            renderItems(order);
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
