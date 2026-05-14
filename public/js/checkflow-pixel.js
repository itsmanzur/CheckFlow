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

	function ensureGoogleTag() {
		if (!checkflowPixel.googleEnabled || !checkflowPixel.googleId) {
			return false;
		}
		window.dataLayer = window.dataLayer || [];
		window.gtag =
			window.gtag ||
			function () {
				window.dataLayer.push(arguments);
			};
		if (window.gtag.__checkflowReady) {
			return true;
		}
		var script = document.createElement("script");
		script.async = true;
		script.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(checkflowPixel.googleId);
		var first = document.getElementsByTagName("script")[0];
		first.parentNode.insertBefore(script, first);
		window.gtag("js", new Date());
		window.gtag("config", checkflowPixel.googleId, { send_page_view: false });
		window.gtag.__checkflowReady = true;
		return true;
	}

	function ensureTikTokPixel() {
		if (!checkflowPixel.tiktokEnabled || !checkflowPixel.tiktokPixelId) {
			return false;
		}
		if (window.ttq && window.ttq.__checkflowReady) {
			return true;
		}
		/* eslint-disable */
		!function (w, d, t) {
			w.TiktokAnalyticsObject = t;
			var ttq = w[t] = w[t] || [];
			ttq.methods = ["page", "track", "identify", "instances", "debug", "on", "off", "once", "ready", "alias", "group", "enableCookie", "disableCookie", "holdConsent", "revokeConsent", "grantConsent"];
			ttq.setAndDefer = function (t, e) { t[e] = function () { t.push([e].concat(Array.prototype.slice.call(arguments, 0))); }; };
			for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
			ttq.instance = function (t) { var e = ttq._i[t] || []; for (var n = 0; n < ttq.methods.length; n++) ttq.setAndDefer(e, ttq.methods[n]); return e; };
			ttq.load = function (e, n) { var r = "https://analytics.tiktok.com/i18n/pixel/events.js"; ttq._i = ttq._i || {}; ttq._i[e] = []; ttq._i[e]._u = r; ttq._t = ttq._t || {}; ttq._t[e] = +new Date(); ttq._o = ttq._o || {}; ttq._o[e] = n || {}; var a = d.createElement("script"); a.type = "text/javascript"; a.async = true; a.src = r + "?sdkid=" + e + "&lib=" + t; var s = d.getElementsByTagName("script")[0]; s.parentNode.insertBefore(a, s); };
		}(window, document, "ttq");
		/* eslint-enable */
		window.ttq.load(checkflowPixel.tiktokPixelId);
		window.ttq.__checkflowReady = true;
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
			log("[CheckFlow Pixel] Meta fired", name, id || "");
		}
		fireGoogle(name, params || {}, id || "");
		fireTikTok(name, params || {}, id || "");
		log("[CheckFlow Pixel]", name, params || {}, id || "", checkflowPixel.providerState || {});
	}

	function money(value) {
		var number = parseFloat(value);
		return Number.isFinite(number) ? number : 0;
	}

	function googleEventName(name) {
		return {
			PageView: "page_view",
			ViewContent: "view_item",
			AddToCart: "add_to_cart",
			InitiateCheckout: "begin_checkout",
			Purchase: "purchase",
		}[name];
	}

	function googleItems(params) {
		return (params.content_ids || []).map(function (id) {
			return { item_id: String(id) };
		});
	}

	function fireGoogle(name, params, id) {
		var mapped = googleEventName(name);
		if (!mapped || !ensureGoogleTag()) {
			return;
		}
		var payload = {
			currency: params.currency || undefined,
			value: money(params.value),
			items: googleItems(params),
			event_id: id || undefined,
		};
		if (name === "PageView") {
			payload.page_location = window.location.href;
			payload.page_title = document.title;
		}
		if (name === "Purchase") {
			payload.transaction_id = params.order_id || id || undefined;
		}
		window.gtag("event", mapped, payload);
		if (name === "Purchase" && /^AW-/i.test(checkflowPixel.googleId || "") && checkflowPixel.googleLabel) {
			window.gtag("event", "conversion", {
				send_to: checkflowPixel.googleId + "/" + checkflowPixel.googleLabel,
				value: money(params.value),
				currency: params.currency || undefined,
				transaction_id: params.order_id || id || undefined,
			});
		}
		log("[CheckFlow Pixel] Google fired", name, payload);
	}

	function tiktokEventName(name) {
		return {
			ViewContent: "ViewContent",
			AddToCart: "AddToCart",
			InitiateCheckout: "InitiateCheckout",
			Purchase: "CompletePayment",
		}[name];
	}

	function fireTikTok(name, params, id) {
		if (!ensureTikTokPixel()) {
			return;
		}
		if (name === "PageView") {
			window.ttq.page();
			log("[CheckFlow Pixel] TikTok fired", name);
			return;
		}
		var mapped = tiktokEventName(name);
		if (!mapped) {
			return;
		}
		var payload = {
			content_id: params.content_ids && params.content_ids[0] ? String(params.content_ids[0]) : undefined,
			content_type: params.content_type || "product",
			quantity: params.quantity || params.num_items || undefined,
			value: money(params.value),
			currency: params.currency || undefined,
			event_id: id || undefined,
		};
		window.ttq.track(mapped, payload);
		log("[CheckFlow Pixel] TikTok fired", name, payload);
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
