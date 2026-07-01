(function () {
	"use strict";

	if (!window.checkflowAnalytics || !checkflowAnalytics.ajaxUrl || !checkflowAnalytics.nonce || !window.fetch) {
		return;
	}

	var fired = {};

	function eventId(name, suffix) {
		return ("cfa_" + name + "_" + (suffix || Date.now())).replace(/[^a-zA-Z0-9_]/g, "_").slice(0, 100);
	}

	function postEvent(name, context, id) {
		if (!name) {
			return;
		}
		var key = name + ":" + (id || "");
		if (id && fired[key]) {
			return;
		}
		fired[key] = true;

		var data = new URLSearchParams();
		data.append("action", "checkflow_log_analytics_event");
		data.append("nonce", checkflowAnalytics.nonce);
		data.append("event_name", name);
		data.append("event_id", id || eventId(name));
		data.append("page_url", window.location.href);
		data.append("context", JSON.stringify(context || {}));

		window.fetch(checkflowAnalytics.ajaxUrl, {
			method: "POST",
			credentials: "same-origin",
			headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
			body: data.toString(),
			keepalive: true,
		}).catch(function () {});
	}

	function paymentValue(input) {
		if (!input) {
			return "";
		}
		return input.value || input.getAttribute("value") || "";
	}

	function bindCheckoutStarted() {
		var started = false;
		document.addEventListener(
			"input",
			function (event) {
				if (started || !event.target || !event.target.closest || !event.target.closest("form.checkout, .wc-block-checkout")) {
					return;
				}
				started = true;
				postEvent("checkout_started", { trigger: "field_input" }, eventId("checkout_started", "field"));
			},
			true
		);
	}

	function bindPaymentSelected() {
		document.addEventListener(
			"change",
			function (event) {
				var input = event.target && event.target.matches ? event.target : null;
				if (!input || input.name !== "payment_method") {
					return;
				}
				postEvent("payment_selected", { payment_method: paymentValue(input) }, eventId("payment_selected", paymentValue(input) || Date.now()));
			},
			true
		);
		document.addEventListener(
			"click",
			function (event) {
				var input = event.target && event.target.closest ? event.target.closest('input[name="payment_method"]') : null;
				if (!input) {
					return;
				}
				postEvent("payment_selected", { payment_method: paymentValue(input) }, eventId("payment_selected", paymentValue(input) || Date.now()));
			},
			true
		);
	}

	function bindCartActions() {
		document.addEventListener(
			"click",
			function (event) {
				var button = event.target && event.target.closest ? event.target.closest(".add_to_cart_button, button[name='add-to-cart'], button.single_add_to_cart_button") : null;
				if (!button) {
					return;
				}
				var productId = button.getAttribute("data-product_id") || button.value || "";
				var quantity = parseInt(button.getAttribute("data-quantity") || "1", 10) || 1;
				window.setTimeout(function () {
					postEvent("add_to_cart", { product_ids: productId ? [String(productId)] : [], quantity: quantity }, eventId("add_to_cart", productId || Date.now()));
				}, 150);
			},
			true
		);
	}

	function boot() {
		if (checkflowAnalytics.isCheckout) {
			postEvent("checkout_view", checkflowAnalytics.cartContext || {}, eventId("checkout_view", "page"));
			bindCheckoutStarted();
			bindPaymentSelected();
		}
		bindCartActions();
	}

	boot();
})();
