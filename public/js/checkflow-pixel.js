(function () {
	"use strict";

	if (!window.checkflowPixel) {
		return;
	}

	var fired = {};

	function log() {
		if (checkflowPixel.debug && window.console && console.log) {
			console.log.apply(console, arguments);
		}
	}

	function ensureMetaPixel() {
		if (!checkflowPixel.metaEnabled || !checkflowPixel.metaPixelId) {
			return false;
		}
		if (window.fbq && window.fbq.__checkflowReady) {
			return true;
		}
		/* eslint-disable */
		!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
		n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
		n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
		t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');
		/* eslint-enable */
		window.fbq("init", checkflowPixel.metaPixelId);
		window.fbq.__checkflowReady = true;
		return true;
	}

	function eventId(name, suffix) {
		var base = "cf_" + name + "_" + (suffix || Date.now());
		return base.replace(/[^a-zA-Z0-9_]/g, "_").slice(0, 80);
	}

	function fire(name, params, id) {
		if (!name) {
			return;
		}
		if (checkflowPixel.enabledEvents && checkflowPixel.enabledEvents[name] === false) {
			log("[CheckFlow Pixel] disabled event skipped", name);
			return;
		}
		var key = name + ":" + (id || "");
		if (id && fired[key]) {
			return;
		}
		fired[key] = true;
		logLocalEvent(name, params || {}, id || eventId(name, Date.now()));
		if (ensureMetaPixel()) {
			window.fbq("track", name, params || {}, id ? { eventID: id } : {});
		}
		log("[CheckFlow Pixel]", name, params || {}, id || "", checkflowPixel.providerState || {});
	}

	function logLocalEvent(name, params, id) {
		if (!checkflowPixel.localEnabled || !checkflowPixel.ajaxUrl || !checkflowPixel.nonce || !window.fetch) {
			return;
		}
		var data = new URLSearchParams();
		data.append("action", "checkflow_log_pixel_event");
		data.append("nonce", checkflowPixel.nonce);
		data.append("event_name", name);
		data.append("event_id", id);
		data.append("page_url", window.location.href);
		data.append("context", JSON.stringify(params || {}));
		window.fetch(checkflowPixel.ajaxUrl, {
			method: "POST",
			credentials: "same-origin",
			headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
			body: data.toString(),
			keepalive: true,
		}).catch(function (error) {
			log("[CheckFlow Pixel] local log failed", error);
		});
	}

	function productFromButton(button) {
		if (!button) {
			return {};
		}
		var id = button.getAttribute("data-product_id") || button.value || "";
		var qty = button.getAttribute("data-quantity") || "1";
		return {
			content_ids: id ? [String(id)] : [],
			content_type: "product",
			quantity: parseInt(qty, 10) || 1,
		};
	}

	function bindAddToCart() {
		document.addEventListener(
			"click",
			function (event) {
				var button = event.target && event.target.closest ? event.target.closest(".add_to_cart_button, button[name='add-to-cart'], button.single_add_to_cart_button") : null;
				if (!button) {
					return;
				}
				var params = productFromButton(button);
				window.setTimeout(function () {
					fire("AddToCart", params, eventId("add_to_cart", params.content_ids && params.content_ids[0] ? params.content_ids[0] : Date.now()));
				}, 150);
			},
			true
		);
	}

	function fireInitialEvents() {
		fire("PageView", {}, eventId("page_view", window.location.pathname));
		(checkflowPixel.events || []).forEach(function (event) {
			fire(event.name, event.params || {}, event.event_id || "");
		});
	}

	bindAddToCart();
	fireInitialEvents();
})();
