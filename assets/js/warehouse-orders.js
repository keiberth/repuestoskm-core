(function () {
    document.addEventListener("DOMContentLoaded", function () {
        var config = window.rkmWarehouseOrders || {};
        var modal = document.getElementById("rkmWarehouseOrderModal");
        var triggers = document.querySelectorAll("[data-rkm-warehouse-detail]");

        if (!modal || !triggers.length) {
            return;
        }

        var orders = Array.isArray(config.orders) ? config.orders : [];
        var ordersById = {};
        var activeOrder = null;
        var activeOrderId = null;
        var activeFilter = "warehouse";

        orders.forEach(function (order) {
            ordersById[String(order.id)] = order;
        });

        var title = document.getElementById("rkmWarehouseModalTitle");
        var meta = document.getElementById("rkmWarehouseModalMeta");
        var status = document.getElementById("rkmWarehouseModalStatus");
        var customer = document.getElementById("rkmWarehouseModalCustomer");
        var customerMeta = document.getElementById("rkmWarehouseModalCustomerMeta");
        var currentNote = document.getElementById("rkmWarehouseModalCurrentNote");
        var items = document.getElementById("rkmWarehouseModalItems");
        var notes = document.getElementById("rkmWarehouseModalNotes");
        var noteInput = document.getElementById("rkmWarehouseModalNoteInput");
        var noteButton = document.getElementById("rkmWarehouseModalNoteBtn");
        var readyButton = document.getElementById("rkmWarehouseModalReadyBtn");
        var closeControls = modal.querySelectorAll("[data-rkm-warehouse-close]");
        var filterButtons = document.querySelectorAll("[data-rkm-warehouse-filter]");
        var filterEmpty = document.querySelector("[data-rkm-warehouse-empty]");

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

        function formatStatusLabel(statusValue) {
            if (statusValue === "rkm-warehouse") {
                return "En preparacion";
            }

            if (statusValue === "rkm-ready") {
                return "Listo";
            }

            return statusValue || "Pedido";
        }

        function getFilterStatuses(filter) {
            if (filter === "ready") {
                return ["rkm-ready"];
            }

            return ["rkm-warehouse"];
        }

        function updateCounts() {
            var counts = {
                warehouse: 0,
                ready: 0
            };

            orders.forEach(function (order) {
                if (!order || !order.status) {
                    return;
                }

                if (order.status === "rkm-warehouse") {
                    counts.warehouse += 1;
                }

                if (order.status === "rkm-ready") {
                    counts.ready += 1;
                }
            });

            Object.keys(counts).forEach(function (key) {
                var counter = document.querySelector('[data-rkm-warehouse-filter-count="' + key + '"]');
                if (counter) {
                    counter.textContent = String(counts[key]);
                }
            });
        }

        function applyFilter(filter) {
            activeFilter = filter || "warehouse";
            var visibleCount = 0;
            var allowed = getFilterStatuses(activeFilter);

            document.querySelectorAll("[data-rkm-warehouse-row]").forEach(function (row) {
                var statusValue = row.getAttribute("data-rkm-order-status") || "";
                var shouldShow = allowed.indexOf(statusValue) !== -1;

                row.hidden = !shouldShow;

                if (shouldShow) {
                    visibleCount += 1;
                }
            });

            filterButtons.forEach(function (button) {
                button.classList.toggle("is-active", button.getAttribute("data-rkm-warehouse-filter") === activeFilter);
            });

            if (filterEmpty) {
                filterEmpty.hidden = visibleCount > 0;
            }
        }

        function openModal(orderId) {
            activeOrderId = String(orderId);
            activeOrder = ordersById[activeOrderId] || null;

            if (!activeOrder) {
                return;
            }

            renderModal(activeOrder);
            modal.classList.add("is-open");
            modal.setAttribute("aria-hidden", "false");
            document.body.classList.add("rkm-modal-open");
        }

        function closeModal() {
            modal.classList.remove("is-open");
            modal.setAttribute("aria-hidden", "true");
            document.body.classList.remove("rkm-modal-open");
            activeOrderId = null;
        }

        function renderItems(order) {
            if (!items) {
                return;
            }

            var list = Array.isArray(order.items) ? order.items : [];

            if (!list.length) {
                items.innerHTML = '<p class="rkm-warehouse-modal__empty">No hay productos para mostrar.</p>';
                return;
            }

            items.innerHTML = list.map(function (item) {
                var sku = decodeHtml(item.sku || "").trim();
                return [
                    '<article class="rkm-warehouse-modal__item">',
                        '<div>',
                            '<strong>' + escapeHtml(item.name || "") + '</strong>',
                            sku ? '<small>SKU: ' + escapeHtml(sku) + '</small>' : '',
                        '</div>',
                        '<div class="rkm-warehouse-modal__item-meta">',
                            '<span>Cantidad: ' + escapeHtml(item.quantity || 0) + '</span>',
                            item.line_total ? '<span>Total: ' + escapeHtml(item.line_total) + '</span>' : '',
                        '</div>',
                    '</article>'
                ].join("");
            }).join("");
        }

        function renderTimeline(order) {
            if (!notes) {
                return;
            }

            var timeline = Array.isArray(order.audit_timeline) ? order.audit_timeline : [];

            if (!timeline.length) {
                notes.innerHTML = '<p class="rkm-warehouse-modal__empty">No hay historial operativo registrado.</p>';
                return;
            }

            notes.innerHTML = timeline.map(function (entry) {
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
                    '<article class="rkm-warehouse-modal__note">',
                        '<span>' + escapeHtml(metaBits.join(" · ")) + '</span>',
                        '<strong>' + escapeHtml(actionText) + '</strong>',
                        '<p>' + escapeHtml(detailText) + '</p>',
                    '</article>'
                ].join("");
            }).join("");
        }

        function getLatestObservation(order) {
            var timeline = Array.isArray(order.audit_timeline) ? order.audit_timeline : [];

            for (var i = timeline.length - 1; i >= 0; i -= 1) {
                var entry = timeline[i];
                if (!entry) {
                    continue;
                }

                var action = String(entry.action || "").toLowerCase();
                if (action.indexOf("observacion") !== -1) {
                    return decodeHtml(entry.detail || "");
                }
            }

            return "";
        }

        function updateReadyButton(order) {
            if (!readyButton) {
                return;
            }

            var canMarkReady = Boolean(config.can_manage && order && order.status === "rkm-warehouse");
            readyButton.hidden = !canMarkReady;
            readyButton.disabled = !canMarkReady;
        }

        function renderModal(order) {
            if (title) {
                title.textContent = "#" + (order.number || order.id);
            }

            if (meta) {
                meta.textContent = (order.customer_name || "Cliente") + " · " + (order.date || "");
            }

            if (status) {
                status.textContent = formatStatusLabel(order.status);
                status.className = "rkm-warehouse-status rkm-warehouse-status--" + escapeHtml(order.status || "");
            }

            if (customer) {
                customer.textContent = order.customer_name || "";
            }

            if (customerMeta) {
                customerMeta.textContent = (order.customer_email && order.customer_email !== "-") ? order.customer_email : "Sin email";
            }

            if (currentNote) {
                var noteText = getLatestObservation(order);
                currentNote.textContent = noteText || "Sin observaciones registradas.";
            }

            renderItems(order);
            renderTimeline(order);
            updateReadyButton(order);

            if (noteInput) {
                noteInput.value = "";
            }
        }

        function setRowStatus(order) {
            var row = document.querySelector('[data-rkm-warehouse-row][data-order-id="' + order.id + '"]');

            if (!row) {
                return;
            }

            row.setAttribute("data-rkm-order-status", order.status || "");

            var badge = row.querySelector("[data-rkm-warehouse-status-badge]");
            if (badge) {
                badge.textContent = formatStatusLabel(order.status);
                badge.className = "rkm-warehouse-status rkm-warehouse-status--" + escapeHtml(order.status || "");
            }
        }

        function mergeOrder(updatedOrder) {
            if (!updatedOrder || typeof updatedOrder.id === "undefined") {
                return;
            }

            var id = String(updatedOrder.id);
            ordersById[id] = updatedOrder;

            for (var i = 0; i < orders.length; i += 1) {
                if (String(orders[i].id) === id) {
                    orders[i] = updatedOrder;
                    break;
                }
            }
        }

        function showNotice(message, type) {
            var notice = document.querySelector(".rkm-warehouse-notice");
            if (!notice) {
                notice = document.createElement("div");
                notice.className = "rkm-warehouse-notice";
                document.querySelector(".rkm-warehouse-shell").prepend(notice);
            }

            notice.textContent = message;
            notice.classList.remove("is-success", "is-error");
            notice.classList.add(type === "error" ? "is-error" : "is-success");
        }

        function sendAjax(action, data) {
            var formData = new FormData();

            formData.append("action", action);
            formData.append("nonce", config.nonce || "");

            Object.keys(data || {}).forEach(function (key) {
                formData.append(key, data[key]);
            });

            return fetch(config.ajax_url || "", {
                method: "POST",
                credentials: "same-origin",
                body: formData
            }).then(function (response) {
                return response.json();
            });
        }

        function addNote() {
            if (!activeOrder) {
                return;
            }

            var note = noteInput ? noteInput.value.trim() : "";
            if (!note) {
                showNotice("Escribe una observacion antes de guardar.", "error");
                return;
            }

            sendAjax("rkm_add_warehouse_note", {
                order_id: activeOrder.id,
                note: note
            }).then(function (payload) {
                if (!payload || !payload.success) {
                    throw new Error(payload && payload.data && payload.data.message ? payload.data.message : "No se pudo registrar la observacion.");
                }

                if (Array.isArray(payload.data.timeline)) {
                    activeOrder.audit_timeline = payload.data.timeline;
                    renderTimeline(activeOrder);
                    currentNote.textContent = getLatestObservation(activeOrder) || "Sin observaciones registradas.";
                }

                if (noteInput) {
                    noteInput.value = "";
                }

                showNotice(payload.data.message || "Observacion registrada correctamente.", "success");
            }).catch(function (error) {
                showNotice(error.message || "No se pudo registrar la observacion.", "error");
            });
        }

        function markReady() {
            if (!activeOrder || activeOrder.status !== "rkm-warehouse") {
                showNotice("Solo se puede marcar como preparado un pedido en preparacion.", "error");
                return;
            }

            sendAjax("rkm_mark_order_ready", {
                order_id: activeOrder.id
            }).then(function (payload) {
                if (!payload || !payload.success) {
                    throw new Error(payload && payload.data && payload.data.message ? payload.data.message : "No se pudo marcar el pedido como preparado.");
                }

                var updatedOrder = payload.data.order || null;
                if (updatedOrder) {
                    mergeOrder(updatedOrder);
                    activeOrder = updatedOrder;
                    setRowStatus(updatedOrder);
                    renderModal(updatedOrder);
                    applyFilter(activeFilter);
                }

                showNotice(payload.data.message || "Pedido marcado como preparado.", "success");
            }).catch(function (error) {
                showNotice(error.message || "No se pudo marcar el pedido como preparado.", "error");
            });
        }

        triggers.forEach(function (button) {
            button.addEventListener("click", function () {
                openModal(button.getAttribute("data-order-id"));
            });
        });

        closeControls.forEach(function (button) {
            button.addEventListener("click", closeModal);
        });

        if (noteButton) {
            noteButton.addEventListener("click", addNote);
        }

        if (readyButton) {
            readyButton.addEventListener("click", markReady);
        }

        filterButtons.forEach(function (button) {
            button.addEventListener("click", function () {
                applyFilter(button.getAttribute("data-rkm-warehouse-filter"));
            });
        });

        applyFilter(activeFilter);
        updateCounts();
    });
})();
