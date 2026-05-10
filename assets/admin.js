(function ($) {
	"use strict";

	if (typeof window.gs_posts_grid_init !== "function") {
		window.gs_posts_grid_init = function () {};
	}

	var fieldEditorDirty = false;
	var draggedFieldRow = null;
	var pointerFieldDrag = null;

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
			});
		});
		return rows;
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
			var title = row.querySelector(".cf-field-title strong");
			if (label) label.value = row.getAttribute("data-default-label") || label.value;
			if (priority) priority.value = row.getAttribute("data-default-priority") || priority.value;
			if (enabled && !enabled.disabled) enabled.checked = row.getAttribute("data-default-enabled") === "1";
			if (required) required.checked = row.getAttribute("data-default-required") === "1";
			if (placeholder) placeholder.value = row.getAttribute("data-default-placeholder") || "";
			if (help) help.value = row.getAttribute("data-default-help") || "";
			if (width) width.value = row.getAttribute("data-default-width") || "default";
			if (defaultValue) defaultValue.value = row.getAttribute("data-default-value") || "";
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
			'<div class="cf-field-preview" aria-hidden="true"><span>Preview</span><strong></strong><em>Customer input</em><small>No help text</small></div>' +
			"</div>";
		row.querySelector(".cf-field-title strong").textContent = config.label;
		row.querySelector(".cf-field-title span").textContent = config.key + " · " + config.type;
		row.querySelector(".cf-field-label").value = config.label;
		updateFieldPreview(row);
		return row;
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

		bindFieldDragEvents();

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
