(function () {
	"use strict";

	if (!window.checkflowCheckout) {
		return;
	}

	var timer = null;
	var qtyAbortController = null;
	var couponReqInFlight = false;
	var removeReqInFlight = false;
	var noticeTimer = null;

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
		Object.keys(data || {}).forEach(function (k) {
			body.append(k, data[k]);
		});
		return fetch(checkflowCheckout.ajaxUrl, {
			method: "POST",
			headers: {
				"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
			},
			body: body.toString(),
			credentials: "same-origin",
			signal: o.signal,
		}).then(function (r) {
			return r.json();
		});
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
					showNotice("success", (res.data && res.data.message) || "Coupon applied.");
					refreshCheckoutSoon();
					return;
				}
				setCouponInputState(form, true);
				optimisticCouponRow("remove", code);
				showNotice("error", (res && res.data && res.data.message) || "Failed to apply coupon.");
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
						showNotice("success", (res.data && res.data.message) || "Coupon removed.");
						refreshCheckoutSoon();
						return;
					}
					rollbackCouponRemoveAnimation(rows);
					showNotice("error", (res && res.data && res.data.message) || "Failed to remove coupon.");
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

})();
