(function ($) {
	"use strict";

	if (typeof window.gs_posts_grid_init !== "function") {
		window.gs_posts_grid_init = function () {};
	}

	var fieldEditorDirty = false;
	var draggedFieldRow = null;
	var pointerFieldDrag = null;
	var orderFilters = {
		status: "all",
		payment: "all",
	};

	function renderMiniChart() {
		var days = window.checkflowAdmin && checkflowAdmin.chartDays ? checkflowAdmin.chartDays : [];
		var vals = window.checkflowAdmin && checkflowAdmin.chartVals ? checkflowAdmin.chartVals : [];
		var mc = document.getElementById("cf-mc");
		var cx = document.getElementById("cf-cx");
		if (!mc || !cx || !vals.length) {
			return;
		}
		mc.innerHTML = "";
		cx.innerHTML = "";
		var mx = Math.max.apply(null, vals);
		vals.forEach(function (v, i) {
			var b = document.createElement("div");
			b.className = "bc";
			var h = mx ? (v / mx) * 100 : 0;
			b.style.cssText =
				"height:" +
				h +
				"%;background:" +
				(i === 5 ? "var(--pr)" : "var(--s3)") +
				";flex:1;border-radius:3px 3px 0 0;";
			mc.appendChild(b);
			var l = document.createElement("span");
			l.textContent = days[i] || "";
			cx.appendChild(l);
		});
	}

	function setPane(id) {
		var root = document.getElementById("checkflow-admin");
		if (!root) return;
		root.querySelectorAll(".cf-pane").forEach(function (p) {
			p.classList.toggle("is-active", p.getAttribute("data-pane") === id);
		});
		root.querySelectorAll(".ni[data-screen]").forEach(function (n) {
			n.classList.toggle("on", n.getAttribute("data-screen") === id);
		});
		var info = window.checkflowAdmin && checkflowAdmin.screens ? checkflowAdmin.screens[id] : null;
		var ttl = document.getElementById("cf-ttl");
		if (ttl && info) {
			while (ttl.firstChild) ttl.removeChild(ttl.firstChild);
			ttl.appendChild(document.createTextNode((info.title || "") + " "));
			var sp = document.createElement("span");
			sp.textContent = info.sub || "";
			ttl.appendChild(sp);
		}
		if (window.history && window.location.hash !== "#" + id) {
			window.history.replaceState(null, "", window.location.pathname + window.location.search + "#" + id);
		}
	}

	function showToast(message, type) {
		var root = document.getElementById("checkflow-admin");
		if (!root) return;
		var toast = root.querySelector(".cf-toast");
		if (!toast) {
			toast = document.createElement("div");
			toast.className = "cf-toast";
			toast.setAttribute("role", "status");
			toast.setAttribute("aria-live", "polite");
			root.appendChild(toast);
		}
		toast.textContent = message;
		toast.classList.toggle("is-error", type === "error");
		toast.classList.remove("is-visible");
		window.clearTimeout(toast._cfTimer);
		window.setTimeout(function () {
			toast.classList.add("is-visible");
		}, 10);
		toast._cfTimer = window.setTimeout(function () {
			toast.classList.remove("is-visible");
		}, 2200);
	}

	function escapeHtml(value) {
		return String(value == null ? "" : value).replace(/[&<>"']/g, function (char) {
			return {
				"&": "&amp;",
				"<": "&lt;",
				">": "&gt;",
				'"': "&quot;",
				"'": "&#039;",
			}[char];
		});
	}

	function applyAdminTheme(theme) {
		var root = document.getElementById("checkflow-admin");
		var next = theme === "light" ? "light" : "dark";
		var button = document.querySelector("[data-admin-theme-toggle]");
		var label = document.querySelector("[data-admin-theme-label]");
		var icon = document.querySelector(".cf-theme-toggle-icon");
		if (root) {
			root.classList.toggle("is-light", next === "light");
			root.setAttribute("data-admin-theme", next);
		}
		document.body.classList.toggle("checkflow-admin-theme-light", next === "light");
		document.body.classList.toggle("checkflow-admin-theme-dark", next !== "light");
		if (button) {
			button.setAttribute("aria-pressed", next === "light" ? "true" : "false");
		}
		if (label) {
			label.textContent = next === "light" ? "Light" : "Dark";
		}
		if (icon) {
			icon.textContent = next === "light" ? "☀" : "☾";
		}
		if (window.checkflowAdmin) {
			checkflowAdmin.adminTheme = next;
		}
	}

	function saveAdminTheme(theme, buttonEl) {
		var ajaxUrl = getAdminAjaxUrl();
		if (!ajaxUrl) {
			applyAdminTheme(theme);
			return;
		}
		var previous = window.checkflowAdmin && checkflowAdmin.adminTheme ? checkflowAdmin.adminTheme : "dark";
		applyAdminTheme(theme);
		if (buttonEl) {
			buttonEl.disabled = true;
		}
		$.ajax({
			url: ajaxUrl,
			method: "POST",
			dataType: "json",
			data: {
				action: "checkflow_save_admin_theme",
				nonce: checkflowAdmin.nonce,
				theme: theme,
			},
		})
			.done(function (res) {
				if (!res || !res.success) {
					applyAdminTheme(previous);
					showToast("Could not save admin theme", "error");
					return;
				}
				applyAdminTheme(res.data && res.data.theme ? res.data.theme : theme);
				showToast(theme === "light" ? "Light theme enabled" : "Dark theme enabled");
			})
			.fail(function () {
				applyAdminTheme(previous);
				showToast("Could not save admin theme", "error");
			})
			.always(function () {
				if (buttonEl) {
					buttonEl.disabled = false;
				}
			});
	}

	function applyOrderFilters() {
		var root = document.getElementById("checkflow-admin");
		if (!root) {
			return;
		}

		var pane = root.querySelector('[data-pane="orders"]');
		if (!pane) {
			return;
		}

		var input = pane.querySelector(".cf-orders-search-input");
		var query = input ? input.value.trim().toLowerCase() : "";
		var rows = Array.prototype.slice.call(pane.querySelectorAll("[data-order-row]"));
		var visible = 0;

		rows.forEach(function (row) {
			var status = row.getAttribute("data-order-status") || "";
			var payment = row.getAttribute("data-order-payment") || "";
			var searchable = row.getAttribute("data-order-search") || row.textContent.toLowerCase();
			var statusOk = orderFilters.status === "all" || status === orderFilters.status;
			var paymentOk = orderFilters.payment === "all" || payment === orderFilters.payment;
			var searchOk = !query || searchable.indexOf(query) !== -1;
			var show = statusOk && paymentOk && searchOk;

			row.classList.toggle("is-filtered", !show);
			if (show) {
				visible += 1;
			}
		});

		var count = pane.querySelector("[data-orders-visible-count]");
		if (count) {
			count.textContent = String(visible);
		}

		var noResults = pane.querySelector(".cf-orders-no-results");
		if (noResults) {
			noResults.hidden = rows.length === 0 || visible !== 0;
		}
		updateOrderSelectionState();
	}

	function getOrderRows(includeHidden) {
		var root = document.getElementById("checkflow-admin");
		if (!root) {
			return [];
		}
		var pane = root.querySelector('[data-pane="orders"]');
		if (!pane) {
			return [];
		}
		var rows = Array.prototype.slice.call(pane.querySelectorAll("[data-order-row]"));
		if (includeHidden) {
			return rows;
		}
		return rows.filter(function (row) {
			return !row.classList.contains("is-filtered");
		});
	}

	function getSelectedOrderRows() {
		return getOrderRows(true).filter(function (row) {
			var checkbox = row.querySelector("[data-order-select]");
			return checkbox && checkbox.checked;
		});
	}

	function updateOrderSelectionState() {
		var root = document.getElementById("checkflow-admin");
		if (!root) {
			return;
		}
		var selected = getSelectedOrderRows();
		var visibleRows = getOrderRows(false);
		var bulkbar = root.querySelector("[data-orders-bulkbar]");
		var count = root.querySelector("[data-orders-selected-count]");
		var selectAll = root.querySelector("[data-order-select-all]");

		getOrderRows(true).forEach(function (row) {
			var checkbox = row.querySelector("[data-order-select]");
			row.classList.toggle("is-selected", !!(checkbox && checkbox.checked));
		});

		if (bulkbar) {
			bulkbar.hidden = selected.length === 0;
		}
		if (count) {
			count.textContent = String(selected.length);
		}
		if (selectAll) {
			var selectedVisible = visibleRows.filter(function (row) {
				var checkbox = row.querySelector("[data-order-select]");
				return checkbox && checkbox.checked;
			}).length;
			selectAll.checked = visibleRows.length > 0 && selectedVisible === visibleRows.length;
			selectAll.indeterminate = selectedVisible > 0 && selectedVisible < visibleRows.length;
		}
	}

	function parseOrderDetail(row) {
		if (!row) {
			return null;
		}
		try {
			return JSON.parse(row.getAttribute("data-order-detail") || "{}");
		} catch (e) {
			return null;
		}
	}

	function normalizeOrderDetail(order) {
		if (!order) {
			return null;
		}
		return {
			orderId: order.order_id || order.orderId || "",
			id: order.id || "",
			customer: order.customer || "",
			email: order.email || "",
			phone: order.phone || "",
			address: order.address || "",
			payment: order.payment || "",
			paymentClass: order.payment_class || order.paymentClass || "",
			courier: order.courier || "",
			courierProvider: order.courier_provider || order.courierProvider || "",
			courierStatus: order.courier_status || order.courierStatus || "",
			amount: order.amount || "",
			status: order.status || "",
			statusKey: order.status_key || order.statusKey || "",
			statusClass: order.status_class || order.statusClass || "",
			date: order.date || "",
			items: order.items || [],
			editUrl: order.edit_url || order.editUrl || "",
		};
	}

	function setText(selector, value) {
		var el = document.querySelector(selector);
		if (el) {
			el.textContent = value || "";
		}
	}

	function openOrderDrawer(row) {
		var root = document.getElementById("checkflow-admin");
		var detail = normalizeOrderDetail(parseOrderDetail(row));
		if (!root || !detail) {
			return;
		}
		var drawer = root.querySelector(".cf-order-drawer");
		var backdrop = root.querySelector(".cf-order-drawer-backdrop");
		if (!drawer || !backdrop) {
			return;
		}
		setText("[data-order-detail-id]", detail.id);
		setText("[data-order-detail-status]", detail.status);
		setText("[data-order-detail-amount]", detail.amount);
		setText("[data-order-detail-customer]", detail.customer);
		setText("[data-order-detail-email]", detail.email);
		setText("[data-order-detail-phone]", detail.phone);
		setText("[data-order-detail-address]", detail.address);
		setText("[data-order-detail-payment]", detail.payment);
		setText("[data-order-detail-courier]", detail.courier);
		setText("[data-order-detail-date]", detail.date);

		var edit = root.querySelector("[data-order-detail-edit]");
		if (edit) {
			edit.href = detail.editUrl || "#";
		}
		updateCourierAction(detail);

		var itemsWrap = root.querySelector("[data-order-detail-items]");
		if (itemsWrap) {
			itemsWrap.innerHTML = "";
			(detail.items || []).forEach(function (item) {
				var line = document.createElement("div");
				line.className = "cf-order-detail-item";
				var name = document.createElement("strong");
				name.textContent = item.name || "Item";
				var qty = document.createElement("span");
				qty.textContent = "Qty " + (item.qty || "1");
				var total = document.createElement("span");
				total.textContent = item.total || "";
				line.appendChild(name);
				line.appendChild(total);
				line.appendChild(qty);
				itemsWrap.appendChild(line);
			});
			if (!itemsWrap.children.length) {
				var empty = document.createElement("span");
				empty.textContent = "No line items found.";
				itemsWrap.appendChild(empty);
			}
		}

		root._cfActiveOrderDetail = detail;
		root._cfActiveOrderRow = row;
		root._cfPendingStatusDraft = "";
		resetOrderWorkflowUi(root);
		backdrop.hidden = false;
		drawer.classList.add("is-open");
		drawer.setAttribute("aria-hidden", "false");
	}

	function closeOrderDrawer() {
		var root = document.getElementById("checkflow-admin");
		if (!root) {
			return;
		}
		var drawer = root.querySelector(".cf-order-drawer");
		var backdrop = root.querySelector(".cf-order-drawer-backdrop");
		if (drawer) {
			drawer.classList.remove("is-open");
			drawer.setAttribute("aria-hidden", "true");
		}
		if (backdrop) {
			backdrop.hidden = true;
		}
	}

	function setOrderActivity(message) {
		var root = document.getElementById("checkflow-admin");
		var activity = root ? root.querySelector("[data-order-activity]") : null;
		if (!activity) {
			return;
		}
		activity.textContent = message || "";
		activity.hidden = !message;
	}

	function clearPathaoReview() {
		var box = document.querySelector("[data-pathao-review]");
		if (box) {
			box.hidden = true;
			box.innerHTML = "";
		}
	}

	function renderPathaoReview(data) {
		var box = document.querySelector("[data-pathao-review]");
		if (!box) {
			return;
		}
		box.innerHTML = "";
		var title = document.createElement("strong");
		title.textContent = data && data.missing && data.missing.length ? "Pathao booking needs attention" : "Pathao booking payload ready";
		box.appendChild(title);

		var meta = document.createElement("div");
		meta.textContent = "Mode: " + ((data && data.mode) || "sandbox") + " | Base URL: " + ((data && data.baseUrl) || "auto");
		box.appendChild(meta);

		if (data && data.payload) {
			var payload = document.createElement("div");
			payload.textContent = "Order " + (data.payload.merchant_order_id || "") + " -> " + (data.payload.recipient_name || "Recipient") + ", collect " + (data.payload.amount_to_collect || 0);
			box.appendChild(payload);
		}

		if (data && data.missing && data.missing.length) {
			var ul = document.createElement("ul");
			data.missing.forEach(function (item) {
				var li = document.createElement("li");
				li.textContent = item;
				ul.appendChild(li);
			});
			box.appendChild(ul);
		}

		box.hidden = false;
	}

	function orderDetailSearchText(detail) {
		return [detail.id, detail.customer, detail.payment, detail.courier, detail.amount, detail.status, detail.date].join(" ").toLowerCase();
	}

	function paymentFilterFromClass(paymentClass) {
		if (paymentClass === "bkash" || paymentClass === "nagad") {
			return "mobile";
		}
		return paymentClass === "cod" ? "cod" : "card";
	}

	function updateOrderRow(row, detail) {
		if (!row || !detail) {
			return;
		}
		row.setAttribute("data-order-status", detail.statusClass || "");
		row.setAttribute("data-order-payment", paymentFilterFromClass(detail.paymentClass));
		row.setAttribute("data-order-search", orderDetailSearchText(detail));
		row.setAttribute("data-order-detail", JSON.stringify(detail));

		var gateway = row.querySelector(".gtag");
		var status = row.querySelector(".stag");
		var amount = row.querySelector(".oamt");
		var customer = row.querySelector(".ocust");
		var courier = row.querySelector(".ocourier");
		if (gateway) {
			gateway.className = "gtag " + (detail.paymentClass || "gateway-card");
			gateway.textContent = detail.payment || "";
		}
		if (status) {
			status.className = "stag " + (detail.statusClass || "");
			status.textContent = detail.status || "";
		}
		if (amount) {
			amount.textContent = detail.amount || "";
		}
		if (customer) {
			customer.textContent = detail.customer || "";
		}
		if (courier) {
			courier.textContent = detail.courier || "";
			courier.classList.toggle("is-ready", detail.courierStatus === "draft_ready" || detail.courierStatus === "booked" || String(detail.courier || "").toLowerCase().indexOf("draft ready") !== -1 || String(detail.courier || "").toLowerCase().indexOf("booked") !== -1);
		}
		row.classList.remove("is-updated");
		row.offsetHeight;
		row.classList.add("is-updated");
		window.setTimeout(function () {
			row.classList.remove("is-updated");
		}, 1500);
		applyOrderFilters();
	}

	function refreshOpenOrderDetail(detail) {
		var root = document.getElementById("checkflow-admin");
		if (!root || !detail) {
			return;
		}
		root._cfActiveOrderDetail = detail;
		setText("[data-order-detail-id]", detail.id);
		setText("[data-order-detail-status]", detail.status);
		setText("[data-order-detail-amount]", detail.amount);
		setText("[data-order-detail-customer]", detail.customer);
		setText("[data-order-detail-email]", detail.email);
		setText("[data-order-detail-phone]", detail.phone);
		setText("[data-order-detail-address]", detail.address);
		setText("[data-order-detail-payment]", detail.payment);
		setText("[data-order-detail-courier]", detail.courier);
		setText("[data-order-detail-date]", detail.date);
		updateCourierAction(detail);
		updateOrderRow(root._cfActiveOrderRow, detail);
	}

	function updateCourierAction(detail) {
		var button = document.querySelector('[data-order-single-action="courier"]');
		var pathaoButton = document.querySelector("[data-book-pathao-order]");
		if (button) {
			button.textContent = detail && detail.courierStatus === "draft_ready" ? "Refresh courier draft" : "Prepare courier";
		}
		if (pathaoButton) {
			pathaoButton.textContent = detail && detail.courierStatus === "booked" ? "Pathao booked" : "Book Pathao live";
			pathaoButton.disabled = !!(detail && detail.courierStatus === "booked");
		}
	}

	function resetOrderWorkflowUi(root) {
		if (!root) {
			return;
		}
		root.querySelectorAll("[data-order-status-draft]").forEach(function (button) {
			button.classList.remove("is-active");
		});
		clearPathaoReview();
		var confirm = root.querySelector("[data-order-status-confirm]");
		if (confirm) {
			confirm.hidden = true;
		}
		var noteType = root.querySelector("[data-order-note-type]");
		var noteText = root.querySelector("[data-order-note-text]");
		var notePreview = root.querySelector("[data-order-note-preview]");
		if (noteType) {
			noteType.value = "internal";
		}
		if (noteText) {
			noteText.value = "";
		}
		if (notePreview) {
			notePreview.hidden = true;
			notePreview.textContent = "";
		}
		setOrderActivity("");
	}

	function prepareStatusDraft(button) {
		var root = document.getElementById("checkflow-admin");
		var detail = root && root._cfActiveOrderDetail ? root._cfActiveOrderDetail : null;
		if (!root || !detail) {
			showToast("Open an order first", "error");
			return;
		}
		var next = button.getAttribute("data-order-status-draft") || "";
		var label = button.textContent.trim();
		root._cfPendingStatusDraft = next;
		root.querySelectorAll("[data-order-status-draft]").forEach(function (item) {
			item.classList.toggle("is-active", item === button);
		});
		var text = root.querySelector("[data-order-status-confirm-text]");
		var confirm = root.querySelector("[data-order-status-confirm]");
		if (text) {
			text.textContent = detail.id + " will be updated to: " + label + ". This will change the real WooCommerce order status.";
		}
		if (confirm) {
			confirm.hidden = false;
		}
	}

	function cancelStatusDraft() {
		var root = document.getElementById("checkflow-admin");
		if (!root) {
			return;
		}
		root._cfPendingStatusDraft = "";
		root.querySelectorAll("[data-order-status-draft]").forEach(function (button) {
			button.classList.remove("is-active");
		});
		var confirm = root.querySelector("[data-order-status-confirm]");
		if (confirm) {
			confirm.hidden = true;
		}
	}

	function confirmStatusDraft() {
		var root = document.getElementById("checkflow-admin");
		var detail = root && root._cfActiveOrderDetail ? root._cfActiveOrderDetail : null;
		var next = root ? root._cfPendingStatusDraft : "";
		if (!detail || !next) {
			showToast("Choose a status action first", "error");
			return;
		}
		var ajaxUrl = getAdminAjaxUrl();
		if (!ajaxUrl || !detail.orderId) {
			showToast("Could not update order status", "error");
			return;
		}
		var button = root.querySelector("[data-order-status-confirm-btn]");
		if (button) {
			button.disabled = true;
		}
		$.ajax({
			url: ajaxUrl,
			method: "POST",
			dataType: "json",
			data: {
				action: "checkflow_update_order_status",
				nonce: checkflowAdmin.nonce,
				order_id: detail.orderId,
				status: next,
			},
		})
			.done(function (res) {
				if (res && res.success && res.data && res.data.order) {
					var updated = normalizeOrderDetail(res.data.order);
					refreshOpenOrderDetail(updated);
					setOrderActivity((res.data.message || "Order status updated") + " Table row refreshed.");
					showToast(res.data.message || "Order status updated");
					cancelStatusDraft();
					return;
				}
				showToast((res && res.data && res.data.message) || "Could not update order status", "error");
			})
			.fail(function (xhr) {
				var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : "Could not update order status";
				showToast(message, "error");
			})
			.always(function () {
				if (button) {
					button.disabled = false;
				}
			});
	}

	function prepareOrderNoteDraft() {
		var root = document.getElementById("checkflow-admin");
		var detail = root && root._cfActiveOrderDetail ? root._cfActiveOrderDetail : null;
		if (!root || !detail) {
			showToast("Open an order first", "error");
			return;
		}
		var type = root.querySelector("[data-order-note-type]");
		var text = root.querySelector("[data-order-note-text]");
		var preview = root.querySelector("[data-order-note-preview]");
		var note = text ? text.value.trim() : "";
		if (!note) {
			showToast("Write a note first", "error");
			return;
		}
		var ajaxUrl = getAdminAjaxUrl();
		if (!ajaxUrl || !detail.orderId) {
			showToast("Could not save note", "error");
			return;
		}
		var button = root.querySelector("[data-order-note-draft]");
		if (button) {
			button.disabled = true;
		}
		$.ajax({
			url: ajaxUrl,
			method: "POST",
			dataType: "json",
			data: {
				action: "checkflow_add_order_note",
				nonce: checkflowAdmin.nonce,
				order_id: detail.orderId,
				note_type: type ? type.value : "internal",
				note: note,
			},
		})
			.done(function (res) {
				if (res && res.success) {
					var noteType = res.data && res.data.note_type === "customer" ? "Customer note" : "Internal note";
					if (preview) {
						preview.textContent = noteType + " saved to " + detail.id + ": " + note;
						preview.hidden = false;
					}
					setOrderActivity(noteType + " saved to WooCommerce order notes.");
					if (text) {
						text.value = "";
					}
					showToast((res.data && res.data.message) || noteType + " saved");
					return;
				}
				showToast((res && res.data && res.data.message) || "Could not save note", "error");
			})
			.fail(function (xhr) {
				var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : "Could not save note";
				showToast(message, "error");
			})
			.always(function () {
				if (button) {
					button.disabled = false;
				}
			});
	}

	function clearOrderNoteDraft() {
		var root = document.getElementById("checkflow-admin");
		if (!root) {
			return;
		}
		var text = root.querySelector("[data-order-note-text]");
		var preview = root.querySelector("[data-order-note-preview]");
		if (text) {
			text.value = "";
			text.focus();
		}
		if (preview) {
			preview.hidden = true;
			preview.textContent = "";
		}
	}

	function selectedOrderDetails() {
		return getSelectedOrderRows().map(parseOrderDetail).map(normalizeOrderDetail).filter(Boolean);
	}

	function exportSelectedOrders() {
		var orders = selectedOrderDetails();
		if (!orders.length) {
			showToast("Select orders first", "error");
			return;
		}
		var headers = ["Order", "Customer", "Phone", "Email", "Payment", "Courier", "Amount", "Status", "Date"];
		var lines = [headers.join(",")];
		orders.forEach(function (order) {
			var values = [order.id, order.customer, order.phone, order.email, order.payment, order.courier, order.amount, order.status, order.date];
			lines.push(values.map(function (value) {
				return '"' + String(value || "").replace(/"/g, '""') + '"';
			}).join(","));
		});
		var blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8" });
		var url = URL.createObjectURL(blob);
		var a = document.createElement("a");
		a.href = url;
		a.download = "checkflow-orders.csv";
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
		showToast("Selected orders exported");
	}

	function handleOrderBulkAction(action) {
		var count = selectedOrderDetails().length;
		if (!count) {
			showToast("Select orders first", "error");
			return;
		}
		if (action === "export") {
			exportSelectedOrders();
			return;
		}
		if (action === "courier") {
			showToast("Open an order drawer to prepare courier draft");
			return;
		}
		if (action === "followup") {
			showToast(count + " payment follow-up items prepared");
		}
	}

	function collectCourierSettings() {
		var root = document.getElementById("checkflow-admin");
		var data = {};
		if (!root) {
			return data;
		}
		root.querySelectorAll("[data-courier-setting]").forEach(function (field) {
			var key = field.getAttribute("data-courier-setting");
			if (!key) {
				return;
			}
			data[key] = field.type === "checkbox" ? (field.checked ? "1" : "0") : field.value;
		});
		var checkedDefault = root.querySelector("[data-courier-default]:checked");
		data.default_provider = checkedDefault ? checkedDefault.value : "pathao";
		return data;
	}

	function collectPixelSettings() {
		var root = document.getElementById("checkflow-admin");
		var data = {};
		if (!root) {
			return data;
		}
		root.querySelectorAll("[data-pixel-setting]").forEach(function (field) {
			if (field.closest(".cf-pixel-settings")) {
				return;
			}
			var key = field.getAttribute("data-pixel-setting");
			if (!key) {
				return;
			}
			data[key] = field.type === "checkbox" ? (field.checked ? "1" : "0") : field.value;
		});
		return data;
	}

	function savePixelSettings(buttonEl) {
		var ajaxUrl = getAdminAjaxUrl();
		if (!ajaxUrl) {
			return;
		}
		var data = collectPixelSettings();
		data.action = "checkflow_save_pixel_settings";
		data.nonce = checkflowAdmin.nonce;
		var button = buttonEl || document.querySelector("[data-save-pixel-settings]");
		var status = document.querySelector("[data-pixel-save-status]");
		if (button) {
			button.disabled = true;
		}
		$.ajax({
			url: ajaxUrl,
			method: "POST",
			dataType: "json",
			data: data,
		})
			.done(function (res) {
				if (res && res.success) {
					if (window.checkflowAdmin) {
						checkflowAdmin.pixelSettings = res.data.settings || data;
					}
					if (status) {
						status.textContent = (res.data.message || "Pixel settings saved.") + " Local log is active when enabled; external real validation remains for the final tracking pass.";
					}
					showToast(res.data.message || "Pixel settings saved");
					return;
				}
				showToast((res && res.data && res.data.message) || "Could not save pixel settings", "error");
			})
			.fail(function (xhr) {
				var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : "Could not save pixel settings";
				showToast(message, "error");
			})
			.always(function () {
				if (button) {
					button.disabled = false;
				}
			});
	}

	function setupPixelInsights() {
		var root = document.getElementById("checkflow-admin");
		if (!root) {
			return;
		}
		var detail = root.querySelector("[data-pixel-detail]");
		var status = root.querySelector("[data-pixel-chart-status]");
		var empty = root.querySelector("[data-pixel-filter-empty]");
		function setFilter(name) {
			var visible = 0;
			root.querySelectorAll("[data-pixel-filter]").forEach(function (button) {
				button.classList.toggle("is-active", button.getAttribute("data-pixel-filter") === name);
			});
			root.querySelectorAll("[data-pixel-event-row]").forEach(function (row) {
				var match = name === "all" || row.getAttribute("data-event-name") === name;
				row.hidden = !match;
				if (match) {
					visible += 1;
				}
			});
			if (status) {
				status.textContent = name === "all" ? "Showing all recent local events." : "Showing " + visible + " recent " + name + " event" + (visible === 1 ? "." : "s.");
			}
			if (empty) {
				empty.hidden = visible !== 0;
			}
		}
		function toggleProvider(card) {
			if (!card) {
				return;
			}
			root.querySelectorAll(".cf-pixel-card").forEach(function (item) {
				item.classList.toggle("is-open", item === card && !item.classList.contains("is-open"));
			});
			if (!card.classList.contains("is-open")) {
				card.classList.add("is-open");
			}
		}
		function showDetail(row) {
			if (!detail || !row) {
				return;
			}
			root.querySelectorAll("[data-pixel-event-row]").forEach(function (item) {
				item.classList.toggle("is-selected", item === row);
			});
			var context = {};
			try {
				context = JSON.parse(row.getAttribute("data-event-context") || "{}");
			} catch (error) {
				context = {};
			}
			var contextText = Object.keys(context).length ? JSON.stringify(context, null, 2) : "No extra context";
			detail.innerHTML =
				"<strong>" +
				escapeHtml(row.getAttribute("data-event-name") || "Event") +
				"</strong><span>" +
				escapeHtml(row.getAttribute("data-event-summary") || "") +
				"</span><code>" +
				escapeHtml(row.getAttribute("data-event-id") || "") +
				"</code><small>" +
				escapeHtml(row.getAttribute("data-event-url") || "") +
				"</small><pre>" +
				escapeHtml(contextText) +
				"</pre>";
		}
		$(document).on("click", "[data-pixel-filter]", function () {
			setFilter(this.getAttribute("data-pixel-filter") || "all");
		});
		$(document).on("click", "[data-pixel-event-row]", function () {
			showDetail(this);
		});
		$(document).on("click", "[data-pixel-provider-toggle]", function () {
			toggleProvider(this.closest(".cf-pixel-card"));
		});
		$(document).on("keydown", "[data-pixel-provider-toggle]", function (event) {
			if (event.key === "Enter" || event.key === " ") {
				event.preventDefault();
				toggleProvider(this.closest(".cf-pixel-card"));
			}
		});
		$(document).on("click", "[data-pixel-insights-toggle]", function () {
			var panel = this.closest(".cf-pixel-visuals");
			if (!panel) {
				return;
			}
			panel.classList.toggle("is-open");
			this.textContent = panel.classList.contains("is-open") ? "Hide chart" : "Show chart";
		});
		setFilter("all");
	}

	function saveCourierSettings(buttonEl) {
		var ajaxUrl = getAdminAjaxUrl();
		if (!ajaxUrl) {
			return;
		}
		var data = collectCourierSettings();
		data.action = "checkflow_save_courier_settings";
		data.nonce = checkflowAdmin.nonce;
		var button = buttonEl || document.querySelector("[data-save-courier-settings]");
		var status = document.querySelector("[data-courier-save-status]");
		if (button) {
			button.disabled = true;
		}
		$.ajax({
			url: ajaxUrl,
			method: "POST",
			dataType: "json",
			data: data,
		})
			.done(function (res) {
				if (res && res.success) {
					if (window.checkflowAdmin) {
						checkflowAdmin.courierSettings = res.data.settings || data;
					}
					var savedSettings = (res.data && res.data.settings) || data;
					if (status) {
						status.textContent = (res.data.message || "Courier settings saved.") + " Default: " + (savedSettings.default_provider || "pathao") + ". Live booking runs only from an order drawer.";
					}
					showToast(res.data.message || "Courier settings saved");
					return;
				}
				showToast((res && res.data && res.data.message) || "Could not save courier settings", "error");
			})
			.fail(function (xhr) {
				var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : "Could not save courier settings";
				showToast(message, "error");
			})
			.always(function () {
				if (button) {
					button.disabled = false;
				}
			});
	}

	function prepareCourierDraft(buttonEl) {
		var root = document.getElementById("checkflow-admin");
		var detail = root && root._cfActiveOrderDetail ? root._cfActiveOrderDetail : null;
		var ajaxUrl = getAdminAjaxUrl();
		if (!detail || !detail.orderId) {
			showToast("Open an order first", "error");
			return;
		}
		if (!ajaxUrl) {
			showToast("Could not prepare courier", "error");
			return;
		}
		var settings = (window.checkflowAdmin && checkflowAdmin.courierSettings) || {};
		var provider = settings.default_provider || "pathao";
		if (detail.courierStatus === "draft_ready" && detail.courierProvider === provider) {
			showToast("Courier draft is already ready");
			setOrderActivity("Courier draft is already ready for this provider. No duplicate note was added.");
			return;
		}
		var button = buttonEl || document.querySelector('[data-order-single-action="courier"]');
		if (button) {
			button.disabled = true;
		}
		$.ajax({
			url: ajaxUrl,
			method: "POST",
			dataType: "json",
			data: {
				action: "checkflow_prepare_courier",
				nonce: checkflowAdmin.nonce,
				order_id: detail.orderId,
				provider: provider,
			},
		})
			.done(function (res) {
				if (res && res.success && res.data && res.data.order) {
					var updated = normalizeOrderDetail(res.data.order);
					refreshOpenOrderDetail(updated);
					setOrderActivity((res.data.message || "Courier draft ready") + " Review the Pathao payload, then use Book Pathao live when ready.");
					showToast(res.data.message || "Courier draft ready");
					return;
				}
				showToast((res && res.data && res.data.message) || "Could not prepare courier", "error");
			})
			.fail(function (xhr) {
				var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : "Could not prepare courier";
				showToast(message, "error");
			})
			.always(function () {
				if (button) {
					button.disabled = false;
				}
			});
	}

	function reviewPathaoBooking(buttonEl) {
		var root = document.getElementById("checkflow-admin");
		var detail = root && root._cfActiveOrderDetail ? root._cfActiveOrderDetail : null;
		var ajaxUrl = getAdminAjaxUrl();
		if (!detail || !detail.orderId) {
			showToast("Open an order first", "error");
			return;
		}
		if (!ajaxUrl) {
			showToast("Could not review Pathao booking", "error");
			return;
		}
		var button = buttonEl || document.querySelector("[data-review-pathao-booking]");
		if (button) {
			button.disabled = true;
		}
		$.ajax({
			url: ajaxUrl,
			method: "POST",
			dataType: "json",
			data: {
				action: "checkflow_review_pathao_booking",
				nonce: checkflowAdmin.nonce,
				order_id: detail.orderId,
			},
		})
			.done(function (res) {
				if (res && res.success) {
					renderPathaoReview(res.data || {});
					showToast(res.data.message || "Pathao booking reviewed");
					return;
				}
				showToast((res && res.data && res.data.message) || "Could not review Pathao booking", "error");
			})
			.fail(function (xhr) {
				var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : "Could not review Pathao booking";
				showToast(message, "error");
			})
			.always(function () {
				if (button) {
					button.disabled = false;
				}
			});
	}

	function bookPathaoOrder(buttonEl) {
		var root = document.getElementById("checkflow-admin");
		var detail = root && root._cfActiveOrderDetail ? root._cfActiveOrderDetail : null;
		var ajaxUrl = getAdminAjaxUrl();
		if (!detail || !detail.orderId) {
			showToast("Open an order first", "error");
			return;
		}
		if (!ajaxUrl) {
			showToast("Could not book Pathao order", "error");
			return;
		}
		if (detail.courierStatus === "booked") {
			showToast("Pathao booking already exists");
			return;
		}
		if (!window.confirm("Create a live Pathao booking for " + (detail.id || "this order") + "?")) {
			return;
		}
		var button = buttonEl || document.querySelector("[data-book-pathao-order]");
		if (button) {
			button.disabled = true;
			button.textContent = "Booking...";
		}
		setOrderActivity("Contacting Pathao API...");
		$.ajax({
			url: ajaxUrl,
			method: "POST",
			dataType: "json",
			data: {
				action: "checkflow_book_pathao_order",
				nonce: checkflowAdmin.nonce,
				order_id: detail.orderId,
			},
		})
			.done(function (res) {
				if (res && res.success && res.data && res.data.order) {
					var updated = normalizeOrderDetail(res.data.order);
					refreshOpenOrderDetail(updated);
					setOrderActivity(res.data.message || "Pathao booking created");
					renderPathaoReview({
						message: res.data.message || "Pathao booking created",
						mode: ((window.checkflowAdmin && checkflowAdmin.courierSettings && checkflowAdmin.courierSettings.pathao_mode) || "sandbox"),
						baseUrl: ((window.checkflowAdmin && checkflowAdmin.courierSettings && checkflowAdmin.courierSettings.pathao_base_url) || "auto"),
						payload: { merchant_order_id: updated.id, recipient_name: updated.customer, amount_to_collect: updated.amount },
						missing: [],
					});
					showToast(res.data.message || "Pathao booking created");
					return;
				}
				showToast((res && res.data && res.data.message) || "Could not book Pathao order", "error");
			})
			.fail(function (xhr) {
				var data = xhr && xhr.responseJSON ? xhr.responseJSON.data : null;
				if (data && data.missing) {
					renderPathaoReview(data);
				}
				showToast((data && data.message) || "Could not book Pathao order", "error");
			})
			.always(function () {
				if (button) {
					button.disabled = false;
				}
				updateCourierAction(root && root._cfActiveOrderDetail ? root._cfActiveOrderDetail : detail);
			});
	}

	function copyOrderValue(type) {
		var root = document.getElementById("checkflow-admin");
		var detail = root && root._cfActiveOrderDetail ? root._cfActiveOrderDetail : null;
		var value = detail ? detail[type] : "";
		if (!value) {
			showToast("Nothing to copy", "error");
			return;
		}
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(value).then(function () {
				showToast(type === "phone" ? "Phone copied" : "Address copied");
			}).catch(function () {
				showToast("Could not copy", "error");
			});
		} else {
			showToast(value);
		}
	}

	function settingLabel(setting) {
		return (setting || "setting")
			.replace(/_/g, " ")
			.replace(/\b\w/g, function (m) {
				return m.toUpperCase();
			});
	}

	function getAdminAjaxUrl() {
		if (!window.checkflowAdmin || !checkflowAdmin.ajaxUrl) {
			return "";
		}

		try {
			var url = new URL(checkflowAdmin.ajaxUrl, window.location.href);
			if (url.origin !== window.location.origin && window.location.pathname.indexOf("/wp-admin/") === 0) {
				url.protocol = window.location.protocol;
				url.host = window.location.host;
			}
			return url.toString();
		} catch (e) {
			return checkflowAdmin.ajaxUrl;
		}
	}

	function toggleSetting(el) {
		var ajaxUrl = getAdminAjaxUrl();
		if (!ajaxUrl) return;
		var setting = el.getAttribute("data-setting");
		if (!setting) {
			var localNext = !el.classList.contains("on");
			el.classList.toggle("on", localNext);
			el.setAttribute("aria-checked", localNext ? "true" : "false");
			return;
		}
		if (el.classList.contains("is-saving")) return;

		var next = !el.classList.contains("on");
		el.classList.toggle("on", next);
		el.classList.add("is-saving");
		el.setAttribute("aria-checked", next ? "true" : "false");

		$.ajax({
			url: ajaxUrl,
			method: "POST",
			dataType: "json",
			data: {
				action: "checkflow_toggle_setting",
				nonce: checkflowAdmin.nonce,
				setting: setting,
				enabled: next ? 1 : 0,
			},
		})
			.done(function (res) {
				if (!res || !res.success) {
					el.classList.toggle("on", !next);
					el.setAttribute("aria-checked", !next ? "true" : "false");
					showToast("Could not save " + settingLabel(setting), "error");
					return;
				}
				if (checkflowAdmin.settings) {
					checkflowAdmin.settings[setting] = next;
				}
				showToast(settingLabel(setting) + (next ? " is ON" : " is OFF"));
			})
			.fail(function () {
				el.classList.toggle("on", !next);
				el.setAttribute("aria-checked", !next ? "true" : "false");
				showToast("Could not save " + settingLabel(setting), "error");
			})
			.always(function () {
				el.classList.remove("is-saving");
			});
	}

	function refreshStats(period) {
		var ajaxUrl = getAdminAjaxUrl();
		if (!ajaxUrl) return;
		$.ajax({
			url: ajaxUrl,
			method: "POST",
			dataType: "json",
			data: {
				action: "checkflow_get_stats",
				nonce: checkflowAdmin.nonce,
				period: period || "7d",
			},
		}).done(function (res) {
			if (res && res.success && res.data && res.data.dailyOrders) {
				checkflowAdmin.chartVals = res.data.dailyOrders;
				renderMiniChart();
			}
		});
	}

	function setTemplateUi(template, label) {
		var activeCard = null;
		document.querySelectorAll("[data-checkout-template]").forEach(function (card) {
			var key = card.getAttribute("data-checkout-template");
			var active = key === template;
			card.classList.toggle("is-active", active);
			if (active) activeCard = card;
			var button = card.querySelector("[data-save-checkout-template]");
			if (button) {
				button.textContent = active ? "Active" : "Use template";
				button.classList.toggle("btn-p", !active);
				button.classList.toggle("cf-btn-ghost", active);
			}
		});
		var current = document.querySelector("[data-template-current]");
		if (current) current.textContent = label || template;
		if (window.checkflowAdmin) {
			checkflowAdmin.checkoutTemplate = template;
		}
		showTemplatePairing(activeCard, label || template);
		updateTemplateCompare(activeCard || document.querySelector("[data-checkout-template]"));
	}

	function templateComparePoints(key, presetLabel) {
		var map = {
			default_one_page: ["Balanced two-column checkout", "Neutral inputs and summary styling", "Pairs with " + presetLabel + " fields"],
			bangladesh_cod: ["COD trust color and local delivery emphasis", "Phone-first checkout field structure", "Pairs with " + presetLabel + " fields"],
			minimal_digital: ["Lower visual weight for short digital checkout", "Hides heavy trust/urgency styling", "Pairs with " + presetLabel + " fields"],
			trust_checkout: ["Stronger reassurance across form and summary", "Highlighted payment, address, and trust modules", "Pairs with " + presetLabel + " fields"],
			compact_mobile: ["Tighter spacing and mobile-friendly field rhythm", "Compact cards and taller tap targets", "Pairs with " + presetLabel + " fields"],
		};
		return map[key] || ["Visual checkout template changes", "Order/payment flow remains native", "Pairs with " + presetLabel + " fields"];
	}

	function updateTemplateCompare(card) {
		var root = document.querySelector("[data-template-compare]");
		if (!root || !card) return;
		var currentCard = document.querySelector(".cf-template-card.is-active") || card;
		var selectedName = card.getAttribute("data-template-name") || "";
		var selectedCopy = card.getAttribute("data-template-description") || "";
		var selectedKey = card.getAttribute("data-checkout-template") || "";
		var presetLabel = card.getAttribute("data-template-field-preset-label") || "matching";
		var currentName = currentCard.getAttribute("data-template-name") || "";
		var currentCopy = currentCard.getAttribute("data-template-description") || "";
		var currentEl = root.querySelector("[data-template-compare-current]");
		var currentCopyEl = root.querySelector("[data-template-compare-current-copy]");
		var selectedEl = root.querySelector("[data-template-compare-selected]");
		var selectedCopyEl = root.querySelector("[data-template-compare-selected-copy]");
		var points = root.querySelector("[data-template-compare-points]");
		if (currentEl) currentEl.textContent = currentName;
		if (currentCopyEl) currentCopyEl.textContent = currentCopy;
		if (selectedEl) selectedEl.textContent = selectedName;
		if (selectedCopyEl) selectedCopyEl.textContent = selectedCopy;
		if (points) {
			points.innerHTML = "";
			templateComparePoints(selectedKey, presetLabel).forEach(function (point) {
				var li = document.createElement("li");
				li.textContent = point;
				points.appendChild(li);
			});
		}
	}

	function showTemplatePairing(card, templateLabel) {
		var box = document.querySelector("[data-template-pairing]");
		if (!box || !card) return;
		var preset = card.getAttribute("data-template-field-preset") || "";
		var presetLabel = card.getAttribute("data-template-field-preset-label") || "";
		if (!preset) {
			box.hidden = true;
			return;
		}
		box.hidden = false;
		box.setAttribute("data-pairing-preset", preset);
		box.setAttribute("data-pairing-preset-label", presetLabel);
		var title = box.querySelector("[data-template-pairing-title]");
		var copy = box.querySelector("[data-template-pairing-copy]");
		if (title) title.textContent = "Pair " + templateLabel + " with " + presetLabel + " fields";
		if (copy) copy.textContent = "This aligns checkout fields, ordering, validation, and custom fields with the selected template. Review changes before saving.";
	}

	function templateAutoPairEnabled() {
		try {
			return window.localStorage.getItem("checkflow_template_auto_pair") === "1";
		} catch (e) {
			return false;
		}
	}

	function setTemplateAutoPairEnabled(enabled) {
		try {
			window.localStorage.setItem("checkflow_template_auto_pair", enabled ? "1" : "0");
		} catch (e) {}
		document.querySelectorAll("[data-template-auto-pair]").forEach(function (input) {
			input.checked = !!enabled;
		});
	}

	function activeTemplatePairingPreset() {
		var box = document.querySelector("[data-template-pairing]");
		return box ? box.getAttribute("data-pairing-preset") || "" : "";
	}

	function applyTemplatePairingPreset(preset, silent) {
		if (!preset || !fieldPresets[preset]) {
			showToast("No matching field preset found", "error");
			return;
		}
		setPane("field_editor");
		window.setTimeout(function () {
			applyFieldPreset(preset, { skipConfirm: !!silent, source: silent ? "auto" : "manual" });
		}, 120);
	}

	function saveCheckoutTemplate(template, btnEl) {
		var ajaxUrl = getAdminAjaxUrl();
		if (!ajaxUrl || !template) return;
		var $btn = $(btnEl);
		$btn.prop("disabled", true);
		$.ajax({
			url: ajaxUrl,
			method: "POST",
			dataType: "json",
			data: {
				action: "checkflow_save_checkout_template",
				nonce: checkflowAdmin.nonce,
				template: template,
			},
		})
			.done(function (res) {
				if (!res || !res.success || !res.data) {
					showToast("Could not save checkout template", "error");
					return;
				}
				setTemplateUi(res.data.template, res.data.label);
				showToast(res.data.label + " template is active");
				if (templateAutoPairEnabled()) {
					applyTemplatePairingPreset(activeTemplatePairingPreset(), true);
				}
			})
			.fail(function () {
				showToast("Could not save checkout template", "error");
			})
			.always(function () {
				$btn.prop("disabled", false);
			});
	}

	function persistLocale(locale) {
		var ajaxUrl = getAdminAjaxUrl();
		if (!ajaxUrl) {
			return;
		}
		$.ajax({
			url: ajaxUrl,
			method: "POST",
			dataType: "json",
			data: {
				action: "checkflow_set_admin_locale",
				nonce: checkflowAdmin.nonce,
				locale: locale,
			},
		}).done(function (res) {
			if (res && res.success) {
				window.location.reload();
			}
		});
	}

	function gatherOverrides() {
		var out = {};
		document.querySelectorAll(".cf-str-custom").forEach(function (el) {
			var k = el.getAttribute("data-key");
			if (!k) return;
			out[k] = el.value || "";
		});
		return out;
	}

	function saveOverrides(targetLocale, btnEl) {
		var ajaxUrl = getAdminAjaxUrl();
		if (!ajaxUrl) return;
		var $btn = $(btnEl);
		$btn.prop("disabled", true);
		$.ajax({
			url: ajaxUrl,
			method: "POST",
			dataType: "json",
			data: {
				action: "checkflow_save_string_overrides",
				nonce: checkflowAdmin.nonce,
				target_locale: targetLocale,
				overrides: JSON.stringify(gatherOverrides()),
			},
		})
			.done(function (res) {
				if (res && res.success) {
					window.location.reload();
				}
			})
			.always(function () {
				$btn.prop("disabled", false);
			});
	}

	function collectCheckoutFields() {
		var rows = [];
		document.querySelectorAll(".cf-field-row").forEach(function (row) {
			var key = row.getAttribute("data-field-key");
			if (!key) return;
			var locked = row.getAttribute("data-protected") === "1";
			var custom = row.getAttribute("data-field-custom") === "1";
			var enabled = row.querySelector(".cf-field-enabled");
			var required = row.querySelector(".cf-field-required");
			var label = row.querySelector(".cf-field-label");
			var priority = row.querySelector(".cf-field-priority");
			var placeholder = row.querySelector(".cf-field-placeholder");
			var help = row.querySelector(".cf-field-help");
			var width = row.querySelector(".cf-field-width");
			var defaultValue = row.querySelector(".cf-field-default-value");
			var validation = row.querySelector(".cf-field-validation");
			var min = row.querySelector(".cf-field-min");
			var max = row.querySelector(".cf-field-max");
			var minLength = row.querySelector(".cf-field-min-length");
			var maxLength = row.querySelector(".cf-field-max-length");
			var requiredMessage = row.querySelector(".cf-field-required-message");
			var validationMessage = row.querySelector(".cf-field-validation-message");
			var conditionEnabled = row.querySelector(".cf-field-condition-enabled");
			var conditionAction = row.querySelector(".cf-field-condition-action");
			var conditionSource = row.querySelector(".cf-field-condition-source");
			var conditionOperator = row.querySelector(".cf-field-condition-operator");
			var conditionValue = row.querySelector(".cf-field-condition-value");
			var conditionField = row.querySelector(".cf-field-condition-field");
			var options = [];
			try {
				options = JSON.parse(row.getAttribute("data-field-options") || "[]");
			} catch (e) {
				options = [];
			}
			rows.push({
				key: key,
				label: label ? label.value : "",
				priority: priority ? priority.value : "10",
				enabled: locked || (enabled && enabled.checked) ? 1 : 0,
				required: required && required.checked ? 1 : 0,
				custom: custom ? 1 : 0,
				type: row.getAttribute("data-field-type") || "text",
				group: row.getAttribute("data-field-group") || "",
				options: options,
				placeholder: placeholder ? placeholder.value : "",
				help: help ? help.value : "",
				width: width ? width.value : "default",
				default_value: defaultValue ? defaultValue.value : "",
				validation: validation ? validation.value : "none",
				min: min ? min.value : "",
				max: max ? max.value : "",
				min_length: minLength ? minLength.value : "",
				max_length: maxLength ? maxLength.value : "",
				required_message: requiredMessage ? requiredMessage.value : "",
				validation_message: validationMessage ? validationMessage.value : "",
				condition: {
					enabled: conditionEnabled && conditionEnabled.checked ? 1 : 0,
					action: conditionAction ? conditionAction.value : "show",
					source: conditionSource ? conditionSource.value : "payment_method",
					operator: conditionOperator ? conditionOperator.value : "equals",
					value: conditionValue ? conditionValue.value : "",
					field: conditionField ? conditionField.value : "",
				},
			});
		});
		return rows;
	}

	function fieldExportEnvelope() {
		return {
			schema: "checkflow-field-setup",
			version: 1,
			source: "CheckFlow",
			exported_at: new Date().toISOString(),
			fields: collectCheckoutFields(),
		};
	}

	function downloadTextFile(filename, text) {
		var blob = new Blob([text], { type: "application/json;charset=utf-8" });
		var url = window.URL.createObjectURL(blob);
		var link = document.createElement("a");
		link.href = url;
		link.download = filename;
		document.body.appendChild(link);
		link.click();
		link.remove();
		window.setTimeout(function () {
			window.URL.revokeObjectURL(url);
		}, 250);
	}

	function exportFieldSetup() {
		var payload = fieldExportEnvelope();
		var stamp = new Date().toISOString().slice(0, 10);
		downloadTextFile("checkflow-field-setup-" + stamp + ".json", JSON.stringify(payload, null, 2));
		showToast("Field setup exported");
	}

	function normalizeImportedFields(payload) {
		var fields = Array.isArray(payload) ? payload : payload && Array.isArray(payload.fields) ? payload.fields : null;
		var groups = ["billing", "shipping", "order"];
		var types = ["text", "select", "checkbox", "date", "textarea"];
		var widths = ["default", "full", "half", "first", "last"];
		var validations = ["none", "email", "phone", "number", "text"];
		if (!fields) return [];
		return fields
			.filter(function (field) {
				return field && typeof field === "object" && field.key;
			})
			.map(function (field) {
				var group = groups.indexOf(field.group) >= 0 ? field.group : "billing";
				var type = types.indexOf(field.type) >= 0 ? field.type : "text";
				var width = widths.indexOf(field.width) >= 0 ? field.width : "default";
				var validation = validations.indexOf(field.validation) >= 0 ? field.validation : "none";
				var options = Array.isArray(field.options)
					? field.options
							.map(function (item) {
								return String(item || "").trim();
							})
							.filter(Boolean)
					: [];
				return {
					key: String(field.key).replace(/[^a-z0-9_]/gi, "_").toLowerCase(),
					label: String(field.label || field.key || "Checkout field"),
					priority: parseInt(field.priority || 10, 10) || 10,
					enabled: field.enabled ? 1 : 0,
					required: field.required ? 1 : 0,
					custom: field.custom ? 1 : 0,
					type: type,
					group: group,
					options: options,
					placeholder: String(field.placeholder || ""),
					help: String(field.help || ""),
					width: width,
					default_value: String(field.default_value || ""),
					validation: validation,
					min: String(field.min || ""),
					max: String(field.max || ""),
					min_length: parseInt(field.min_length || 0, 10) || 0,
					max_length: parseInt(field.max_length || 0, 10) || 0,
					required_message: String(field.required_message || ""),
					validation_message: String(field.validation_message || ""),
					condition: field.condition && typeof field.condition === "object" ? field.condition : {},
				};
			});
	}

	function resetFieldRowToDefaults(row) {
		if (!row) return;
		var label = row.querySelector(".cf-field-label");
		var priority = row.querySelector(".cf-field-priority");
		var enabled = row.querySelector(".cf-field-enabled");
		var required = row.querySelector(".cf-field-required");
		var placeholder = row.querySelector(".cf-field-placeholder");
		var help = row.querySelector(".cf-field-help");
		var width = row.querySelector(".cf-field-width");
		var defaultValue = row.querySelector(".cf-field-default-value");
		var validation = row.querySelector(".cf-field-validation");
		var min = row.querySelector(".cf-field-min");
		var max = row.querySelector(".cf-field-max");
		var minLength = row.querySelector(".cf-field-min-length");
		var maxLength = row.querySelector(".cf-field-max-length");
		var requiredMessage = row.querySelector(".cf-field-required-message");
		var validationMessage = row.querySelector(".cf-field-validation-message");
		var conditionEnabled = row.querySelector(".cf-field-condition-enabled");
		var conditionAction = row.querySelector(".cf-field-condition-action");
		var conditionSource = row.querySelector(".cf-field-condition-source");
		var conditionOperator = row.querySelector(".cf-field-condition-operator");
		var conditionValue = row.querySelector(".cf-field-condition-value");
		var conditionField = row.querySelector(".cf-field-condition-field");
		var title = row.querySelector(".cf-field-title strong");
		if (label) label.value = row.getAttribute("data-default-label") || label.value;
		if (priority) priority.value = row.getAttribute("data-default-priority") || priority.value;
		if (enabled && !enabled.disabled) enabled.checked = row.getAttribute("data-default-enabled") === "1";
		if (required) required.checked = row.getAttribute("data-default-required") === "1";
		if (placeholder) placeholder.value = row.getAttribute("data-default-placeholder") || "";
		if (help) help.value = row.getAttribute("data-default-help") || "";
		if (width) width.value = row.getAttribute("data-default-width") || "default";
		if (defaultValue) defaultValue.value = row.getAttribute("data-default-value") || "";
		if (validation) validation.value = row.getAttribute("data-default-validation") || "none";
		if (min) min.value = row.getAttribute("data-default-min") || "";
		if (max) max.value = row.getAttribute("data-default-max") || "";
		if (minLength) minLength.value = row.getAttribute("data-default-min-length") || "0";
		if (maxLength) maxLength.value = row.getAttribute("data-default-max-length") || "0";
		if (requiredMessage) requiredMessage.value = row.getAttribute("data-default-required-message") || "";
		if (validationMessage) validationMessage.value = row.getAttribute("data-default-validation-message") || "";
		if (conditionEnabled) conditionEnabled.checked = row.getAttribute("data-default-condition-enabled") === "1";
		if (conditionAction) conditionAction.value = row.getAttribute("data-default-condition-action") || "show";
		if (conditionSource) conditionSource.value = row.getAttribute("data-default-condition-source") || "payment_method";
		if (conditionOperator) conditionOperator.value = row.getAttribute("data-default-condition-operator") || "equals";
		if (conditionValue) conditionValue.value = row.getAttribute("data-default-condition-value") || "";
		if (conditionField) conditionField.value = row.getAttribute("data-default-condition-field") || "";
		if (title && label) title.textContent = label.value;
		updateFieldPreview(row);
	}

	function setFieldEditorDirty(dirty) {
		fieldEditorDirty = !!dirty;
		var root = document.getElementById("checkflow-admin");
		if (!root) return;
		var state = root.querySelector(".cf-field-save-state");
		var save = root.querySelector(".cf-save-fields");
		if (state) {
			state.textContent = fieldEditorDirty ? "Unsaved changes" : "Saved";
			state.classList.toggle("is-dirty", fieldEditorDirty);
		}
		if (save) {
			save.classList.toggle("is-dirty", fieldEditorDirty);
		}
	}

	function updateFieldSearch() {
		var root = document.getElementById("checkflow-admin");
		if (!root) return;
		var input = root.querySelector(".cf-field-search");
		var query = input ? input.value.trim().toLowerCase() : "";
		root.querySelectorAll(".cf-field-row").forEach(function (row) {
			var title = row.querySelector(".cf-field-title strong");
			var haystack = [
				row.getAttribute("data-field-key") || "",
				row.getAttribute("data-field-type") || "",
				title ? title.textContent : "",
				row.getAttribute("data-field-custom") === "1" ? "custom" : "core",
			]
				.join(" ")
				.toLowerCase();
			row.classList.toggle("is-filtered", !!query && haystack.indexOf(query) === -1);
		});
	}

	function activeFieldPanel() {
		var root = document.getElementById("checkflow-admin");
		return root ? root.querySelector(".cf-field-panel.is-active") : null;
	}

	function resetActiveFieldTab() {
		var panel = activeFieldPanel();
		if (!panel) return;
		panel.querySelectorAll(".cf-field-row").forEach(function (row) {
			if (row.getAttribute("data-field-custom") === "1") {
				row.remove();
				return;
			}
			var label = row.querySelector(".cf-field-label");
			var priority = row.querySelector(".cf-field-priority");
			var enabled = row.querySelector(".cf-field-enabled");
			var required = row.querySelector(".cf-field-required");
			var placeholder = row.querySelector(".cf-field-placeholder");
			var help = row.querySelector(".cf-field-help");
			var width = row.querySelector(".cf-field-width");
			var defaultValue = row.querySelector(".cf-field-default-value");
			var validation = row.querySelector(".cf-field-validation");
			var min = row.querySelector(".cf-field-min");
			var max = row.querySelector(".cf-field-max");
			var minLength = row.querySelector(".cf-field-min-length");
			var maxLength = row.querySelector(".cf-field-max-length");
			var requiredMessage = row.querySelector(".cf-field-required-message");
			var validationMessage = row.querySelector(".cf-field-validation-message");
			var conditionEnabled = row.querySelector(".cf-field-condition-enabled");
			var conditionAction = row.querySelector(".cf-field-condition-action");
			var conditionSource = row.querySelector(".cf-field-condition-source");
			var conditionOperator = row.querySelector(".cf-field-condition-operator");
			var conditionValue = row.querySelector(".cf-field-condition-value");
			var conditionField = row.querySelector(".cf-field-condition-field");
			var title = row.querySelector(".cf-field-title strong");
			if (label) label.value = row.getAttribute("data-default-label") || label.value;
			if (priority) priority.value = row.getAttribute("data-default-priority") || priority.value;
			if (enabled && !enabled.disabled) enabled.checked = row.getAttribute("data-default-enabled") === "1";
			if (required) required.checked = row.getAttribute("data-default-required") === "1";
			if (placeholder) placeholder.value = row.getAttribute("data-default-placeholder") || "";
			if (help) help.value = row.getAttribute("data-default-help") || "";
			if (width) width.value = row.getAttribute("data-default-width") || "default";
			if (defaultValue) defaultValue.value = row.getAttribute("data-default-value") || "";
			if (validation) validation.value = row.getAttribute("data-default-validation") || "none";
			if (min) min.value = row.getAttribute("data-default-min") || "";
			if (max) max.value = row.getAttribute("data-default-max") || "";
			if (minLength) minLength.value = row.getAttribute("data-default-min-length") || "0";
			if (maxLength) maxLength.value = row.getAttribute("data-default-max-length") || "0";
			if (requiredMessage) requiredMessage.value = row.getAttribute("data-default-required-message") || "";
			if (validationMessage) validationMessage.value = row.getAttribute("data-default-validation-message") || "";
			if (conditionEnabled) conditionEnabled.checked = row.getAttribute("data-default-condition-enabled") === "1";
			if (conditionAction) conditionAction.value = row.getAttribute("data-default-condition-action") || "show";
			if (conditionSource) conditionSource.value = row.getAttribute("data-default-condition-source") || "payment_method";
			if (conditionOperator) conditionOperator.value = row.getAttribute("data-default-condition-operator") || "equals";
			if (conditionValue) conditionValue.value = row.getAttribute("data-default-condition-value") || "";
			if (conditionField) conditionField.value = row.getAttribute("data-default-condition-field") || "";
			if (title && label) title.textContent = label.value;
			updateFieldPreview(row);
			row.classList.add("is-reordered");
			window.setTimeout(function () {
				row.classList.remove("is-reordered");
			}, 520);
		});
		setFieldEditorDirty(true);
		updateFieldSearch();
		showToast("Current tab reset. Save to apply.");
	}

	function deleteCustomField(button) {
		var row = button && button.closest ? button.closest(".cf-field-row") : null;
		if (!row || row.getAttribute("data-field-custom") !== "1") return;
		var label = row.querySelector(".cf-field-title strong");
		var name = label ? label.textContent : "custom field";
		if (!window.confirm("Delete " + name + "? Save fields to apply this change.")) {
			return;
		}
		row.remove();
		setFieldEditorDirty(true);
		showToast("Custom field removed. Save to apply.");
	}

	function updateFieldPositionLabel(ui) {
		if (!ui || !ui.helper || !ui.item) return;
		var list = ui.placeholder && ui.placeholder.parent();
		var total = list ? list.children(".cf-field-row").length : 0;
		var position = ui.placeholder ? ui.placeholder.index() + 1 : ui.item.index() + 1;
		ui.helper.attr("data-position-label", "Position " + position + " of " + total);
	}

	function updateFieldPreview(row) {
		if (!row) return;
		var preview = row.querySelector(".cf-field-preview");
		if (!preview) return;
		var label = row.querySelector(".cf-field-label");
		var placeholder = row.querySelector(".cf-field-placeholder");
		var help = row.querySelector(".cf-field-help");
		var defaultValue = row.querySelector(".cf-field-default-value");
		var title = preview.querySelector("strong");
		var sample = preview.querySelector("em");
		var note = preview.querySelector("small");
		if (title) title.textContent = label && label.value ? label.value : row.getAttribute("data-field-key") || "Checkout field";
		if (sample) sample.textContent = defaultValue && defaultValue.value ? defaultValue.value : placeholder && placeholder.value ? placeholder.value : "Customer input";
		if (note) note.textContent = help && help.value ? help.value : "No help text";
	}

	function toggleFieldSettings(button) {
		var row = button && button.closest ? button.closest(".cf-field-row") : null;
		var drawer = row ? row.querySelector(".cf-field-advanced") : null;
		if (!row || !drawer) return;
		var open = drawer.hasAttribute("hidden");
		drawer.toggleAttribute("hidden", !open);
		row.classList.toggle("is-expanded", open);
		button.setAttribute("aria-expanded", open ? "true" : "false");
		updateFieldPreview(row);
	}

	function reindexCheckoutFields(scope) {
		var root = scope || document;
		var lists = root.classList && root.classList.contains("cf-field-list") ? [root] : root.querySelectorAll(".cf-field-list");
		Array.prototype.slice.call(lists).forEach(function (list) {
			Array.prototype.slice.call(list.querySelectorAll(".cf-field-row")).forEach(function (row, index) {
				var input = row.querySelector(".cf-field-priority");
				if (input) {
					input.value = String((index + 1) * 10);
				}
			});
		});
	}

	function rowRects(list) {
		var rects = new Map();
		Array.prototype.slice.call(list.querySelectorAll(".cf-field-row")).forEach(function (row) {
			rects.set(row, row.getBoundingClientRect());
		});
		return rects;
	}

	function animateFieldRows(list, beforeRects) {
		Array.prototype.slice.call(list.querySelectorAll(".cf-field-row")).forEach(function (row) {
			var before = beforeRects.get(row);
			if (!before) return;
			var after = row.getBoundingClientRect();
			var dx = before.left - after.left;
			var dy = before.top - after.top;
			if (!dx && !dy) return;
			row.style.transition = "none";
			row.style.transform = "translate(" + dx + "px, " + dy + "px)";
			row.offsetHeight; // Force layout before returning to natural position.
			row.style.transition = "";
			row.style.transform = "";
		});
	}

	function moveCheckoutField(button) {
		var row = button && button.closest ? button.closest(".cf-field-row") : null;
		var list = row && row.parentNode;
		if (!row || !list) return;
		var direction = button.getAttribute("data-field-move");
		var beforeRects = rowRects(list);
		var moved = false;
		if (direction === "up" && row.previousElementSibling) {
			list.insertBefore(row, row.previousElementSibling);
			moved = true;
		}
		if (direction === "down" && row.nextElementSibling) {
			list.insertBefore(row.nextElementSibling, row);
			moved = true;
		}
		if (!moved) {
			row.classList.add("is-reordered");
			window.setTimeout(function () {
				row.classList.remove("is-reordered");
			}, 260);
			return;
		}
		reindexCheckoutFields(list);
		animateFieldRows(list, beforeRects);
		row.classList.add("is-reordered");
		setFieldEditorDirty(true);
		window.setTimeout(function () {
			row.classList.remove("is-reordered");
		}, 520);
	}

	function fieldRowAfterPointer(list, y) {
		var rows = Array.prototype.slice.call(list.querySelectorAll(".cf-field-row:not(.is-dragging)"));
		return rows.reduce(
			function (closest, child) {
				var box = child.getBoundingClientRect();
				var offset = y - box.top - box.height / 2;
				if (offset < 0 && offset > closest.offset) {
					return { offset: offset, element: child };
				}
				return closest;
			},
			{ offset: Number.NEGATIVE_INFINITY, element: null }
		).element;
	}

	function finishFieldDrag(row, changed) {
		if (!row) return;
		var list = row.parentNode;
		row.classList.remove("is-dragging");
		document.documentElement.classList.remove("cf-field-dragging");
		draggedFieldRow = null;
		if (list) {
			reindexCheckoutFields(list);
		}
		if (changed) {
			row.classList.add("is-reordered");
			setFieldEditorDirty(true);
			window.setTimeout(function () {
				row.classList.remove("is-reordered");
			}, 520);
		}
	}

	function updatePointerFieldDrag(event) {
		if (!pointerFieldDrag || !pointerFieldDrag.row || !pointerFieldDrag.list) return;
		event.preventDefault();
		var clientY = getFieldDragClientY(event);
		if (clientY === null) return;
		var list = pointerFieldDrag.list;
		var beforeRects = rowRects(list);
		var beforeParent = pointerFieldDrag.row.parentNode;
		var beforeNext = pointerFieldDrag.row.nextElementSibling;
		var after = fieldRowAfterPointer(list, clientY);
		if (after == null) {
			list.appendChild(pointerFieldDrag.row);
		} else if (after !== pointerFieldDrag.row) {
			list.insertBefore(pointerFieldDrag.row, after);
		}
		if (beforeParent !== pointerFieldDrag.row.parentNode || beforeNext !== pointerFieldDrag.row.nextElementSibling) {
			pointerFieldDrag.changed = true;
			reindexCheckoutFields(list);
			animateFieldRows(list, beforeRects);
		}
	}

	function endPointerFieldDrag(event) {
		if (!pointerFieldDrag) return;
		if (event && event.cancelable) {
			event.preventDefault();
		}
		var drag = pointerFieldDrag;
		pointerFieldDrag = null;
		if (drag.handle && drag.pointerId != null && drag.handle.releasePointerCapture) {
			try {
				drag.handle.releasePointerCapture(drag.pointerId);
			} catch (e) {}
		}
		document.removeEventListener("pointermove", updatePointerFieldDrag);
		document.removeEventListener("pointerup", endPointerFieldDrag);
		document.removeEventListener("pointercancel", endPointerFieldDrag);
		document.removeEventListener("mousemove", updatePointerFieldDrag);
		document.removeEventListener("mouseup", endPointerFieldDrag);
		document.removeEventListener("touchmove", updatePointerFieldDrag);
		document.removeEventListener("touchend", endPointerFieldDrag);
		document.removeEventListener("touchcancel", endPointerFieldDrag);
		finishFieldDrag(drag.row, drag.changed);
	}

	function startPointerFieldDrag(event) {
		startFieldDrag(event);
	}

	function getFieldDragClientY(event) {
		if (event.touches && event.touches.length) {
			return event.touches[0].clientY;
		}
		if (event.changedTouches && event.changedTouches.length) {
			return event.changedTouches[0].clientY;
		}
		if (typeof event.clientY === "number") {
			return event.clientY;
		}
		return null;
	}

	function startFieldDrag(event) {
		if (pointerFieldDrag) return;
		var handle = event.target && event.target.closest ? event.target.closest("[data-field-drag]") : null;
		var row = handle && handle.closest ? handle.closest(".cf-field-row") : null;
		var list = row && row.closest ? row.closest(".cf-field-list") : null;
		if (!handle || !row || !list) return;
		event.preventDefault();
		pointerFieldDrag = {
			row: row,
			list: list,
			handle: handle,
			pointerId: event.pointerId,
			changed: false,
		};
		draggedFieldRow = row;
		row.classList.add("is-dragging");
		document.documentElement.classList.add("cf-field-dragging");
		if (handle.setPointerCapture && event.pointerId != null) {
			try {
				handle.setPointerCapture(event.pointerId);
			} catch (e) {}
		}
		document.addEventListener("pointermove", updatePointerFieldDrag);
		document.addEventListener("pointerup", endPointerFieldDrag);
		document.addEventListener("pointercancel", endPointerFieldDrag);
		document.addEventListener("mousemove", updatePointerFieldDrag);
		document.addEventListener("mouseup", endPointerFieldDrag);
		document.addEventListener("touchmove", updatePointerFieldDrag, { passive: false });
		document.addEventListener("touchend", endPointerFieldDrag);
		document.addEventListener("touchcancel", endPointerFieldDrag);
	}

	function bindFieldDragEvents() {
		if ($.fn && $.fn.sortable) {
			$(".cf-field-list").sortable({
				axis: "y",
				handle: "[data-field-drag]",
				items: ".cf-field-row",
				cancel: "input, select, textarea, label, [data-field-move], .cf-field-enabled, .cf-field-required",
				placeholder: "cf-field-row-placeholder",
				helper: "clone",
				appendTo: "#checkflow-admin",
				forcePlaceholderSize: true,
				tolerance: "pointer",
				distance: 4,
				scroll: true,
				scrollSensitivity: 72,
				scrollSpeed: 16,
				start: function (event, ui) {
					ui.helper.addClass("cf-field-drag-helper");
					ui.placeholder.height(ui.item.outerHeight());
					ui.item.addClass("is-dragging");
					document.documentElement.classList.add("cf-field-dragging");
					updateFieldPositionLabel(ui);
				},
				sort: function (event, ui) {
					updateFieldPositionLabel(ui);
				},
				update: function (event, ui) {
					reindexCheckoutFields(ui.item.closest(".cf-field-list")[0]);
					ui.item.addClass("is-reordered");
					setFieldEditorDirty(true);
					window.setTimeout(function () {
						ui.item.removeClass("is-reordered");
					}, 520);
				},
				stop: function (event, ui) {
					ui.item.removeClass("is-dragging");
					document.documentElement.classList.remove("cf-field-dragging");
				},
			});
			return;
		}

		document.addEventListener("pointerdown", startPointerFieldDrag);
		document.addEventListener("mousedown", startFieldDrag);
		document.addEventListener("touchstart", startFieldDrag, { passive: false });

		document.addEventListener("dragstart", function (event) {
			if (pointerFieldDrag) {
				event.preventDefault();
				return;
			}
			var row = event.target && event.target.closest ? event.target.closest(".cf-field-row") : null;
			if (!row) {
				return;
			}
			if (!event.target.closest("[data-field-drag]")) {
				event.preventDefault();
				return;
			}
			draggedFieldRow = row;
			row.classList.add("is-dragging");
			document.documentElement.classList.add("cf-field-dragging");
			if (event.dataTransfer) {
				event.dataTransfer.effectAllowed = "move";
				event.dataTransfer.setData("text/plain", row.getAttribute("data-field-key") || "");
			}
		});

		document.addEventListener("dragover", function (event) {
			if (!draggedFieldRow) return;
			var list = event.target && event.target.closest ? event.target.closest(".cf-field-list") : null;
			if (!list || list !== draggedFieldRow.parentNode) return;
			event.preventDefault();
			var beforeRects = rowRects(list);
			var after = fieldRowAfterPointer(list, event.clientY);
			if (after == null) {
				list.appendChild(draggedFieldRow);
			} else if (after !== draggedFieldRow) {
				list.insertBefore(draggedFieldRow, after);
			}
			animateFieldRows(list, beforeRects);
		});

		document.addEventListener("drop", function (event) {
			if (!draggedFieldRow) return;
			event.preventDefault();
			finishFieldDrag(draggedFieldRow, true);
		});

		document.addEventListener("dragend", function () {
			if (!draggedFieldRow) return;
			finishFieldDrag(draggedFieldRow, true);
		});
	}

	function setFieldGroup(group) {
		var root = document.getElementById("checkflow-admin");
		if (!root) return;
		root.querySelectorAll("[data-field-group-tab]").forEach(function (btn) {
			btn.classList.toggle("is-active", btn.getAttribute("data-field-group-tab") === group);
		});
		root.querySelectorAll("[data-field-group-panel]").forEach(function (panel) {
			panel.classList.toggle("is-active", panel.getAttribute("data-field-group-panel") === group);
		});
		updateFieldSearch();
	}

	function slugifyFieldKey(label) {
		var slug = String(label || "")
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, "_")
			.replace(/^_+|_+$/g, "");
		return "checkflow_custom_" + (slug || "field") + "_" + Date.now().toString(36);
	}

	function createCustomFieldRow(config) {
		var row = document.createElement("div");
		row.className = "cf-field-row is-reordered";
		row.setAttribute("data-field-key", config.key);
		row.setAttribute("data-field-group", config.group);
		row.setAttribute("data-field-type", config.type);
		row.setAttribute("data-field-custom", "1");
		row.setAttribute("data-field-options", JSON.stringify(config.options || []));
		row.setAttribute("data-protected", "0");
		row.setAttribute("data-default-label", config.label);
		row.setAttribute("data-default-priority", "999");
		row.setAttribute("data-default-enabled", "1");
		row.setAttribute("data-default-required", "0");
		row.setAttribute("data-default-placeholder", "");
		row.setAttribute("data-default-help", "");
		row.setAttribute("data-default-width", "default");
		row.setAttribute("data-default-value", "");
		row.setAttribute("data-default-validation", "none");
		row.setAttribute("data-default-min", "");
		row.setAttribute("data-default-max", "");
		row.setAttribute("data-default-min-length", "0");
		row.setAttribute("data-default-max-length", "0");
		row.setAttribute("data-default-required-message", "");
		row.setAttribute("data-default-validation-message", "");
		row.setAttribute("data-default-condition-enabled", "0");
		row.setAttribute("data-default-condition-action", "show");
		row.setAttribute("data-default-condition-source", "payment_method");
		row.setAttribute("data-default-condition-operator", "equals");
		row.setAttribute("data-default-condition-value", "");
		row.setAttribute("data-default-condition-field", "");
		row.innerHTML =
			'<div class="cf-field-move" aria-label="Reorder field">' +
			'<button type="button" class="cf-field-drag" data-field-drag aria-label="Drag to reorder">☰</button>' +
			'<button type="button" data-field-move="up" aria-label="Move up">↑</button>' +
			'<button type="button" data-field-move="down" aria-label="Move down">↓</button>' +
			"</div>" +
			'<div class="cf-field-main">' +
			'<div class="cf-field-title"><strong></strong><span></span><em>Custom</em></div>' +
			'<label><span>Checkout label</span><input type="text" class="cf-field-label" /></label>' +
			"</div>" +
			'<div class="cf-field-controls">' +
			'<label class="cf-field-mini"><span>Order</span><input type="number" class="cf-field-priority" min="1" max="999" value="999" /></label>' +
			'<label class="cf-field-switch"><input type="checkbox" class="cf-field-enabled" checked /><span>Show</span></label>' +
			'<label class="cf-field-switch"><input type="checkbox" class="cf-field-required" /><span>Required</span></label>' +
			'<button type="button" class="cf-field-settings" data-field-settings aria-expanded="false">Settings</button>' +
			'<button type="button" class="cf-field-delete" data-field-delete aria-label="Delete custom field">Delete</button>' +
			"</div>" +
			'<div class="cf-field-advanced" hidden>' +
			'<label><span>Placeholder</span><input type="text" class="cf-field-placeholder" placeholder="Shown inside the input" /></label>' +
			'<label><span>Help text</span><input type="text" class="cf-field-help" placeholder="Small note below the field" /></label>' +
			'<label><span>Width</span><select class="cf-field-width"><option value="default">Default</option><option value="full">Full width</option><option value="first">Half - left</option><option value="last">Half - right</option></select></label>' +
			'<label><span>Default value</span><input type="text" class="cf-field-default-value" placeholder="Optional prefilled value" /></label>' +
			'<label><span>Validation</span><select class="cf-field-validation"><option value="none">None</option><option value="email">Email</option><option value="phone">Phone</option><option value="number">Number</option><option value="text">Letters only</option></select></label>' +
			'<label><span>Number min/max</span><div class="cf-field-pair"><input type="number" class="cf-field-min" placeholder="Min" /><input type="number" class="cf-field-max" placeholder="Max" /></div></label>' +
			'<label><span>Length min/max</span><div class="cf-field-pair"><input type="number" class="cf-field-min-length" min="0" placeholder="Min" /><input type="number" class="cf-field-max-length" min="0" placeholder="Max" /></div></label>' +
			'<label><span>Required message</span><input type="text" class="cf-field-required-message" placeholder="Custom required error" /></label>' +
			'<label><span>Validation message</span><input type="text" class="cf-field-validation-message" placeholder="Custom invalid error" /></label>' +
			'<div class="cf-field-condition"><label class="cf-field-switch"><input type="checkbox" class="cf-field-condition-enabled" /><span>Conditional</span></label>' +
			'<label><span>Action</span><select class="cf-field-condition-action"><option value="show">Show when</option><option value="hide">Hide when</option></select></label>' +
			'<label><span>Source</span><select class="cf-field-condition-source"><option value="payment_method">Payment method</option><option value="billing_country">Billing country</option><option value="shipping_country">Shipping country</option><option value="field">Another field</option><option value="cart_total">Cart total</option><option value="product_id">Product ID in cart</option><option value="category_id">Category ID in cart</option></select></label>' +
			'<label><span>Operator</span><select class="cf-field-condition-operator"><option value="equals">Equals</option><option value="not_equals">Not equals</option><option value="contains">Contains</option><option value="greater_equal">Greater or equal</option><option value="less_equal">Less or equal</option><option value="checked">Checked</option><option value="not_checked">Not checked</option></select></label>' +
			'<label><span>Field key</span><input type="text" class="cf-field-condition-field" placeholder="For another field" /></label>' +
			'<label><span>Value</span><input type="text" class="cf-field-condition-value" placeholder="cod, BD, 100, product/category ID" /></label></div>' +
			'<div class="cf-field-preview" aria-hidden="true"><span>Preview</span><strong></strong><em>Customer input</em><small>No help text</small></div>' +
			"</div>";
		row.querySelector(".cf-field-title strong").textContent = config.label;
		row.querySelector(".cf-field-title span").textContent = config.key + " · " + config.type;
		row.querySelector(".cf-field-label").value = config.label;
		updateFieldPreview(row);
		return row;
	}

	var fieldPresets = {
		bangladesh_cod: {
			label: "Bangladesh COD",
			fields: {
				billing_first_name: { enabled: true, required: true, priority: 10, width: "first", placeholder: "Your first name" },
				billing_last_name: { enabled: true, required: true, priority: 20, width: "last", placeholder: "Your last name" },
				billing_phone: { enabled: true, required: true, priority: 30, width: "full", validation: "phone", placeholder: "01XXXXXXXXX", help: "We will call this number before delivery.", required_message: "Phone number is required for COD delivery.", validation_message: "Enter a valid phone number." },
				billing_email: { enabled: true, required: false, priority: 40, width: "full", validation: "email", placeholder: "you@example.com" },
				billing_country: { enabled: true, required: true, priority: 50, width: "full" },
				billing_address_1: { enabled: true, required: true, priority: 60, width: "full", placeholder: "House, road, area" },
				billing_city: { enabled: true, required: true, priority: 70, width: "first" },
				billing_state: { enabled: true, required: true, priority: 80, width: "last", label: "District" },
				billing_company: { enabled: false, required: false },
				billing_address_2: { enabled: false, required: false },
				billing_postcode: { enabled: false, required: false },
				shipping_first_name: { enabled: true, required: true, priority: 10, width: "first" },
				shipping_last_name: { enabled: true, required: true, priority: 20, width: "last" },
				shipping_country: { enabled: true, required: true, priority: 30, width: "full" },
				shipping_address_1: { enabled: true, required: true, priority: 40, width: "full", placeholder: "House, road, area" },
				shipping_city: { enabled: true, required: true, priority: 50, width: "first" },
				shipping_state: { enabled: true, required: true, priority: 60, width: "last", label: "District" },
				shipping_company: { enabled: false, required: false },
				shipping_address_2: { enabled: false, required: false },
				shipping_postcode: { enabled: false, required: false },
				order_comments: { enabled: true, required: false, priority: 10, placeholder: "Delivery note, landmark, preferred time" },
			},
			custom: [
				{ key: "checkflow_custom_alt_phone", label: "Alternative phone", type: "text", group: "billing", priority: 90, validation: "phone", placeholder: "Optional backup number", condition: { enabled: true, action: "show", source: "payment_method", operator: "equals", value: "cod", field: "" } },
			],
		},
		minimal: {
			label: "Minimal Checkout",
			fields: {
				billing_email: { enabled: true, required: true, priority: 10, width: "full", validation: "email", placeholder: "Email address" },
				billing_first_name: { enabled: true, required: true, priority: 20, width: "first" },
				billing_last_name: { enabled: true, required: true, priority: 30, width: "last" },
				billing_phone: { enabled: true, required: false, priority: 40, width: "full", validation: "phone" },
				billing_country: { enabled: true, required: true, priority: 50, width: "full" },
				billing_address_1: { enabled: true, required: true, priority: 60, width: "full" },
				billing_city: { enabled: true, required: true, priority: 70, width: "first" },
				billing_state: { enabled: true, required: true, priority: 80, width: "last" },
				billing_company: { enabled: false, required: false },
				billing_address_2: { enabled: false, required: false },
				billing_postcode: { enabled: false, required: false },
				shipping_first_name: { enabled: true, required: true, priority: 10, width: "first" },
				shipping_last_name: { enabled: true, required: true, priority: 20, width: "last" },
				shipping_country: { enabled: true, required: true, priority: 30, width: "full" },
				shipping_address_1: { enabled: true, required: true, priority: 40, width: "full" },
				shipping_city: { enabled: true, required: true, priority: 50, width: "first" },
				shipping_state: { enabled: true, required: true, priority: 60, width: "last" },
				shipping_company: { enabled: false, required: false },
				shipping_address_2: { enabled: false, required: false },
				shipping_postcode: { enabled: false, required: false },
				order_comments: { enabled: false, required: false },
			},
			custom: [],
		},
		digital: {
			label: "Digital Product",
			fields: {
				billing_email: { enabled: true, required: true, priority: 10, width: "full", validation: "email", placeholder: "Where should we send access?" },
				billing_first_name: { enabled: true, required: false, priority: 20, width: "first" },
				billing_last_name: { enabled: true, required: false, priority: 30, width: "last" },
				billing_phone: { enabled: false, required: false },
				billing_company: { enabled: false, required: false },
				billing_country: { enabled: false, required: false },
				billing_address_1: { enabled: false, required: false },
				billing_address_2: { enabled: false, required: false },
				billing_city: { enabled: false, required: false },
				billing_state: { enabled: false, required: false },
				billing_postcode: { enabled: false, required: false },
				shipping_first_name: { enabled: false, required: false },
				shipping_last_name: { enabled: false, required: false },
				shipping_company: { enabled: false, required: false },
				shipping_country: { enabled: false, required: false },
				shipping_address_1: { enabled: false, required: false },
				shipping_address_2: { enabled: false, required: false },
				shipping_city: { enabled: false, required: false },
				shipping_state: { enabled: false, required: false },
				shipping_postcode: { enabled: false, required: false },
				order_comments: { enabled: true, required: false, priority: 10, placeholder: "Anything we should know?" },
			},
			custom: [
				{ key: "checkflow_custom_account_email", label: "Account email", type: "text", group: "billing", priority: 40, validation: "email", placeholder: "Optional different login email" },
			],
		},
		b2b: {
			label: "Business / B2B",
			fields: {
				billing_company: { enabled: true, required: true, priority: 10, width: "full", placeholder: "Company name" },
				billing_first_name: { enabled: true, required: true, priority: 20, width: "first" },
				billing_last_name: { enabled: true, required: true, priority: 30, width: "last" },
				billing_email: { enabled: true, required: true, priority: 40, width: "first", validation: "email" },
				billing_phone: { enabled: true, required: true, priority: 50, width: "last", validation: "phone" },
				billing_country: { enabled: true, required: true, priority: 60, width: "full" },
				billing_address_1: { enabled: true, required: true, priority: 70, width: "full" },
				billing_city: { enabled: true, required: true, priority: 80, width: "first" },
				billing_state: { enabled: true, required: true, priority: 90, width: "last" },
				billing_postcode: { enabled: true, required: false, priority: 100, width: "first" },
				billing_address_2: { enabled: true, required: false, priority: 110, width: "last" },
				shipping_company: { enabled: true, required: true, priority: 10, width: "full", placeholder: "Company name" },
				shipping_first_name: { enabled: true, required: true, priority: 20, width: "first" },
				shipping_last_name: { enabled: true, required: true, priority: 30, width: "last" },
				shipping_country: { enabled: true, required: true, priority: 40, width: "full" },
				shipping_address_1: { enabled: true, required: true, priority: 50, width: "full" },
				shipping_city: { enabled: true, required: true, priority: 60, width: "first" },
				shipping_state: { enabled: true, required: true, priority: 70, width: "last" },
				shipping_postcode: { enabled: true, required: false, priority: 80, width: "first" },
				shipping_address_2: { enabled: true, required: false, priority: 90, width: "last" },
				order_comments: { enabled: true, required: false, priority: 10, placeholder: "Procurement notes, delivery window, invoice instructions" },
			},
			custom: [
				{ key: "checkflow_custom_tax_id", label: "Tax / BIN number", type: "text", group: "billing", priority: 120, placeholder: "Optional tax identifier", min_length: 4 },
				{ key: "checkflow_custom_po_number", label: "Purchase order number", type: "text", group: "order", priority: 20, placeholder: "PO number if required" },
			],
		},
	};
	var activeFieldPreset = "";

	function allPresetCustomKeys() {
		var keys = [];
		Object.keys(fieldPresets).forEach(function (presetKey) {
			(fieldPresets[presetKey].custom || []).forEach(function (field) {
				if (field.key && keys.indexOf(field.key) === -1) {
					keys.push(field.key);
				}
			});
		});
		return keys;
	}

	function activePresetCustomKeys(preset) {
		return (preset.custom || []).map(function (field) {
			return field.key;
		});
	}

	function fieldRow(key) {
		return document.querySelector('.cf-field-row[data-field-key="' + key + '"]');
	}

	function setIfExists(row, selector, value) {
		var el = row ? row.querySelector(selector) : null;
		if (!el || value === undefined) return;
		if (el.type === "checkbox") {
			if (!el.disabled) el.checked = !!value;
			return;
		}
		el.value = value == null ? "" : String(value);
	}

	function clearAdvancedRow(row) {
		setIfExists(row, ".cf-field-placeholder", "");
		setIfExists(row, ".cf-field-help", "");
		setIfExists(row, ".cf-field-width", "default");
		setIfExists(row, ".cf-field-default-value", "");
		setIfExists(row, ".cf-field-validation", "none");
		setIfExists(row, ".cf-field-min", "");
		setIfExists(row, ".cf-field-max", "");
		setIfExists(row, ".cf-field-min-length", "");
		setIfExists(row, ".cf-field-max-length", "");
		setIfExists(row, ".cf-field-required-message", "");
		setIfExists(row, ".cf-field-validation-message", "");
		setIfExists(row, ".cf-field-condition-enabled", false);
		setIfExists(row, ".cf-field-condition-action", "show");
		setIfExists(row, ".cf-field-condition-source", "payment_method");
		setIfExists(row, ".cf-field-condition-operator", "equals");
		setIfExists(row, ".cf-field-condition-value", "");
		setIfExists(row, ".cf-field-condition-field", "");
	}

	function applyRowConfig(row, config) {
		if (!row || !config) return;
		clearAdvancedRow(row);
		setIfExists(row, ".cf-field-enabled", config.enabled);
		setIfExists(row, ".cf-field-required", config.required);
		setIfExists(row, ".cf-field-priority", config.priority);
		setIfExists(row, ".cf-field-label", config.label);
		setIfExists(row, ".cf-field-placeholder", config.placeholder);
		setIfExists(row, ".cf-field-help", config.help);
		setIfExists(row, ".cf-field-width", config.width);
		setIfExists(row, ".cf-field-default-value", config.default_value);
		setIfExists(row, ".cf-field-validation", config.validation);
		setIfExists(row, ".cf-field-min", config.min);
		setIfExists(row, ".cf-field-max", config.max);
		setIfExists(row, ".cf-field-min-length", config.min_length);
		setIfExists(row, ".cf-field-max-length", config.max_length);
		setIfExists(row, ".cf-field-required-message", config.required_message);
		setIfExists(row, ".cf-field-validation-message", config.validation_message);
		if (config.condition) {
			setIfExists(row, ".cf-field-condition-enabled", !!config.condition.enabled);
			setIfExists(row, ".cf-field-condition-action", config.condition.action || "show");
			setIfExists(row, ".cf-field-condition-source", config.condition.source || "payment_method");
			setIfExists(row, ".cf-field-condition-operator", config.condition.operator || "equals");
			setIfExists(row, ".cf-field-condition-value", config.condition.value || "");
			setIfExists(row, ".cf-field-condition-field", config.condition.field || "");
		}
		var labelInput = row.querySelector(".cf-field-label");
		var title = row.querySelector(".cf-field-title strong");
		if (title && labelInput) title.textContent = labelInput.value;
		updateFieldPreview(row);
		row.classList.add("is-reordered");
		window.setTimeout(function () {
			row.classList.remove("is-reordered");
		}, 520);
	}

	function ensurePresetCustomField(config) {
		var row = fieldRow(config.key);
		if (row) {
			row.setAttribute("data-preset-field", "1");
			return row;
		}
		var list = document.querySelector('[data-field-list="' + config.group + '"]');
		if (!list) return null;
		row = createCustomFieldRow({
			key: config.key,
			label: config.label,
			type: config.type || "text",
			group: config.group || "billing",
			options: config.options || [],
		});
		row.setAttribute("data-preset-field", "1");
		list.appendChild(row);
		if ($.fn && $.fn.sortable) {
			$(list).sortable("refresh");
		}
		return row;
	}

	function disableInactivePresetCustomFields(activeKeys) {
		allPresetCustomKeys().forEach(function (key) {
			if (activeKeys.indexOf(key) !== -1) return;
			var row = fieldRow(key);
			if (!row) return;
			setIfExists(row, ".cf-field-enabled", false);
			setIfExists(row, ".cf-field-required", false);
			row.classList.add("is-preset-hidden");
			updateFieldPreview(row);
		});
		activeKeys.forEach(function (key) {
			var row = fieldRow(key);
			if (row) row.classList.remove("is-preset-hidden");
		});
	}

	function setPresetUi(key, summary) {
		activeFieldPreset = key;
		document.querySelectorAll("[data-field-preset]").forEach(function (button) {
			var isActive = button.getAttribute("data-field-preset") === key;
			var card = button.closest ? button.closest(".cf-preset-card") : null;
			if (card) card.classList.toggle("is-active", isActive);
			button.textContent = isActive ? "Selected" : "Apply";
		});
		var status = document.querySelector("[data-field-preset-status]");
		if (status) {
			status.classList.add("is-active");
			status.textContent = summary || "Selected: " + (fieldPresets[key] ? fieldPresets[key].label : key) + " - save to publish";
		}
		try {
			window.localStorage.setItem("checkflow_field_preset", key);
		} catch (e) {}
	}

	function presetSummary(preset) {
		var enabled = 0;
		var hidden = 0;
		Object.keys(preset.fields || {}).forEach(function (fieldKey) {
			if (preset.fields[fieldKey].enabled === false) hidden++;
			if (preset.fields[fieldKey].enabled === true) enabled++;
		});
		var customCount = (preset.custom || []).length;
		return "Selected: " + preset.label + " - " + enabled + " shown, " + hidden + " hidden, " + customCount + " smart field" + (customCount === 1 ? "" : "s") + " - save to publish";
	}

	function sortFieldLists() {
		document.querySelectorAll(".cf-field-list").forEach(function (list) {
			Array.prototype.slice.call(list.querySelectorAll(".cf-field-row"))
				.sort(function (a, b) {
					var pa = parseInt((a.querySelector(".cf-field-priority") || {}).value || "999", 10);
					var pb = parseInt((b.querySelector(".cf-field-priority") || {}).value || "999", 10);
					return pa - pb;
				})
				.forEach(function (row) {
					list.appendChild(row);
				});
		});
	}

	function applyFieldPreset(key, options) {
		options = options || {};
		var preset = fieldPresets[key];
		if (!preset) return;
		if (!options.skipConfirm && !window.confirm("Apply " + preset.label + " preset? Review the changes, then Save fields to publish.")) {
			return;
		}
		document.querySelectorAll(".cf-field-row").forEach(function (row) {
			if (row.getAttribute("data-field-custom") === "1") return;
			clearAdvancedRow(row);
			var enabled = row.getAttribute("data-protected") === "1";
			setIfExists(row, ".cf-field-enabled", enabled);
			setIfExists(row, ".cf-field-required", false);
		});
		Object.keys(preset.fields || {}).forEach(function (fieldKey) {
			applyRowConfig(fieldRow(fieldKey), preset.fields[fieldKey]);
		});
		disableInactivePresetCustomFields(activePresetCustomKeys(preset));
		(preset.custom || []).forEach(function (field) {
			applyRowConfig(ensurePresetCustomField(field), Object.assign({ enabled: true, required: false, width: "default" }, field));
		});
		sortFieldLists();
		setPresetUi(key, presetSummary(preset));
		setFieldEditorDirty(true);
		updateFieldSearch();
		showToast(preset.label + (options.source === "auto" ? " fields auto-applied. Review, then Save fields." : " template selected. Review changes, then Save fields."));
	}

	function applyTemplateFieldPairing(button) {
		var box = button && button.closest ? button.closest("[data-template-pairing]") : null;
		applyTemplatePairingPreset(box ? box.getAttribute("data-pairing-preset") : "", false);
	}

	function restorePresetUi() {
		var key = "";
		try {
			key = window.localStorage.getItem("checkflow_field_preset") || "";
		} catch (e) {}
		if (!key || !fieldPresets[key]) return;
		document.querySelectorAll("[data-field-preset]").forEach(function (button) {
			var isActive = button.getAttribute("data-field-preset") === key;
			var card = button.closest ? button.closest(".cf-preset-card") : null;
			if (card) card.classList.toggle("is-active", isActive);
			button.textContent = isActive ? "Last selected" : "Apply";
		});
		var status = document.querySelector("[data-field-preset-status]");
		if (status) {
			status.classList.add("is-active");
			status.textContent = "Last selected: " + fieldPresets[key].label + " - apply again or save after edits";
		}
	}

	function ensureImportedCustomField(field) {
		var row = fieldRow(field.key);
		if (row) return row;
		var list = document.querySelector('[data-field-list="' + field.group + '"]');
		if (!list) return null;
		row = createCustomFieldRow({
			key: field.key,
			label: field.label,
			type: field.type || "text",
			group: field.group || "billing",
			options: field.options || [],
		});
		list.appendChild(row);
		if ($.fn && $.fn.sortable) {
			$(list).sortable("refresh");
		}
		return row;
	}

	function applyImportedFieldSetup(fields) {
		var normalized = normalizeImportedFields(fields);
		if (!normalized.length) {
			showToast("No valid fields found in import file", "error");
			return;
		}
		var customCount = normalized.filter(function (field) {
			return !!field.custom;
		}).length;
		if (!window.confirm("Import " + normalized.length + " fields and replace the current editor setup? Review, then Save fields to publish.")) {
			return;
		}
		document.querySelectorAll('.cf-field-row[data-field-custom="1"]').forEach(function (row) {
			row.remove();
		});
		document.querySelectorAll('.cf-field-row[data-field-custom="0"]').forEach(function (row) {
			resetFieldRowToDefaults(row);
		});
		normalized.forEach(function (field) {
			var row = field.custom ? ensureImportedCustomField(field) : fieldRow(field.key);
			if (!row) return;
			if (field.custom) {
				row.setAttribute("data-field-options", JSON.stringify(field.options || []));
			}
			applyRowConfig(row, field);
		});
		sortFieldLists();
		setFieldEditorDirty(true);
		updateFieldSearch();
		try {
			window.localStorage.removeItem("checkflow_field_preset");
		} catch (e) {}
		document.querySelectorAll("[data-field-preset]").forEach(function (button) {
			var card = button.closest ? button.closest(".cf-preset-card") : null;
			if (card) card.classList.remove("is-active");
			button.textContent = "Apply";
		});
		var status = document.querySelector("[data-field-preset-status]");
		if (status) {
			status.classList.add("is-active");
			status.textContent = "Imported setup: " + normalized.length + " fields, " + customCount + " custom - save to publish";
		}
		showToast("Field setup imported. Review changes, then Save fields.");
	}

	function importFieldSetupFile(file) {
		if (!file) return;
		if (file.size > 1024 * 1024) {
			showToast("Import file is too large", "error");
			return;
		}
		var reader = new FileReader();
		reader.onload = function () {
			try {
				applyImportedFieldSetup(JSON.parse(String(reader.result || "{}")));
			} catch (e) {
				showToast("Invalid JSON import file", "error");
			}
		};
		reader.onerror = function () {
			showToast("Could not read import file", "error");
		};
		reader.readAsText(file);
	}

	function addCustomField() {
		var labelInput = document.querySelector(".cf-new-field-label");
		var typeInput = document.querySelector(".cf-new-field-type");
		var groupInput = document.querySelector(".cf-new-field-group");
		var optionsInput = document.querySelector(".cf-new-field-options");
		var label = labelInput ? labelInput.value.trim() : "";
		var type = typeInput ? typeInput.value : "text";
		var group = groupInput ? groupInput.value : "billing";
		if (!label) {
			showToast("Field label is required", "error");
			return;
		}
		var options = [];
		if (optionsInput && optionsInput.value.trim()) {
			options = optionsInput.value
				.split(",")
				.map(function (item) {
					return item.trim();
				})
				.filter(Boolean);
		}
		if (type === "select" && !options.length) {
			showToast("Select fields need options", "error");
			return;
		}
		var list = document.querySelector('[data-field-list="' + group + '"]');
		if (!list) return;
		var row = createCustomFieldRow({
			key: slugifyFieldKey(label),
			label: label,
			type: type,
			group: group,
			options: options,
		});
		list.appendChild(row);
		reindexCheckoutFields(list);
		if ($.fn && $.fn.sortable) {
			$(list).sortable("refresh");
		}
		setFieldGroup(group);
		setFieldEditorDirty(true);
		updateFieldSearch();
		if (labelInput) labelInput.value = "";
		if (optionsInput) optionsInput.value = "";
		window.setTimeout(function () {
			row.classList.remove("is-reordered");
		}, 520);
	}

	function saveCheckoutFields(btnEl) {
		var ajaxUrl = getAdminAjaxUrl();
		if (!ajaxUrl) return;
		var $btn = $(btnEl);
		$btn.prop("disabled", true);
		$.ajax({
			url: ajaxUrl,
			method: "POST",
			dataType: "json",
			data: {
				action: "checkflow_save_checkout_fields",
				nonce: checkflowAdmin.nonce,
				fields: JSON.stringify(collectCheckoutFields()),
			},
		})
			.done(function (res) {
				if (res && res.success) {
					showToast("Checkout fields saved");
					setFieldEditorDirty(false);
					return;
				}
				showToast("Could not save checkout fields", "error");
			})
			.fail(function () {
				showToast("Could not save checkout fields", "error");
			})
			.always(function () {
				$btn.prop("disabled", false);
			});
	}

	function resetCheckoutFields(btnEl) {
		var ajaxUrl = getAdminAjaxUrl();
		if (!ajaxUrl) return;
		var $btn = $(btnEl);
		$btn.prop("disabled", true);
		$.ajax({
			url: ajaxUrl,
			method: "POST",
			dataType: "json",
			data: {
				action: "checkflow_reset_checkout_fields",
				nonce: checkflowAdmin.nonce,
			},
		})
			.done(function (res) {
				if (res && res.success) {
					showToast("Checkout fields reset");
					try {
						window.localStorage.removeItem("checkflow_field_preset");
					} catch (e) {}
					window.setTimeout(function () {
						window.location.reload();
					}, 500);
					return;
				}
				showToast("Could not reset checkout fields", "error");
			})
			.fail(function () {
				showToast("Could not reset checkout fields", "error");
			})
			.always(function () {
				$btn.prop("disabled", false);
			});
	}

	$(function () {
		document.documentElement.classList.add("checkflow-admin-html");
		renderMiniChart();

		$(document).on("click", ".tab", function () {
			$(this).siblings(".tab").removeClass("on");
			$(this).addClass("on");
			refreshStats($(this).attr("data-period") || $(this).text().trim());
		});

		$(document).on("click", ".tgl", function () {
			toggleSetting(this);
		});

		$(document).on("keydown", ".tgl", function (e) {
			if (e.key === "Enter" || e.key === " ") {
				e.preventDefault();
				toggleSetting(this);
			}
		});

		$(document).on("click", ".ni[data-screen]", function () {
			var id = $(this).attr("data-screen");
			if (id) setPane(id);
		});

		$(document).on("click", "[data-save-checkout-template]", function () {
			saveCheckoutTemplate(this.getAttribute("data-save-checkout-template"), this);
		});

		$(document).on("mouseenter focusin click", ".cf-template-card", function () {
			updateTemplateCompare(this);
		});

		$(document).on("click", "[data-apply-template-field-preset]", function () {
			applyTemplateFieldPairing(this);
		});

		$(document).on("change", "[data-template-auto-pair]", function () {
			setTemplateAutoPairEnabled(this.checked);
			showToast("Template auto-pairing is " + (this.checked ? "ON" : "OFF"));
		});

		var activeTemplateCard = document.querySelector(".cf-template-card.is-active");
		if (activeTemplateCard) {
			var activeTemplateName = activeTemplateCard.querySelector(".cf-template-name");
			showTemplatePairing(activeTemplateCard, activeTemplateName ? activeTemplateName.textContent : activeTemplateCard.getAttribute("data-checkout-template"));
			updateTemplateCompare(activeTemplateCard);
		}
		setTemplateAutoPairEnabled(templateAutoPairEnabled());

		$(document).on("change", ".cf-locale-switch", function () {
			persistLocale($(this).val());
		});

		$(document).on("click", ".cf-stub-dash", function (e) {
			e.preventDefault();
			setPane("dashboard");
		});

		$(document).on("change", ".cf-str-locale-picker", function () {
			var base = window.checkflowAdmin && checkflowAdmin.adminPageBase;
			if (!base) {
				return;
			}
			var j = base.indexOf("?") >= 0 ? "&" : "?";
			window.location.href =
				base +
				j +
				"cf_edit_lang=" +
				encodeURIComponent($(this).val()) +
				"#settings";
		});

		$(document).on("click", "#cf-save-overrides", function () {
			var lng = $("#cf-edit-locale").val() || "";
			saveOverrides(lng, this);
		});

		$(document).on("click", ".cf-save-fields", function () {
			saveCheckoutFields(this);
		});

		$(document).on("click", ".cf-reset-fields", function () {
			resetCheckoutFields(this);
		});

		$(document).on("input change", ".cf-field-row input, .cf-field-row select", function () {
			var row = this.closest ? this.closest(".cf-field-row") : null;
			if (row && this.classList.contains("cf-field-label")) {
				var title = row.querySelector(".cf-field-title strong");
				if (title) title.textContent = this.value || row.getAttribute("data-field-key") || "";
				updateFieldSearch();
			}
			if (row && (this.classList.contains("cf-field-placeholder") || this.classList.contains("cf-field-help") || this.classList.contains("cf-field-default-value"))) {
				updateFieldPreview(row);
			}
			setFieldEditorDirty(true);
		});

		$(document).on("input", ".cf-field-search", function () {
			updateFieldSearch();
		});

		$(document).on("click", ".cf-reset-active-fields", function () {
			resetActiveFieldTab();
		});

		$(document).on("click", "[data-field-group-tab]", function () {
			setFieldGroup(this.getAttribute("data-field-group-tab"));
		});

		$(document).on("click", "[data-field-move]", function () {
			moveCheckoutField(this);
		});

		$(document).on("click", "[data-field-settings]", function () {
			toggleFieldSettings(this);
		});

		$(document).on("click", "[data-field-delete]", function () {
			deleteCustomField(this);
		});

		$(document).on("click", ".cf-add-custom-field", function () {
			addCustomField();
		});

		$(document).on("click", "[data-field-preset]", function () {
			applyFieldPreset(this.getAttribute("data-field-preset"));
		});

		$(document).on("click", ".cf-export-fields", function () {
			exportFieldSetup();
		});

		$(document).on("click", ".cf-import-fields", function () {
			var input = document.querySelector(".cf-import-fields-file");
			if (input) input.click();
		});

		$(document).on("change", ".cf-import-fields-file", function () {
			importFieldSetupFile(this.files && this.files[0]);
			this.value = "";
		});

		$(document).on("input", ".cf-orders-search-input", function () {
			applyOrderFilters();
		});

		$(document).on("click", "[data-order-filter]", function () {
			var group = this.getAttribute("data-order-filter") || "";
			var value = this.getAttribute("data-filter-value") || "all";
			if (!group) {
				return;
			}
			orderFilters[group] = value;
			var parent = this.closest ? this.closest(".cf-orders-filter-group") : null;
			if (parent) {
				parent.querySelectorAll('[data-order-filter="' + group + '"]').forEach(function (button) {
					button.classList.toggle("is-active", button === this);
				}, this);
			}
			applyOrderFilters();
		});

		$(document).on("change", "[data-order-select-all]", function () {
			var checked = this.checked;
			getOrderRows(false).forEach(function (row) {
				var checkbox = row.querySelector("[data-order-select]");
				if (checkbox) {
					checkbox.checked = checked;
				}
			});
			updateOrderSelectionState();
		});

		$(document).on("change", "[data-order-select]", function () {
			updateOrderSelectionState();
		});

		$(document).on("click", "[data-order-row]", function (event) {
			if (event.target.closest("a, button, input, label")) {
				return;
			}
			openOrderDrawer(this);
		});

		$(document).on("click", "[data-order-drawer-close]", function () {
			closeOrderDrawer();
		});

		$(document).on("keydown", function (event) {
			if (event.key === "Escape") {
				closeOrderDrawer();
			}
		});

		$(document).on("click", "[data-order-bulk-action]", function () {
			handleOrderBulkAction(this.getAttribute("data-order-bulk-action"));
		});

		$(document).on("click", "[data-order-clear-selection]", function () {
			getSelectedOrderRows().forEach(function (row) {
				var checkbox = row.querySelector("[data-order-select]");
				if (checkbox) {
					checkbox.checked = false;
				}
			});
			updateOrderSelectionState();
		});

		$(document).on("click", "[data-copy-order-phone]", function () {
			copyOrderValue("phone");
		});

		$(document).on("click", "[data-copy-order-address]", function () {
			copyOrderValue("address");
		});

		$(document).on("click", "[data-order-single-action]", function () {
			if (this.getAttribute("data-order-single-action") === "courier") {
				prepareCourierDraft(this);
			}
		});

		$(document).on("click", "[data-review-pathao-booking]", function () {
			reviewPathaoBooking(this);
		});

		$(document).on("click", "[data-book-pathao-order]", function () {
			bookPathaoOrder(this);
		});

		$(document).on("click", "[data-admin-theme-toggle]", function () {
			var current = window.checkflowAdmin && checkflowAdmin.adminTheme === "light" ? "light" : "dark";
			saveAdminTheme(current === "light" ? "dark" : "light", this);
		});

		$(document).on("click", "[data-save-courier-settings]", function () {
			saveCourierSettings(this);
		});

		$(document).on("click", "[data-save-pixel-settings]", function () {
			savePixelSettings(this);
		});

		$(document).on("change", "[data-courier-default]", function () {
			document.querySelectorAll("[data-courier-provider-card]").forEach(function (card) {
				card.classList.toggle("is-default", card.getAttribute("data-courier-provider-card") === this.value);
				var label = card.querySelector(".cf-courier-provider-head span");
				if (label) {
					label.textContent = card.classList.contains("is-default") ? "Default provider" : "Courier provider";
				}
			}, this);
		});

		$(document).on("click", "[data-order-status-draft]", function () {
			prepareStatusDraft(this);
		});

		$(document).on("click", "[data-order-status-cancel]", function () {
			cancelStatusDraft();
		});

		$(document).on("click", "[data-order-status-confirm-btn]", function () {
			confirmStatusDraft();
		});

		$(document).on("click", "[data-order-note-draft]", function () {
			prepareOrderNoteDraft();
		});

		$(document).on("click", "[data-order-note-clear]", function () {
			clearOrderNoteDraft();
		});

		bindFieldDragEvents();
		setupPixelInsights();
		restorePresetUi();
		applyAdminTheme((window.checkflowAdmin && checkflowAdmin.adminTheme) || "dark");
		applyOrderFilters();

		window.addEventListener("beforeunload", function (event) {
			if (!fieldEditorDirty) return;
			event.preventDefault();
			event.returnValue = "";
		});

		var rawHash = window.location.hash ? window.location.hash.replace(/^#\s*/, "").replace(/^cf-/, "") : "";
		if (rawHash && document.querySelector('[data-pane="' + rawHash + '"]')) {
			setPane(rawHash);
		}
	});
})(jQuery);
