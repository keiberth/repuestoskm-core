(function () {
    document.addEventListener("DOMContentLoaded", function () {
        var config = window.rkmWarehouseOrders || {};
        var modal = document.getElementById("rkmWarehouseOrderModal");
        var triggers = document.querySelectorAll("[data-rkm-warehouse-detail]");
        var allowedWarehouseActions = [
            "rkm_add_warehouse_note",
            "rkm_save_warehouse_picking_progress",
            "rkm_report_warehouse_picking_incident",
            "rkm_mark_order_ready",
            "rkm_mark_order_dispatched",
            "rkm_mark_order_delivered"
        ];

        if (!modal || !triggers.length) {
            return;
        }

        var orders = Array.isArray(config.orders) ? config.orders : [];
        var ordersById = {};
        var activeOrder = null;
        var activeOrderId = null;
        var activeFilter = "warehouse";
        var pickingState = [];
        var incidentState = [];
        var evidenceFiles = [];
        var evidencePreviewUrls = [];
        var isMarkingReady = false;
        var isMarkingDispatched = false;
        var isMarkingDelivered = false;
        var isSavingProgress = false;
        var savedPickingSignature = "";

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
        var evidence = document.getElementById("rkmWarehouseEvidence");
        var closure = document.getElementById("rkmWarehouseClosure");
        var notes = document.getElementById("rkmWarehouseModalNotes");
        var noteInput = document.getElementById("rkmWarehouseModalNoteInput");
        var noteButton = document.getElementById("rkmWarehouseModalNoteBtn");
        var saveProgressButton = document.getElementById("rkmWarehouseModalSaveProgressBtn");
        var saveProgressStatus = document.getElementById("rkmWarehousePickingSaveStatus");
        var readyButton = document.getElementById("rkmWarehouseModalReadyBtn");
        var dispatchButton = document.getElementById("rkmWarehouseModalDispatchBtn");
        var deliverButton = document.getElementById("rkmWarehouseModalDeliverBtn");
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

        function escapeAttribute(value) {
            return escapeHtml(value).replace(/`/g, "&#096;");
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

            if (statusValue === "rkm-dispatched") {
                return "Despachado";
            }

            if (statusValue === "completed") {
                return "Entregado";
            }

            return statusValue || "Pedido";
        }

        function getFilterStatuses(filter) {
            if (filter === "ready") {
                return ["rkm-ready"];
            }

            if (filter === "dispatched") {
                return ["rkm-dispatched"];
            }

            if (filter === "completed") {
                return ["completed"];
            }

            return ["rkm-warehouse"];
        }

        function updateCounts() {
            var counts = {
                warehouse: 0,
                ready: 0,
                dispatched: 0,
                completed: 0
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

                if (order.status === "rkm-dispatched") {
                    counts.dispatched += 1;
                }

                if (order.status === "completed") {
                    counts.completed += 1;
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
            clearEvidencePreviewUrls();
            evidenceFiles = [];
            activeOrderId = null;
        }

        function normalizeQuantity(value) {
            var number = Number(value);
            return Number.isFinite(number) ? number : 0;
        }

        function quantitiesMatch(left, right) {
            return Math.abs(normalizeQuantity(left) - normalizeQuantity(right)) < 0.0001;
        }

        function getPickingEntries(order) {
            var checklist = Array.isArray(order.picking_checklist) ? order.picking_checklist : [];
            var orderItems = Array.isArray(order.items) ? order.items : [];

            if (checklist.length) {
                return checklist.map(function (entry) {
                    return {
                        item_id: Number(entry.item_id || 0),
                        product_id: Number(entry.product_id || 0),
                        sku: decodeHtml(entry.sku || ""),
                        name: decodeHtml(entry.name || ""),
                        ordered_quantity: normalizeQuantity(entry.ordered_quantity),
                        prepared_quantity: normalizeQuantity(entry.prepared_quantity),
                        prepared: Boolean(entry.prepared),
                        note: decodeHtml(entry.note || ""),
                        prepared_at: entry.prepared_at || "",
                        prepared_by: Number(entry.prepared_by || 0)
                    };
                });
            }

            return orderItems.map(function (item) {
                return {
                    item_id: Number(item.item_id || 0),
                    product_id: Number(item.product_id || 0),
                    sku: decodeHtml(item.sku || ""),
                    name: decodeHtml(item.name || ""),
                    ordered_quantity: normalizeQuantity(item.quantity),
                    prepared_quantity: 0,
                    prepared: false,
                    note: "",
                    prepared_at: "",
                    prepared_by: 0
                };
            });
        }

        function getIncidentTypeLabel(type) {
            var labels = {
                missing: "Producto faltante",
                insufficient_stock: "Cantidad insuficiente",
                damaged: "Producto dañado",
                wrong_item: "Producto equivocado",
                other: "Observacion general"
            };

            return labels[type] || labels.other;
        }

        function getOrderIncidents(order) {
            return Array.isArray(order && order.picking_incidents) ? order.picking_incidents : [];
        }

        function getOpenIncidents(order) {
            return getOrderIncidents(order).filter(function (incident) {
                return incident && incident.status === "open";
            });
        }

        function hasOpenPickingIncidents(order) {
            return getOpenIncidents(order).length > 0;
        }

        function getOpenIncidentForItem(itemId) {
            for (var i = 0; i < incidentState.length; i += 1) {
                if (String(incidentState[i].item_id) === String(itemId) && incidentState[i].status === "open") {
                    return incidentState[i];
                }
            }

            return null;
        }

        function getIncidentsForItem(itemId) {
            return incidentState.filter(function (incident) {
                return String(incident.item_id) === String(itemId);
            });
        }

        function getResolutionTypeLabel(type) {
            var labels = {
                wait_stock: "Esperar reposicion",
                approve_partial: "Aprobar envio parcial",
                remove_item: "Remover item del pedido",
                replace_item: "Reemplazar producto",
                no_action: "Sin accion operativa"
            };

            return labels[type] || "Sin accion operativa";
        }

        function getPickingEntryStatus(entry) {
            if (!entry || !entry.prepared || normalizeQuantity(entry.prepared_quantity) <= 0) {
                return "incomplete";
            }

            if (!quantitiesMatch(entry.prepared_quantity, entry.ordered_quantity)) {
                return "wrong";
            }

            return "ready";
        }

        function getPickingValidation() {
            var total = pickingState.length;
            var prepared = 0;

            pickingState.forEach(function (entry) {
                if (getPickingEntryStatus(entry) === "ready") {
                    prepared += 1;
                }
            });

            return {
                total: total,
                prepared: prepared,
                complete: total > 0 && prepared === total
            };
        }

        function getPickingSignature() {
            return JSON.stringify(pickingState.map(function (entry) {
                return {
                    item_id: Number(entry.item_id || 0),
                    prepared_quantity: normalizeQuantity(entry.prepared_quantity),
                    prepared: Boolean(entry.prepared),
                    note: String(entry.note || "")
                };
            }));
        }

        function hasUnsavedPickingChanges() {
            return Boolean(activeOrder && activeOrder.status === "rkm-warehouse" && getPickingSignature() !== savedPickingSignature);
        }

        function updateSaveProgressStatus(message, type) {
            if (!saveProgressStatus) {
                return;
            }

            if (message) {
                saveProgressStatus.textContent = message;
                saveProgressStatus.className = "rkm-warehouse-progress-status is-" + (type || "neutral");
                return;
            }

            if (isSavingProgress) {
                saveProgressStatus.textContent = "Guardando avance...";
                saveProgressStatus.className = "rkm-warehouse-progress-status is-pending";
                return;
            }

            if (hasUnsavedPickingChanges()) {
                saveProgressStatus.textContent = "Cambios pendientes";
                saveProgressStatus.className = "rkm-warehouse-progress-status is-pending";
            } else if (activeOrder && activeOrder.status === "rkm-warehouse") {
                saveProgressStatus.textContent = "Avance guardado";
                saveProgressStatus.className = "rkm-warehouse-progress-status is-saved";
            } else {
                saveProgressStatus.textContent = "";
                saveProgressStatus.className = "rkm-warehouse-progress-status";
            }
        }

        function getPickingEntryByItemId(itemId) {
            for (var i = 0; i < pickingState.length; i += 1) {
                if (String(pickingState[i].item_id) === String(itemId)) {
                    return pickingState[i];
                }
            }

            return null;
        }

        function syncPickingStateFromControls() {
            if (!items) {
                return;
            }

            items.querySelectorAll("[data-rkm-picking-item]").forEach(function (node) {
                var entry = getPickingEntryByItemId(node.getAttribute("data-item-id"));
                var quantityInput = node.querySelector("[data-rkm-picking-quantity]");
                var preparedInput = node.querySelector("[data-rkm-picking-prepared]");
                var noteInputControl = node.querySelector("[data-rkm-picking-note]");

                if (!entry) {
                    return;
                }

                entry.prepared_quantity = quantityInput ? normalizeQuantity(quantityInput.value) : 0;
                entry.prepared = preparedInput ? preparedInput.checked : false;
                entry.note = noteInputControl ? noteInputControl.value.trim() : "";
            });
        }

        function updatePickingUi() {
            syncPickingStateFromControls();

            var validation = getPickingValidation();
            var evidenceValidation = getEvidenceValidation();
            var hasOpenIncidents = hasOpenPickingIncidents(activeOrder);
            var progress = document.getElementById("rkmWarehousePickingProgress");
            var progressBar = document.getElementById("rkmWarehousePickingProgressBar");
            var progressHint = document.getElementById("rkmWarehousePickingHint");
            var editable = Boolean(config.can_manage && activeOrder && activeOrder.status === "rkm-warehouse");

            if (progress) {
                progress.textContent = validation.prepared + "/" + validation.total + " productos preparados";
            }

            if (progressBar) {
                progressBar.style.width = validation.total > 0 ? ((validation.prepared / validation.total) * 100) + "%" : "0%";
            }

            if (progressHint) {
                progressHint.textContent = hasOpenIncidents
                    ? "Este pedido tiene incidencias pendientes de resolver."
                    : validation.complete
                    ? "Checklist completo. El pedido puede marcarse como preparado."
                    : "Completa cada producto con cantidad correcta y checkbox preparado.";
            }

            if (items) {
                items.querySelectorAll("[data-rkm-picking-item]").forEach(function (node) {
                    var entry = getPickingEntryByItemId(node.getAttribute("data-item-id"));
                    var entryStatus = getPickingEntryStatus(entry);
                    var statusLabel = node.querySelector("[data-rkm-picking-status]");

                    node.classList.toggle("is-ready", entryStatus === "ready");
                    node.classList.toggle("is-incomplete", entryStatus === "incomplete");
                    node.classList.toggle("is-wrong", entryStatus === "wrong");
                    node.classList.toggle("has-incident", Boolean(getOpenIncidentForItem(node.getAttribute("data-item-id"))));

                    if (statusLabel) {
                        statusLabel.textContent = entryStatus === "ready" ? "Correcto" : (entryStatus === "wrong" ? "Cantidad incorrecta" : "Incompleto");
                    }
                });
            }

            if (readyButton) {
                var canMarkReady = editable && validation.complete && evidenceValidation.complete && !hasOpenIncidents && !isMarkingReady;
                readyButton.hidden = !editable;
                readyButton.disabled = !canMarkReady;
                readyButton.title = canMarkReady ? "" : (hasOpenIncidents ? "Hay incidencias pendientes de resolver." : "Completa el checklist y carga la evidencia fotografica.");
            }

            if (dispatchButton) {
                var canDispatch = Boolean(config.can_manage && activeOrder && activeOrder.status === "rkm-ready" && !hasOpenIncidents && validation.complete && evidenceValidation.complete && !isMarkingDispatched);
                dispatchButton.hidden = !(config.can_manage && activeOrder && activeOrder.status === "rkm-ready");
                dispatchButton.disabled = !canDispatch;
                dispatchButton.title = canDispatch ? "" : "El pedido debe tener picking completo, evidencia e incidencias resueltas.";
            }

            if (deliverButton) {
                var canDeliver = Boolean(config.can_manage && activeOrder && activeOrder.status === "rkm-dispatched" && !isMarkingDelivered);
                deliverButton.hidden = !(config.can_manage && activeOrder && activeOrder.status === "rkm-dispatched");
                deliverButton.disabled = !canDeliver;
            }

            if (saveProgressButton) {
                saveProgressButton.hidden = !editable;
                saveProgressButton.disabled = !editable || isSavingProgress;
            }

            updateSaveProgressStatus();
        }

        function renderItems(order) {
            if (!items) {
                return;
            }

            var editable = Boolean(config.can_manage && order && order.status === "rkm-warehouse");
            var disabled = editable ? "" : " disabled";
            var list = getPickingEntries(order);
            var openIncidents = getOpenIncidents(order);
            pickingState = list.map(function (entry) {
                return Object.assign({}, entry);
            });
            incidentState = getOrderIncidents(order).map(function (incident) {
                return Object.assign({}, incident);
            });
            savedPickingSignature = getPickingSignature();

            if (!list.length) {
                items.innerHTML = '<p class="rkm-warehouse-modal__empty">No hay productos para mostrar.</p>';
                updatePickingUi();
                return;
            }

            items.innerHTML = '<div class="rkm-warehouse-picking-summary">' +
                '<div><strong id="rkmWarehousePickingProgress">0/' + escapeHtml(list.length) + ' productos preparados</strong><span id="rkmWarehousePickingHint"></span></div>' +
                '<div class="rkm-warehouse-picking-summary__track"><span id="rkmWarehousePickingProgressBar"></span></div>' +
                '</div>' +
                (openIncidents.length ? '<div class="rkm-warehouse-incident-alert">Este pedido tiene incidencias pendientes de resolver.</div>' : '') +
                list.map(function (item) {
                var sku = String(item.sku || "").trim();
                var checked = item.prepared ? " checked" : "";
                var quantity = normalizeQuantity(item.prepared_quantity) > 0 ? item.prepared_quantity : "";
                var openIncident = getOpenIncidentForItem(item.item_id);
                var itemIncidents = getIncidentsForItem(item.item_id);
                var incidentList = itemIncidents.length ? [
                    '<div class="rkm-warehouse-incident-list">',
                    itemIncidents.map(function (incident) {
                        var isResolved = incident.status === "resolved";
                        return [
                            '<div class="rkm-warehouse-incident-card ' + (isResolved ? 'is-resolved' : 'is-open') + '">',
                                '<span>' + (isResolved ? 'Resuelta' : 'Pendiente de resolucion') + '</span>',
                                '<strong>' + escapeHtml(getIncidentTypeLabel(incident.type)) + '</strong>',
                                '<p>' + escapeHtml(incident.note || '') + '</p>',
                                isResolved ? '<small>Resolucion: ' + escapeHtml(getResolutionTypeLabel(incident.resolution_type)) + '. ' + escapeHtml(incident.resolution_note || '') + '</small>' : '',
                            '</div>'
                        ].join("");
                    }).join(""),
                    '</div>'
                ].join("") : "";
                return [
                    '<article class="rkm-warehouse-modal__item rkm-warehouse-picking-item" data-rkm-picking-item data-item-id="' + escapeAttribute(item.item_id) + '">',
                        '<div class="rkm-warehouse-picking-item__main">',
                            '<strong>' + escapeHtml(item.name || "") + '</strong>',
                            sku ? '<small>SKU: ' + escapeHtml(sku) + '</small>' : '',
                            '<span>Cantidad pedida: ' + escapeHtml(item.ordered_quantity || 0) + '</span>',
                            openIncident ? '<mark>Incidencia abierta: ' + escapeHtml(getIncidentTypeLabel(openIncident.type)) + '</mark>' : '',
                        '</div>',
                        '<div class="rkm-warehouse-picking-item__controls">',
                            '<label><span>Cantidad preparada</span><input type="number" min="0" step="1" value="' + escapeAttribute(quantity) + '" data-rkm-picking-quantity' + disabled + '></label>',
                            '<label class="rkm-warehouse-picking-item__check"><input type="checkbox" data-rkm-picking-prepared' + checked + disabled + '><span>Preparado</span></label>',
                            '<label><span>Observacion</span><input type="text" maxlength="140" value="' + escapeAttribute(item.note || "") + '" placeholder="Opcional" data-rkm-picking-note' + disabled + '></label>',
                            '<em data-rkm-picking-status></em>',
                        '</div>',
                        editable ? [
                            '<div class="rkm-warehouse-incident" data-rkm-incident-panel hidden>',
                                '<label><span>Tipo de incidencia</span><select data-rkm-incident-type>',
                                    '<option value="missing">Producto faltante</option>',
                                    '<option value="insufficient_stock">Cantidad insuficiente</option>',
                                    '<option value="damaged">Producto dañado</option>',
                                    '<option value="wrong_item">Producto equivocado</option>',
                                    '<option value="other">Observacion general</option>',
                                '</select></label>',
                                '<label><span>Cantidad disponible</span><input type="number" min="0" step="1" value="' + escapeAttribute(quantity || 0) + '" data-rkm-incident-available></label>',
                                '<label class="rkm-warehouse-incident__note"><span>Nota obligatoria</span><textarea rows="2" maxlength="500" data-rkm-incident-note></textarea></label>',
                                '<button type="button" class="rkm-warehouse__btn rkm-warehouse__btn--secondary" data-rkm-incident-submit>Guardar incidencia</button>',
                            '</div>',
                            '<button type="button" class="rkm-warehouse__btn rkm-warehouse__btn--secondary rkm-warehouse-incident-toggle" data-rkm-incident-toggle>Reportar incidencia</button>'
                        ].join("") : '',
                        incidentList,
                    '</article>'
                ].join("");
            }).join("");

            items.querySelectorAll("[data-rkm-picking-quantity], [data-rkm-picking-prepared], [data-rkm-picking-note]").forEach(function (control) {
                control.addEventListener("input", updatePickingUi);
                control.addEventListener("change", updatePickingUi);
            });

            items.querySelectorAll("[data-rkm-incident-toggle]").forEach(function (button) {
                button.addEventListener("click", function () {
                    var itemNode = button.closest("[data-rkm-picking-item]");
                    var panel = itemNode ? itemNode.querySelector("[data-rkm-incident-panel]") : null;

                    if (panel) {
                        panel.hidden = !panel.hidden;
                    }
                });
            });

            items.querySelectorAll("[data-rkm-incident-submit]").forEach(function (button) {
                button.addEventListener("click", function () {
                    var itemNode = button.closest("[data-rkm-picking-item]");

                    if (itemNode) {
                        reportPickingIncident(itemNode, button);
                    }
                });
            });

            updatePickingUi();
        }

        function getEvidenceMinRequired(order) {
            var minimum = Number(order && order.evidence_min_required ? order.evidence_min_required : 2);
            return Number.isFinite(minimum) && minimum > 0 ? minimum : 2;
        }

        function getSavedEvidence(order) {
            return Array.isArray(order && order.evidence) ? order.evidence : [];
        }

        function getEvidenceValidation() {
            if (!activeOrder) {
                return {
                    count: 0,
                    minimum: 2,
                    complete: false
                };
            }

            var savedCount = getSavedEvidence(activeOrder).length;
            var count = savedCount + evidenceFiles.length;
            var minimum = getEvidenceMinRequired(activeOrder);

            return {
                count: count,
                minimum: minimum,
                complete: count >= minimum
            };
        }

        function clearEvidencePreviewUrls() {
            evidencePreviewUrls.forEach(function (url) {
                window.URL.revokeObjectURL(url);
            });
            evidencePreviewUrls = [];
        }

        function validateSelectedEvidenceFiles(files) {
            var allowedTypes = ["image/jpeg", "image/png", "image/webp"];
            var maxSize = 5 * 1024 * 1024;

            for (var i = 0; i < files.length; i += 1) {
                if (allowedTypes.indexOf(files[i].type) === -1) {
                    return "Solo se permiten imagenes JPG, PNG o WebP.";
                }

                if (files[i].size <= 0 || files[i].size > maxSize) {
                    return "Cada foto debe pesar 5 MB o menos.";
                }
            }

            return "";
        }

        function updateEvidenceUi(message) {
            if (!evidence) {
                return;
            }

            var validation = getEvidenceValidation();
            var counter = evidence.querySelector("[data-rkm-evidence-counter]");
            var error = evidence.querySelector("[data-rkm-evidence-error]");
            var selectedPreview = evidence.querySelector("[data-rkm-evidence-selected]");

            if (counter) {
                counter.textContent = validation.count + "/" + validation.minimum + " fotos requeridas";
                counter.classList.toggle("is-complete", validation.complete);
            }

            if (error) {
                error.textContent = message || (validation.complete ? "" : "Carga al menos " + validation.minimum + " fotos para continuar.");
                error.hidden = !error.textContent;
            }

            if (selectedPreview) {
                clearEvidencePreviewUrls();
                selectedPreview.innerHTML = evidenceFiles.map(function (file) {
                    var url = window.URL.createObjectURL(file);
                    evidencePreviewUrls.push(url);

                    return [
                        '<figure class="rkm-warehouse-evidence__preview">',
                            '<img src="' + escapeAttribute(url) + '" alt="">',
                            '<figcaption>' + escapeHtml(file.name || "Foto seleccionada") + '</figcaption>',
                        '</figure>'
                    ].join("");
                }).join("");
            }

            updatePickingUi();
        }

        function renderEvidence(order) {
            if (!evidence) {
                return;
            }

            clearEvidencePreviewUrls();
            evidenceFiles = [];

            var editable = Boolean(config.can_manage && order && order.status === "rkm-warehouse");
            var saved = getSavedEvidence(order);
            var minimum = getEvidenceMinRequired(order);
            var savedHtml = saved.length ? saved.map(function (photo) {
                return [
                    '<figure class="rkm-warehouse-evidence__preview">',
                        '<img src="' + escapeAttribute(photo.thumbnail || photo.thumbnail_url || photo.url || "") + '" alt="">',
                        '<figcaption>' + escapeHtml(photo.filename || photo.title || ("#" + (photo.id || ""))) + '</figcaption>',
                    '</figure>'
                ].join("");
            }).join("") : '<p class="rkm-warehouse-modal__empty">No hay fotos cargadas.</p>';

            evidence.innerHTML = [
                '<div class="rkm-warehouse-evidence__summary">',
                    '<strong data-rkm-evidence-counter>0/' + escapeHtml(minimum) + ' fotos requeridas</strong>',
                    '<span>JPG, PNG o WebP. Maximo 5 MB por imagen.</span>',
                '</div>',
                editable ? '<label class="rkm-warehouse-evidence__drop"><span>Seleccionar fotos</span><input type="file" accept="image/jpeg,image/png,image/webp" multiple data-rkm-evidence-input></label>' : '',
                '<div class="rkm-warehouse-evidence__grid" data-rkm-evidence-saved>' + savedHtml + '</div>',
                '<div class="rkm-warehouse-evidence__grid" data-rkm-evidence-selected></div>',
                '<p class="rkm-warehouse-evidence__error" data-rkm-evidence-error hidden></p>'
            ].join("");

            var input = evidence.querySelector("[data-rkm-evidence-input]");
            if (input) {
                input.addEventListener("change", function () {
                    var selected = Array.prototype.slice.call(input.files || []);
                    var error = validateSelectedEvidenceFiles(selected);

                    if (error) {
                        evidenceFiles = [];
                        input.value = "";
                        updateEvidenceUi(error);
                        return;
                    }

                    evidenceFiles = selected;
                    updateEvidenceUi("");
                });
            }

            updateEvidenceUi("");
        }

        function renderClosure(order) {
            if (!closure) {
                return;
            }

            var statusValue = order && order.status ? order.status : "";
            var closureData = order && order.operational_closure ? order.operational_closure : {};
            var creditContext = order && order.credit_context ? order.credit_context : {};
            var rows = [];

            if (closureData.dispatched_label) {
                rows.push('<span>Despacho: <strong>' + escapeHtml(closureData.dispatched_label) + '</strong></span>');
            }

            if (closureData.delivered_label) {
                rows.push('<span>Entrega: <strong>' + escapeHtml(closureData.delivered_label) + '</strong></span>');
            }

            if (creditContext && creditContext.has_credit && (creditContext.due_label || creditContext.status_label)) {
                rows.push('<span>Credito: <strong>' + escapeHtml(creditContext.due_label || "Pendiente") + '</strong></span>');
                if (creditContext.status_label) {
                    rows.push('<span>' + escapeHtml(creditContext.status_label) + '</span>');
                }
            }

            var message = "El cierre operativo se habilita cuando corresponde al estado actual.";
            if (statusValue === "rkm-ready") {
                message = "Pedido preparado y listo para salida logistica.";
            } else if (statusValue === "rkm-dispatched") {
                message = "Pedido despachado. Confirma la entrega para cerrar el ciclo operativo.";
            } else if (statusValue === "completed") {
                message = "Pedido entregado y cerrado operativamente.";
            } else if (statusValue === "rkm-warehouse") {
                message = "Completa el picking para habilitar el cierre operativo.";
            }

            closure.innerHTML = [
                '<div class="rkm-warehouse-closure__status">',
                    '<strong>' + escapeHtml(message) + '</strong>',
                    rows.length ? '<div>' + rows.join("") + '</div>' : '',
                '</div>'
            ].join("");
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
            updatePickingUi();
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
            renderEvidence(order);
            renderClosure(order);
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
            if (allowedWarehouseActions.indexOf(action) === -1) {
                return Promise.reject(new Error("Accion no permitida en el modulo Almacen."));
            }

            var formData = new FormData();

            formData.append("action", action);
            formData.append("nonce", config.nonce || "");

            Object.keys(data || {}).forEach(function (key) {
                if (Array.isArray(data[key])) {
                    data[key].forEach(function (value) {
                        formData.append(key + "[]", value);
                    });
                    return;
                }

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

        function savePickingProgress() {
            if (isSavingProgress) {
                return;
            }

            if (!activeOrder || activeOrder.status !== "rkm-warehouse") {
                showNotice("Solo se puede guardar avance en pedidos en preparacion.", "error");
                return;
            }

            syncPickingStateFromControls();
            isSavingProgress = true;
            updateSaveProgressStatus("Guardando avance...", "pending");
            updatePickingUi();

            sendAjax("rkm_save_warehouse_picking_progress", {
                order_id: activeOrder.id,
                checklist: JSON.stringify(pickingState),
                evidence_photos: evidenceFiles
            }).then(function (payload) {
                if (!payload || !payload.success) {
                    throw new Error(payload && payload.data && payload.data.message ? payload.data.message : "No se pudo guardar el avance.");
                }

                var updatedOrder = payload.data.order || null;
                if (updatedOrder) {
                    mergeOrder(updatedOrder);
                    activeOrder = updatedOrder;
                    pickingState = getPickingEntries(updatedOrder).map(function (entry) {
                        return Object.assign({}, entry);
                    });
                    savedPickingSignature = getPickingSignature();
                    renderModal(updatedOrder);
                }

                updateSaveProgressStatus("Avance guardado", "saved");
                showNotice(payload.data.message || "Avance de picking guardado.", "success");
            }).catch(function (error) {
                updateSaveProgressStatus("Cambios pendientes", "pending");
                showNotice(error.message || "No se pudo guardar el avance.", "error");
            }).finally(function () {
                isSavingProgress = false;
                updatePickingUi();
            });
        }

        function reportPickingIncident(itemNode, sourceButton) {
            if (!activeOrder || activeOrder.status !== "rkm-warehouse") {
                showNotice("Solo se pueden reportar incidencias en pedidos en preparacion.", "error");
                return;
            }

            var typeInput = itemNode.querySelector("[data-rkm-incident-type]");
            var availableInput = itemNode.querySelector("[data-rkm-incident-available]");
            var noteInputControl = itemNode.querySelector("[data-rkm-incident-note]");
            var itemId = itemNode.getAttribute("data-item-id");
            var note = noteInputControl ? noteInputControl.value.trim() : "";
            var availableQuantity = availableInput ? normalizeQuantity(availableInput.value) : 0;

            if (!note) {
                showNotice("La nota de incidencia es obligatoria.", "error");
                return;
            }

            if (availableQuantity < 0) {
                showNotice("La cantidad disponible no puede ser negativa.", "error");
                return;
            }

            syncPickingStateFromControls();

            if (sourceButton) {
                sourceButton.disabled = true;
            }

            sendAjax("rkm_report_warehouse_picking_incident", {
                order_id: activeOrder.id,
                checklist: JSON.stringify(pickingState),
                item_id: itemId,
                type: typeInput ? typeInput.value : "other",
                available_quantity: availableQuantity,
                note: note
            }).then(function (payload) {
                if (!payload || !payload.success) {
                    throw new Error(payload && payload.data && payload.data.message ? payload.data.message : "No se pudo registrar la incidencia.");
                }

                var updatedOrder = payload.data.order || null;
                if (updatedOrder) {
                    mergeOrder(updatedOrder);
                    activeOrder = updatedOrder;
                    renderModal(updatedOrder);
                }

                showNotice(payload.data.message || "Incidencia de picking registrada.", "success");
            }).catch(function (error) {
                showNotice(error.message || "No se pudo registrar la incidencia.", "error");
            }).finally(function () {
                if (sourceButton) {
                    sourceButton.disabled = false;
                }

                updatePickingUi();
            });
        }

        function markReady() {
            if (isMarkingReady) {
                return;
            }

            if (!activeOrder || activeOrder.status !== "rkm-warehouse") {
                showNotice("Solo se puede marcar como preparado un pedido en preparacion.", "error");
                return;
            }

            syncPickingStateFromControls();

            if (!getPickingValidation().complete) {
                showNotice("Completa el checklist con cantidades correctas antes de marcar el pedido.", "error");
                updatePickingUi();
                return;
            }

            if (!getEvidenceValidation().complete) {
                showNotice("Carga al menos " + getEvidenceMinRequired(activeOrder) + " fotos de evidencia.", "error");
                updateEvidenceUi("");
                return;
            }

            if (hasOpenPickingIncidents(activeOrder)) {
                showNotice("Este pedido tiene incidencias pendientes de resolver.", "error");
                updatePickingUi();
                return;
            }

            isMarkingReady = true;
            if (readyButton) {
                readyButton.disabled = true;
            }

            sendAjax("rkm_mark_order_ready", {
                order_id: activeOrder.id,
                checklist: JSON.stringify(pickingState),
                evidence_photos: evidenceFiles
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
                    updateCounts();
                    applyFilter(activeFilter);
                }

                showNotice(payload.data.message || "Pedido marcado como preparado.", "success");
            }).catch(function (error) {
                showNotice(error.message || "No se pudo marcar el pedido como preparado.", "error");
            }).finally(function () {
                isMarkingReady = false;
                updatePickingUi();
            });
        }

        function markDispatched() {
            if (isMarkingDispatched) {
                return;
            }

            if (!activeOrder || activeOrder.status !== "rkm-ready") {
                showNotice("Solo se pueden despachar pedidos listos.", "error");
                return;
            }

            if (hasOpenPickingIncidents(activeOrder)) {
                showNotice("Este pedido tiene incidencias pendientes de resolver.", "error");
                updatePickingUi();
                return;
            }

            isMarkingDispatched = true;
            if (dispatchButton) {
                dispatchButton.disabled = true;
            }

            sendAjax("rkm_mark_order_dispatched", {
                order_id: activeOrder.id
            }).then(function (payload) {
                if (!payload || !payload.success) {
                    throw new Error(payload && payload.data && payload.data.message ? payload.data.message : "No se pudo despachar el pedido.");
                }

                var updatedOrder = payload.data.order || null;
                if (updatedOrder) {
                    mergeOrder(updatedOrder);
                    activeOrder = updatedOrder;
                    setRowStatus(updatedOrder);
                    renderModal(updatedOrder);
                    updateCounts();
                    applyFilter(activeFilter);
                }

                showNotice(payload.data.message || "Pedido marcado como despachado.", "success");
            }).catch(function (error) {
                showNotice(error.message || "No se pudo despachar el pedido.", "error");
            }).finally(function () {
                isMarkingDispatched = false;
                updatePickingUi();
            });
        }

        function markDelivered() {
            if (isMarkingDelivered) {
                return;
            }

            if (!activeOrder || activeOrder.status !== "rkm-dispatched") {
                showNotice("Solo se pueden entregar pedidos despachados.", "error");
                return;
            }

            isMarkingDelivered = true;
            if (deliverButton) {
                deliverButton.disabled = true;
            }

            sendAjax("rkm_mark_order_delivered", {
                order_id: activeOrder.id
            }).then(function (payload) {
                if (!payload || !payload.success) {
                    throw new Error(payload && payload.data && payload.data.message ? payload.data.message : "No se pudo confirmar la entrega.");
                }

                var updatedOrder = payload.data.order || null;
                if (updatedOrder) {
                    mergeOrder(updatedOrder);
                    activeOrder = updatedOrder;
                    setRowStatus(updatedOrder);
                    renderModal(updatedOrder);
                    updateCounts();
                    applyFilter(activeFilter);
                }

                showNotice(payload.data.message || "Pedido marcado como entregado.", "success");
            }).catch(function (error) {
                showNotice(error.message || "No se pudo confirmar la entrega.", "error");
            }).finally(function () {
                isMarkingDelivered = false;
                updatePickingUi();
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

        if (saveProgressButton) {
            saveProgressButton.addEventListener("click", savePickingProgress);
        }

        if (readyButton) {
            readyButton.addEventListener("click", markReady);
        }

        if (dispatchButton) {
            dispatchButton.addEventListener("click", markDispatched);
        }

        if (deliverButton) {
            deliverButton.addEventListener("click", markDelivered);
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
