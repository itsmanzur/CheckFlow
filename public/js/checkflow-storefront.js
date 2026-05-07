(function () {
	"use strict";

	if (!window.checkflowStorefront || !window.jQuery) {
		return;
	}

	var drawer = null;
	var lastShownAt = 0;
	var pendingAddToCart = false;
	var lastFocus = null;

	function strings(key, fallback) {
		return (checkflowStorefront.strings && checkflowStorefront.strings[key]) || fallback;
	}

	function ensureDrawer() {
		if (drawer) {
			return drawer;
		}
		drawer = document.createElement("div");
		drawer.className = "checkflow-popup-checkout";
		drawer.setAttribute("role", "dialog");
		drawer.setAttribute("aria-modal", "true");
		drawer.setAttribute("aria-hidden", "true");
		drawer.setAttribute("aria-labelledby", "checkflow-popup-title");
		drawer.setAttribute("aria-describedby", "checkflow-popup-desc");
		drawer.innerHTML =
			'<div class="checkflow-popup-checkout__backdrop" data-checkflow-popup-close></div>' +
			'<div class="checkflow-popup-checkout__panel">' +
			'<button type="button" class="checkflow-popup-checkout__close" data-checkflow-popup-close aria-label="Close"><span aria-hidden="true"></span></button>' +
			'<strong id="checkflow-popup-title">' + strings("title", "Added to cart") + '</strong>' +
			'<p id="checkflow-popup-desc">' + strings("description", "Your item is ready. Choose your next step.") + '</p>' +
			'<div class="checkflow-popup-checkout__actions">' +
			'<a class="checkflow-popup-checkout__primary" href="' + checkflowStorefront.checkoutUrl + '">' + strings("checkout", "Checkout now") + '</a>' +
			'<a class="checkflow-popup-checkout__secondary" href="' + checkflowStorefront.cartUrl + '">' + strings("cart", "View cart") + '</a>' +
			'<button type="button" class="checkflow-popup-checkout__link" data-checkflow-popup-close>' + strings("continue", "Continue shopping") + '</button>' +
			"</div>" +
			"</div>";
		document.body.appendChild(drawer);
		drawer.addEventListener("click", function (event) {
			var closeTarget = event.target && event.target.closest ? event.target.closest("[data-checkflow-popup-close]") : null;
			if (closeTarget) {
				hideDrawer();
			}
		});
		document.addEventListener("keydown", function (event) {
			if (event.key === "Escape") {
				hideDrawer();
			}
		});
		return drawer;
	}

	function showDrawer() {
		var now = Date.now();
		if (now - lastShownAt < 700) {
			return;
		}
		lastShownAt = now;
		var node = ensureDrawer();
		lastFocus = document.activeElement;
		node.classList.add("is-open");
		node.setAttribute("aria-hidden", "false");
		document.documentElement.classList.add("checkflow-popup-open");
		window.setTimeout(function () {
			var primary = node.querySelector(".checkflow-popup-checkout__primary");
			if (primary) {
				primary.focus();
			}
		}, 30);
	}

	function hideDrawer() {
		if (!drawer) {
			return;
		}
		drawer.classList.remove("is-open");
		drawer.setAttribute("aria-hidden", "true");
		document.documentElement.classList.remove("checkflow-popup-open");
		if (lastFocus && lastFocus.focus) {
			lastFocus.focus();
		}
	}

	function looksLikeCartMutation(url) {
		url = String(url || "");
		return (
			url.indexOf("wc-ajax=add_to_cart") !== -1 ||
			(url.indexOf("wc/store") !== -1 && url.indexOf("/cart") !== -1) ||
			url.indexOf("/wc/store/v1/batch") !== -1 ||
			url.indexOf("add_to_cart") !== -1
		);
	}

	function bindFetchFallback() {
		if (!window.fetch || window.fetch.__checkflowPopupBound) {
			return;
		}
		var nativeFetch = window.fetch;
		window.fetch = function () {
			var request = arguments[0];
			var url = typeof request === "string" ? request : request && request.url;
			return nativeFetch.apply(this, arguments).then(function (response) {
				if ((pendingAddToCart || looksLikeCartMutation(url)) && response && response.ok && looksLikeCartMutation(url)) {
					window.setTimeout(showDrawer, 180);
				}
				return response;
			});
		};
		window.fetch.__checkflowPopupBound = true;
	}

	function bindClickFallback() {
		document.addEventListener(
			"click",
			function (event) {
				var button = event.target && event.target.closest ? event.target.closest(".add_to_cart_button[data-product_id]") : null;
				if (!button || button.classList.contains("product_type_variable")) {
					return;
				}
				pendingAddToCart = true;
				window.setTimeout(function () {
					var text = (button.textContent || "").toLowerCase();
					if (
						button.classList.contains("added") ||
						button.classList.contains("added_to_cart") ||
						text.indexOf("in cart") !== -1 ||
						text.indexOf("view cart") !== -1
					) {
						showDrawer();
					}
				}, 700);
				window.setTimeout(function () {
					pendingAddToCart = false;
				}, 3000);
			},
			true
		);
	}

	window.jQuery(document.body).on("added_to_cart", function () {
		showDrawer();
	});

	window.jQuery(document).ajaxComplete(function (event, xhr, settings) {
		var url = settings && settings.url ? String(settings.url) : "";
		var data = settings && settings.data ? String(settings.data) : "";
		var isAddToCart =
			url.indexOf("wc-ajax=add_to_cart") !== -1 ||
			url.indexOf("add_to_cart") !== -1 ||
			data.indexOf("action=woocommerce_add_to_cart") !== -1 ||
			data.indexOf("add-to-cart") !== -1 ||
			data.indexOf("product_id=") !== -1;
		if (!isAddToCart || !xhr || xhr.status < 200 || xhr.status >= 300) {
			return;
		}
		showDrawer();
	});

	bindFetchFallback();
	bindClickFallback();
})();
