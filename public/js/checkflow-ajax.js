(function () {
	"use strict";

	if (!window.checkflowCheckout) {
		return;
	}

	var timer = null;
	var qtyAbortController = null;
	var shippingAbortController = null;
	var couponReqInFlight = false;
	var removeReqInFlight = false;
	var noticeTimer = null;
	var validationTimers = {};
	var lastValidatedValues = {};

	function getCouponForm() {
		return document.querySelector("form.checkout_coupon");
	}

	function ensureNoticeWrap() {
		var form = getCouponForm();
		if (!form) return null;
		var wrap = form.querySelector(".checkflow-coupon-notice-wrap");
		if (wrap) return wrap;
		wrap = document.createElement("div");
		wrap.className = "checkflow-coupon-notice-wrap";
		form.insertBefore(wrap, form.firstChild);
		return wrap;
	}

	function clearNotice() {
		if (noticeTimer) {
			clearTimeout(noticeTimer);
			noticeTimer = null;
		}
		var wrap = ensureNoticeWrap();
		if (wrap) wrap.innerHTML = "";
	}

	function showNotice(type, message) {
		var wrap = ensureNoticeWrap();
		if (!wrap) return;
		var msg = document.createElement("div");
		msg.className = "checkflow-coupon-notice " + (type === "success" ? "is-success" : "is-error");
		msg.textContent = message || "";
		wrap.innerHTML = "";
		wrap.appendChild(msg);
		if (noticeTimer) clearTimeout(noticeTimer);
		noticeTimer = setTimeout(function () {
			clearNotice();
		}, 4000);
	}

	function setCouponInputState(form, hasError) {
		if (!form) return;
		var input = form.querySelector('input[name="coupon_code"]');
		if (!input) return;
		input.classList.toggle("is-error", !!hasError);
		if (hasError) {
			input.setAttribute("aria-invalid", "true");
		} else {
			input.removeAttribute("aria-invalid");
		}
	}

	function couponKeyClass(code) {
		return "coupon-" + String(code || "").toLowerCase().replace(/[^a-z0-9_-]/g, "-");
	}

	function getOrderReviewTable() {
		return (
			document.querySelector(".woocommerce-checkout-review-order-table") ||
			document.querySelector("#order_review .shop_table") ||
			null
		);
	}

	function findCouponDiscountRows(code) {
		var table = getOrderReviewTable();
		var key = couponKeyClass(code);
		var out = [];
		if (!table) return out;
		var opt = table.querySelector(".checkflow-opt-coupon-row." + key);
		if (opt) out.push(opt);
		var wc = table.querySelector("tr.cart-discount." + key);
		if (wc) out.push(wc);
		return out;
	}

	function rowsForCouponRemove(link, code) {
		var out = [];
		var tr = link.closest("tr");
		if (tr) out.push(tr);
		findCouponDiscountRows(code).forEach(function (r) {
			if (out.indexOf(r) === -1) out.push(r);
		});
		return out;
	}

	function rollbackCouponRemoveAnimation(rows) {
		if (!rows || !rows.length) return;
		rows.forEach(function (row) {
			if (row.parentNode) {
				row.classList.remove("checkflow-coupon-removing");
			}
		});
	}

	function removeCouponDomRows(rows) {
		if (!rows || !rows.length) return;
		rows.forEach(function (row) {
			if (row.parentNode) row.remove();
		});
	}

	function optimisticCouponRow(action, code, amountHtml) {
		var table = getOrderReviewTable();
		if (!table) return;
		var rowClass = "checkflow-opt-coupon-row";
		var keyClass = couponKeyClass(code);
		var existing = table.querySelector("." + rowClass + "." + keyClass);
		if (action === "remove") {
			if (existing) existing.remove();
			var wcRow = table.querySelector(".cart-discount." + keyClass);
			if (wcRow) wcRow.remove();
			return;
		}
		if (existing) {
			if (amountHtml) {
				var amountCell = existing.querySelector("td");
				if (amountCell) amountCell.innerHTML = amountHtml;
			}
			return;
		}
		var tfoot = table.querySelector("tfoot") || table;
		var orderTotal = tfoot.querySelector(".order-total");
		var tr = document.createElement("tr");
		tr.className = rowClass + " " + keyClass;
		var th = document.createElement("th");
		th.textContent = "Coupon: " + code;
		var td = document.createElement("td");
		td.innerHTML = amountHtml || "Applying...";
		tr.appendChild(th);
		tr.appendChild(td);
		if (orderTotal && orderTotal.parentNode) {
			orderTotal.parentNode.insertBefore(tr, orderTotal);
		} else {
			tfoot.appendChild(tr);
		}
	}

	function refreshCheckoutSoon() {
		setTimeout(function () {
			if (window.jQuery) {
				window.jQuery(document.body).trigger("update_checkout");
			}
		}, 120);
	}

	function setCouponButtonState(form, busy) {
		if (!form) return;
		var btn = form.querySelector('button[type="submit"], button[name="apply_coupon"]');
		if (!btn) return;
		if (busy) {
			btn.setAttribute("data-cf-old-text", btn.textContent || "");
			btn.disabled = true;
			btn.classList.add("is-loading");
			btn.textContent = checkflowCheckout.strings && checkflowCheckout.strings.updating ? checkflowCheckout.strings.updating : "Updating...";
			return;
		}
		var old = btn.getAttribute("data-cf-old-text");
		btn.disabled = false;
		btn.classList.remove("is-loading");
		if (old) btn.textContent = old;
		btn.removeAttribute("data-cf-old-text");
	}

	function setRemoveLinkState(link, busy) {
		if (!link) return;
		if (busy) {
			link.classList.add("is-loading");
			link.setAttribute("aria-disabled", "true");
			link.style.pointerEvents = "none";
			return;
		}
		link.classList.remove("is-loading");
		link.removeAttribute("aria-disabled");
		link.style.pointerEvents = "";
	}

	function postAction(action, data, opts) {
		var o = opts || {};
		var body = new URLSearchParams();
		body.append("action", action);
		body.append("nonce", checkflowCheckout.nonce);
		if (window.FormData && data instanceof FormData) {
			data.forEach(function (value, key) {
				body.append(key, value);
			});
		} else {
			Object.keys(data || {}).forEach(function (k) {
				body.append(k, data[k]);
			});
		}
		return fetch(getAjaxUrl(), {
			method: "POST",
			headers: {
				"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
			},
			body: body.toString(),
			credentials: "same-origin",
			signal: o.signal,
		}).then(function (r) {
			return r.text().then(function (text) {
				var payload = null;
				try {
					payload = text ? JSON.parse(text) : null;
				} catch (err) {
					payload = {
						success: false,
						data: { message: "Unexpected server response. Please retry." },
					};
				}
				if (!r.ok && payload && payload.success !== false) {
					payload.success = false;
				}
				return payload;
			});
		});
	}

	function normalizeMessage(res, fallback) {
		if (!res) return fallback;
		if (res.message) return res.message;
		if (res.data && res.data.message) return res.data.message;
		return fallback;
	}

	function normalizeFieldMessage(res, fieldName, fallback) {
		if (res && res.errors && res.errors[fieldName] && res.errors[fieldName][0]) {
			return res.errors[fieldName][0];
		}
		return normalizeMessage(res, fallback);
	}

	function sendQtyUpdate() {
		var inputs = document.querySelectorAll(".checkflow-qty-input");
		if (!inputs.length) {
			return;
		}

		var quantities = {};
		inputs.forEach(function (input) {
			var key = input.getAttribute("data-item-key");
			var qty = parseInt(input.value || "1", 10);
			if (!key) return;
			quantities[key] = Number.isNaN(qty) || qty < 1 ? 1 : qty;
		});

		Object.keys(quantities).forEach(function (k) {
			quantities["quantities[" + k + "]"] = String(quantities[k]);
		});

		if (qtyAbortController) {
			qtyAbortController.abort();
		}
		qtyAbortController = new AbortController();
		postAction("checkflow_update_order_review", quantities, { signal: qtyAbortController.signal })
			.then(function (r) {
				return r;
			})
			.then(function (res) {
				if (!res || !res.success) return;
				if (window.jQuery) {
					window.jQuery(document.body).trigger("update_checkout");
				}
			})
			.catch(function () {})
			.finally(function () {
				qtyAbortController = null;
			});
	}

	function applyCoupon(code) {
		return postAction("checkflow_apply_coupon", { coupon_code: code });
	}

	function removeCoupon(code) {
		return postAction("checkflow_remove_coupon", { coupon_code: code });
	}

	function addOrderBump(productId) {
		return postAction("checkflow_add_order_bump", { product_id: productId });
	}

	function acceptUpsell(productId, slot) {
		return postAction("checkflow_accept_upsell", {
			product_id: productId,
			slot: slot || "main",
		});
	}

	function trackUpsellEvent(eventName, module) {
		if (!eventName || !module) return;
		var productId = module.getAttribute("data-checkflow-upsell-product") || "";
		var slot = module.getAttribute("data-checkflow-upsell-slot") || "main";
		var key = "checkflow_upsell_" + eventName + "_" + slot + "_" + productId;
		try {
			if (eventName === "shown" || eventName === "downsell_shown") {
				var seenAt = window.sessionStorage ? sessionStorage.getItem(key) : "";
				if (seenAt) return;
				if (window.sessionStorage) sessionStorage.setItem(key, String(Date.now()));
			}
		} catch (e) {}
		postAction("checkflow_track_upsell_event", {
			event: eventName,
			product_id: productId,
			slot: slot,
		}).catch(function () {});
	}

	function trackVisibleUpsells() {
		document.querySelectorAll(".checkflow-upsell-module").forEach(function (module) {
			if (module.hidden) return;
			var slot = module.getAttribute("data-checkflow-upsell-slot") || "main";
			trackUpsellEvent(slot === "downsell" ? "downsell_shown" : "shown", module);
		});
	}

	function getAjaxUrl() {
		if (!window.checkflowCheckout || !checkflowCheckout.ajaxUrl) {
			return "";
		}

		try {
			var url = new URL(checkflowCheckout.ajaxUrl, window.location.href);
			if (url.origin !== window.location.origin) {
				url.protocol = window.location.protocol;
				url.host = window.location.host;
			}
			return url.toString();
		} catch (e) {
			return checkflowCheckout.ajaxUrl;
		}
	}

	function checkoutForm() {
		return document.querySelector("form.checkout");
	}

	function checkoutFieldName(field) {
		if (!field || !field.name) return "";
		return field.name.replace(/\[\]$/, "");
	}

	function checkoutFieldType(field) {
		if (!field) return "text";
		if (field.type === "email") return "email";
		if (field.type === "tel") return "tel";
		if (field.name && field.name.indexOf("postcode") !== -1) return "postcode";
		return field.type || "text";
	}

	function isCheckoutFieldRequired(field) {
		if (!field) return false;
		if (field.required) return true;
		var wrapper = field.closest(".form-row, p");
		return !!(wrapper && wrapper.classList.contains("validate-required"));
	}

	function getFieldErrorNode(field) {
		var wrapper = field.closest(".form-row, p") || field.parentNode;
		if (!wrapper) return null;
		var node = wrapper.querySelector(".checkflow-field-error");
		if (node) return node;
		node = document.createElement("span");
		node.className = "checkflow-field-error";
		node.setAttribute("role", "alert");
		wrapper.appendChild(node);
		return node;
	}

	function setFieldValidationState(field, valid, message) {
		var wrapper = field.closest(".form-row, p") || field.parentNode;
		var errorNode = getFieldErrorNode(field);
		field.classList.toggle("checkflow-field-invalid", !valid);
		if (wrapper) wrapper.classList.toggle("checkflow-field-has-error", !valid);
		if (valid) {
			field.removeAttribute("aria-invalid");
			if (errorNode) errorNode.textContent = "";
			return;
		}
		field.setAttribute("aria-invalid", "true");
		if (errorNode) errorNode.textContent = message || "Please check this field.";
	}

	function shouldValidateField(field) {
		if (!field || !field.matches) return false;
		if (!checkoutForm() || !field.closest("form.checkout")) return false;
		if (!field.name || field.disabled) return false;
		if (field.type === "hidden" || field.type === "password" || field.type === "submit") return false;
		return !field.matches('input[name="coupon_code"], .checkflow-qty-input');
	}

	function validateCheckoutField(field) {
		if (!shouldValidateField(field)) return;
		var name = checkoutFieldName(field);
		var value = field.value || "";
		var cacheKey = name + "|" + value;
		if (lastValidatedValues[name] === cacheKey) {
			return;
		}
		window.clearTimeout(validationTimers[name]);
		validationTimers[name] = window.setTimeout(function () {
			lastValidatedValues[name] = cacheKey;
			postAction("checkflow_validate_field", {
				field: name,
				value: value,
				type: checkoutFieldType(field),
				required: isCheckoutFieldRequired(field) ? "1" : "0",
			})
				.then(function (res) {
					if (!res) return;
					setFieldValidationState(field, !!res.success, normalizeFieldMessage(res, name, "Please check this field."));
				})
				.catch(function () {});
		}, 700);
	}

	function addressPayload(form) {
		var fields = [
			"billing_country",
			"billing_state",
			"billing_postcode",
			"billing_city",
			"billing_address_1",
			"shipping_country",
			"shipping_state",
			"shipping_postcode",
			"shipping_city",
			"shipping_address_1",
		];
		var payload = {};
		fields.forEach(function (name) {
			var input = form.querySelector('[name="' + name + '"]');
			if (input) payload[name] = input.value || "";
		});
		return payload;
	}

	function maybeRefreshShipping(field) {
		var form = checkoutForm();
		if (!form || !field || !field.name) return;
		if (!/(country|state|postcode|city|address_1)$/.test(field.name)) return;
		if (shippingAbortController) shippingAbortController.abort();
		shippingAbortController = new AbortController();
		postAction("checkflow_get_shipping_methods", addressPayload(form), { signal: shippingAbortController.signal })
			.then(function (res) {
				if (res && res.success) refreshCheckoutSoon();
			})
			.catch(function () {})
			.finally(function () {
				shippingAbortController = null;
			});
	}

	function placeOrder(form) {
		if (!form) return Promise.resolve({ success: false });
		return postAction("checkflow_place_order", new FormData(form));
	}

	document.addEventListener("input", function (e) {
		if (!e.target.classList.contains("checkflow-qty-input")) {
			return;
		}
		clearTimeout(timer);
		timer = setTimeout(sendQtyUpdate, 250);
	});

	document.addEventListener("submit", function (e) {
		var form = e.target;
		if (!form || !form.classList || !form.classList.contains("checkout_coupon")) {
			return;
		}
		e.preventDefault();
		if (couponReqInFlight) return;
		var input = form.querySelector('input[name="coupon_code"]');
		var code = input ? (input.value || "").trim() : "";
		if (!code) {
			setCouponInputState(form, true);
			showNotice("error", "Coupon code is required.");
			return;
		}
		setCouponInputState(form, false);
		clearNotice();
		couponReqInFlight = true;
		setCouponButtonState(form, true);
		optimisticCouponRow("apply", code);
		applyCoupon(code)
			.then(function (res) {
				if (res && res.success) {
					optimisticCouponRow(
						"apply",
						code,
						(res.data && res.data.coupon_discount_html) || "Applied"
					);
					showNotice("success", normalizeMessage(res, "Coupon applied."));
					refreshCheckoutSoon();
					return;
				}
				setCouponInputState(form, true);
				optimisticCouponRow("remove", code);
				showNotice("error", normalizeMessage(res, "Failed to apply coupon."));
			})
			.catch(function () {
				setCouponInputState(form, true);
				optimisticCouponRow("remove", code);
				showNotice("error", "Network error. Please retry.");
			})
			.finally(function () {
				couponReqInFlight = false;
				setCouponButtonState(form, false);
			});
	});

	document.addEventListener("click", function (e) {
		var el = e.target;
		if (!el) return;
		var upsellAccept = el.closest ? el.closest(".checkflow-upsell-accept") : null;
		if (upsellAccept) {
			e.preventDefault();
			var upsellModule = upsellAccept.closest(".checkflow-upsell-module");
			var upsellProductId = upsellModule ? upsellModule.getAttribute("data-checkflow-upsell-product") : "";
			var upsellSlot = upsellModule ? upsellModule.getAttribute("data-checkflow-upsell-slot") || "main" : "main";
			var postPurchase = upsellModule && upsellModule.getAttribute("data-checkflow-post-purchase") === "1";
			if (!upsellModule || !upsellProductId || upsellAccept.disabled) return;
			var upsellButtons = upsellModule ? upsellModule.querySelectorAll("button") : [upsellAccept];
			upsellButtons.forEach(function (button) {
				button.disabled = true;
			});
			upsellModule.classList.add("is-loading");
			acceptUpsell(upsellProductId, upsellSlot)
				.then(function (res) {
					if (res && res.success) {
						upsellModule.hidden = true;
						upsellModule.setAttribute("data-checkflow-upsell-added", "1");
						if (postPurchase && res.data && res.data.checkout_url) {
							window.location.href = res.data.checkout_url;
							return;
						}
						showNotice("success", normalizeMessage(res, checkflowCheckout.strings && checkflowCheckout.strings.upsellAdded ? checkflowCheckout.strings.upsellAdded : "Offer added."));
						refreshCheckoutSoon();
						return;
					}
					showNotice("error", normalizeMessage(res, "Could not add offer."));
					upsellButtons.forEach(function (button) {
						button.disabled = false;
					});
					upsellModule.classList.remove("is-loading");
				})
				.catch(function () {
					showNotice("error", "Network error. Please retry.");
					upsellButtons.forEach(function (button) {
						button.disabled = false;
					});
					upsellModule.classList.remove("is-loading");
				});
			return;
		}
		var upsellSkip = el.closest ? el.closest(".checkflow-upsell-skip") : null;
		if (upsellSkip) {
			e.preventDefault();
			var skipped = upsellSkip.closest(".checkflow-upsell-module");
			if (!skipped) return;
			var slot = skipped.getAttribute("data-checkflow-upsell-slot") || "main";
			if (slot === "main") {
				trackUpsellEvent("skipped", skipped);
			}
			skipped.hidden = true;
			skipped.setAttribute("data-checkflow-upsell-manual-hidden", "1");
			if (slot === "main" && skipped.parentNode) {
				var downsell = skipped.parentNode.querySelector('.checkflow-upsell-module[data-checkflow-upsell-slot="downsell"]');
				if (downsell) {
					downsell.hidden = false;
					downsell.removeAttribute("data-checkflow-upsell-manual-hidden");
					trackUpsellEvent("downsell_shown", downsell);
				}
			}
			return;
		}
		var link = el.closest ? el.closest("a.woocommerce-remove-coupon") : null;
		if (!link) return;
		e.preventDefault();
		if (removeReqInFlight) return;
		var code = (link.getAttribute("data-coupon") || "").trim();
		if (!code) return;
		clearNotice();
		removeReqInFlight = true;
		setRemoveLinkState(link, true);
		var rows = rowsForCouponRemove(link, code);
		var animStart = Date.now();
		var animMs = 480;
		rows.forEach(function (row) {
			row.classList.add("checkflow-coupon-removing");
		});
		removeCoupon(code)
			.then(function (res) {
				var elapsed = Date.now() - animStart;
				var wait = Math.max(0, animMs - elapsed);
				window.setTimeout(function () {
					if (res && res.success) {
						removeCouponDomRows(rows);
						showNotice("success", normalizeMessage(res, "Coupon removed."));
						refreshCheckoutSoon();
						return;
					}
					rollbackCouponRemoveAnimation(rows);
					showNotice("error", normalizeMessage(res, "Failed to remove coupon."));
					refreshCheckoutSoon();
				}, wait);
			})
			.catch(function () {
				var elapsed = Date.now() - animStart;
				var wait = Math.max(0, animMs - elapsed);
				window.setTimeout(function () {
					rollbackCouponRemoveAnimation(rows);
					showNotice("error", "Network error. Please retry.");
					refreshCheckoutSoon();
				}, wait);
			})
			.finally(function () {
				removeReqInFlight = false;
				setRemoveLinkState(link, false);
			});
	});

	document.addEventListener(
		"blur",
		function (e) {
			validateCheckoutField(e.target);
		},
		true
	);

	document.addEventListener("change", function (e) {
		if (e.target && e.target.classList.contains("checkflow-order-bump-checkbox")) {
			var module = e.target.closest("[data-checkflow-bump-product]");
			var productId = module ? module.getAttribute("data-checkflow-bump-product") : "";
			if (!e.target.checked || !productId) {
				return;
			}
			e.target.disabled = true;
			addOrderBump(productId)
				.then(function (res) {
					if (res && res.success) {
						if (module) {
							module.hidden = true;
							module.setAttribute("data-checkflow-bump-added", "1");
						}
						refreshCheckoutSoon();
						return;
					}
					e.target.checked = false;
					e.target.disabled = false;
					showNotice("error", normalizeMessage(res, "Could not add order bump."));
				})
				.catch(function () {
					e.target.checked = false;
					e.target.disabled = false;
					showNotice("error", "Network error. Please retry.");
				});
			return;
		}
		if (e.target && /^(select|checkbox|radio)$/i.test(e.target.type || e.target.tagName || "")) {
			validateCheckoutField(e.target);
		}
		maybeRefreshShipping(e.target);
	});

	window.checkflowCheckoutEngine = window.checkflowCheckoutEngine || {};
	window.checkflowCheckoutEngine.postAction = postAction;
	window.checkflowCheckoutEngine.validateField = validateCheckoutField;
	window.checkflowCheckoutEngine.refreshShipping = function () {
		var form = checkoutForm();
		if (!form) return;
		maybeRefreshShipping(form.querySelector('[name="billing_country"], [name="shipping_country"]'));
	};
	window.checkflowCheckoutEngine.placeOrder = placeOrder;
	window.setTimeout(trackVisibleUpsells, 800);
	window.setTimeout(trackVisibleUpsells, 1800);

})();
