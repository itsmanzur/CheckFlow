(function () {
	"use strict";

	if (!window.checkflowCheckout) {
		return;
	}

	var state = {
		activeStep: "contact",
		busy: false,
		lastError: "",
	};

	var steps = [
		{ key: "contact", label: "Contact", selector: "#customer_details" },
		{ key: "shipping", label: "Delivery", selector: ".woocommerce-shipping-fields, .woocommerce-billing-fields" },
		{ key: "payment", label: "Payment", selector: "#order_review" },
	];

	function checkoutRoot() {
		return (
			document.querySelector(".wc-block-checkout") ||
			document.querySelector(".wp-block-woocommerce-checkout") ||
			document.querySelector(".woocommerce-checkout .woocommerce") ||
			document.querySelector(".woocommerce-checkout")
		);
	}

	function blockCheckout() {
		return (
			document.querySelector(".wc-block-checkout") ||
			document.querySelector(".wp-block-woocommerce-checkout") ||
			document.querySelector(".wc-block-components-checkout-step") ||
			null
		);
	}

	function isBlockCheckout() {
		return !!blockCheckout() && !checkoutForm();
	}

	function checkoutForm() {
		return document.querySelector("form.checkout");
	}

	function orderReview() {
		return (
			document.querySelector(".wc-block-checkout__sidebar") ||
			document.querySelector(".wc-block-components-sidebar") ||
			document.querySelector("#order_review")
		);
	}

	function orderReviewCard() {
		return (
			document.querySelector(".wc-block-components-sidebar") ||
			document.querySelector("#order_review") ||
			document.querySelector(".wc-block-checkout__sidebar")
		);
	}

	function ensureTrustBadgesPlacement() {
		var review = orderReview();
		var card = orderReviewCard();
		if (!review || !review.parentNode || !card) {
			return;
		}
		var allBadges = Array.prototype.slice.call(document.querySelectorAll(".checkflow-trust-badges"));
		var badges = allBadges[0] || null;
		if (!badges) {
			return;
		}
		allBadges.slice(1).forEach(function (duplicate) {
			duplicate.remove();
		});

		var slot = review.querySelector(":scope > .checkflow-trust-badges-slot");
		if (!slot) {
			slot = document.createElement("div");
			slot.className = "checkflow-trust-badges-slot";
			review.appendChild(slot);
		}
		badges.classList.add("checkflow-trust-badges--sidebar");
		slot.appendChild(badges);
	}

	function ensureQuickModulesPlacement() {
		var review = orderReview();
		if (!review) {
			return;
		}
		var modules = document.querySelector(".checkflow-checkout-modules");
		if (!modules) {
			return;
		}
		var slot = review.querySelector(":scope > .checkflow-checkout-modules-slot");
		if (!slot) {
			slot = document.createElement("div");
			slot.className = "checkflow-checkout-modules-slot";
			review.appendChild(slot);
		}
		slot.appendChild(modules);
	}

	function initCountdowns() {
		document.querySelectorAll("[data-checkflow-countdown-seconds]").forEach(function (timer) {
			if (timer.getAttribute("data-checkflow-bound")) {
				return;
			}
			timer.setAttribute("data-checkflow-bound", "1");
			var total = parseInt(timer.getAttribute("data-checkflow-countdown-seconds"), 10);
			var output = timer.querySelector("[data-checkflow-countdown]");
			if (!output || !total || total < 1) {
				return;
			}
			function render() {
				var minutes = Math.floor(total / 60);
				var seconds = total % 60;
				output.textContent = String(minutes).padStart(2, "0") + ":" + String(seconds).padStart(2, "0");
				if (total > 0) {
					total -= 1;
				}
			}
			render();
			window.setInterval(render, 1000);
		});
	}

	function cssEscape(value) {
		if (window.CSS && window.CSS.escape) {
			return window.CSS.escape(value);
		}
		return String(value).replace(/"/g, '\\"');
	}

	function fieldCandidates(key) {
		var clean = String(key || "");
		var dash = clean.replace(/_/g, "-");
		var stripped = clean.replace(/^(billing|shipping)_/, "");
		var strippedDash = stripped.replace(/_/g, "-");
		var custom = clean.replace(/^checkflow_custom_/, "");
		return [clean, dash, stripped, strippedDash, "checkflow/" + custom].filter(Boolean);
	}

	function findFieldControl(key) {
		var candidates = fieldCandidates(key);
		for (var i = 0; i < candidates.length; i += 1) {
			var value = candidates[i];
			var selector =
				'input[name="' +
				cssEscape(value) +
				'"], select[name="' +
				cssEscape(value) +
				'"], textarea[name="' +
				cssEscape(value) +
				'"], input[id="' +
				cssEscape(value) +
				'"], select[id="' +
				cssEscape(value) +
				'"], textarea[id="' +
				cssEscape(value) +
				'"]';
			var control = document.querySelector(selector);
			if (control) {
				return control;
			}
		}
		return null;
	}

	function fieldWrapper(control) {
		return (
			control.closest(".form-row") ||
			control.closest(".wc-block-components-text-input") ||
			control.closest(".wc-block-components-select") ||
			control.closest(".wc-block-components-checkbox") ||
			control.closest(".wc-block-components-address-form__address_1") ||
			control.parentElement
		);
	}

	function applyFieldWidth(wrapper, width) {
		if (!wrapper) return;
		wrapper.classList.remove("checkflow-field-width-full", "checkflow-field-width-first", "checkflow-field-width-last");
		if (width === "full") {
			wrapper.classList.add("checkflow-field-width-full");
		}
		if (width === "first" || width === "half") {
			wrapper.classList.add("checkflow-field-width-first");
		}
		if (width === "last") {
			wrapper.classList.add("checkflow-field-width-last");
		}
	}

	function applyFieldHelp(wrapper, key, help) {
		if (!wrapper || !help) return;
		var note = wrapper.querySelector('.checkflow-field-help[data-field-key="' + cssEscape(key) + '"]');
		if (!note) {
			note = document.createElement("small");
			note.className = "checkflow-field-help";
			note.setAttribute("data-field-key", key);
			wrapper.appendChild(note);
		}
		note.textContent = help;
	}

	function applyAdvancedFieldSettings() {
		var meta = window.checkflowCheckout && checkflowCheckout.fieldMeta ? checkflowCheckout.fieldMeta : {};
		Object.keys(meta).forEach(function (key) {
			var config = meta[key] || {};
			var control = findFieldControl(key);
			if (!control) return;
			var wrapper = fieldWrapper(control);
			if (config.placeholder && "placeholder" in control) {
				control.setAttribute("placeholder", config.placeholder);
			}
			if (config.defaultValue && !control.value && control.type !== "checkbox") {
				control.value = config.defaultValue;
				control.dispatchEvent(new Event("input", { bubbles: true }));
				control.dispatchEvent(new Event("change", { bubbles: true }));
			}
			applyFieldWidth(wrapper, config.width || "default");
			applyFieldHelp(wrapper, key, config.help || "");
		});
	}

	function createStepButton(step) {
		var btn = document.createElement("button");
		btn.type = "button";
		btn.className = "checkflow-step";
		btn.setAttribute("data-checkflow-step", step.key);
		btn.setAttribute("aria-pressed", step.key === state.activeStep ? "true" : "false");
		btn.textContent = step.label;
		btn.addEventListener("click", function () {
			goToStep(step.key);
		});
		return btn;
	}

	function mountStepper(form) {
		if (document.querySelector(".checkflow-stepper")) {
			return;
		}
		var stepper = document.createElement("nav");
		stepper.className = "checkflow-stepper";
		stepper.setAttribute("aria-label", "Checkout steps");
		steps.forEach(function (step) {
			stepper.appendChild(createStepButton(step));
		});
		form.parentNode.insertBefore(stepper, form);
	}

	function updateStepButtons() {
		document.querySelectorAll("[data-checkflow-step]").forEach(function (btn) {
			var active = btn.getAttribute("data-checkflow-step") === state.activeStep;
			btn.classList.toggle("is-active", active);
			btn.setAttribute("aria-pressed", active ? "true" : "false");
		});
	}

	function nearestStepFromElement(el) {
		if (!el || !el.closest) {
			return "";
		}
		if (el.closest("#order_review, .woocommerce-checkout-payment")) {
			return "payment";
		}
		if (el.closest(".woocommerce-shipping-fields, .shipping_address")) {
			return "shipping";
		}
		if (el.closest("#customer_details, .woocommerce-billing-fields")) {
			return "contact";
		}
		return "";
	}

	function goToStep(stepKey) {
		var step = steps.filter(function (item) {
			return item.key === stepKey;
		})[0];
		if (!step) {
			return;
		}
		var target = document.querySelector(step.selector);
		state.activeStep = step.key;
		updateStepButtons();
		if (target && target.scrollIntoView) {
			target.scrollIntoView({ behavior: "smooth", block: "start" });
		}
	}

	function setBusy(busy) {
		state.busy = !!busy;
		document.documentElement.classList.toggle("checkflow-is-busy", state.busy);
		var form = checkoutForm();
		if (form) {
			form.classList.toggle("checkflow-is-busy", state.busy);
		}
		var root = checkoutRoot();
		if (root) {
			root.classList.toggle("checkflow-is-busy", state.busy);
		}
	}

	function setError(message) {
		state.lastError = message || "";
		var target = checkoutForm() || checkoutRoot();
		if (!target) {
			return;
		}
		var node = target.querySelector(".checkflow-checkout-error");
		if (!state.lastError) {
			if (node) {
				node.remove();
			}
			return;
		}
		if (!node) {
			node = document.createElement("div");
			node.className = "checkflow-checkout-error";
			node.setAttribute("role", "alert");
			target.insertBefore(node, target.firstChild);
		}
		node.textContent = state.lastError;
	}

	function enhancePlaceOrder(scope) {
		if (!scope || !scope.querySelector) {
			return;
		}
		var placeOrder = scope.querySelector(
			"#place_order, .wc-block-components-checkout-place-order-button, .wc-block-checkout__actions button, .wc-block-checkout__actions_row button"
		);
		if (!placeOrder || placeOrder.getAttribute("data-checkflow-bound")) {
			return;
		}
		placeOrder.setAttribute("data-checkflow-bound", "1");
		placeOrder.addEventListener("click", function () {
			setError("");
			setBusy(true);
			window.setTimeout(function () {
				setBusy(false);
			}, 5000);
		});
	}

	function observeCheckoutEvents() {
		if (!window.jQuery) {
			return;
		}
		window.jQuery(document.body)
			.on("update_checkout", function () {
				setBusy(true);
			})
			.on("updated_checkout checkout_error", function () {
				setBusy(false);
				enhancePlaceOrder(checkoutForm() || document);
				applyAdvancedFieldSettings();
			})
			.on("checkout_error", function () {
				state.activeStep = "contact";
				updateStepButtons();
			});
	}

	function bindFocusTracking(form) {
		form.addEventListener("focusin", function (event) {
			var step = nearestStepFromElement(event.target);
			if (!step) {
				return;
			}
			state.activeStep = step;
			updateStepButtons();
		});
	}

	function addAppAttributes(form) {
		form.classList.add("checkflow-app");
		form.setAttribute("data-checkflow-ready", "1");
		var review = orderReview();
		if (review) {
			review.setAttribute("data-checkflow-summary", "1");
		}
		ensureTrustBadgesPlacement();
		ensureQuickModulesPlacement();
		initCountdowns();
		applyAdvancedFieldSettings();
	}

	function initBlocks() {
		var root = blockCheckout() || checkoutRoot();
		if (!root || root.getAttribute("data-checkflow-ready")) {
			return;
		}
		root.classList.add("checkflow-app", "checkflow-block-app");
		root.setAttribute("data-checkflow-ready", "1");
		var review = orderReview();
		if (review) {
			review.setAttribute("data-checkflow-summary", "1");
		}
		ensureTrustBadgesPlacement();
		ensureQuickModulesPlacement();
		initCountdowns();
		enhancePlaceOrder(root);
		applyAdvancedFieldSettings();
		observeCheckoutEvents();
	}

	function initClassic() {
		var form = checkoutForm();
		if (!form || form.getAttribute("data-checkflow-ready")) {
			return;
		}
		addAppAttributes(form);
		mountStepper(form);
		bindFocusTracking(form);
		enhancePlaceOrder(form);
		ensureTrustBadgesPlacement();
		observeCheckoutEvents();
		updateStepButtons();
	}

	function initVanilla() {
		if (isBlockCheckout()) {
			initBlocks();
			window.setTimeout(function () {
				ensureTrustBadgesPlacement();
				ensureQuickModulesPlacement();
				initCountdowns();
				applyAdvancedFieldSettings();
			}, 250);
			window.setTimeout(function () {
				ensureTrustBadgesPlacement();
				ensureQuickModulesPlacement();
				initCountdowns();
				applyAdvancedFieldSettings();
			}, 1000);
			return;
		}
		initClassic();
		window.setTimeout(function () {
			ensureTrustBadgesPlacement();
			ensureQuickModulesPlacement();
			initCountdowns();
			applyAdvancedFieldSettings();
		}, 250);
	}

	function alpineData() {
		return {
			activeStep: state.activeStep,
			busy: state.busy,
			lastError: state.lastError,
			goToStep: goToStep,
			setBusy: setBusy,
			setError: setError,
			init: initVanilla,
		};
	}

	if (window.Alpine && window.Alpine.data) {
		window.Alpine.data("checkflowCheckout", alpineData);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", initVanilla);
	} else {
		initVanilla();
	}

	window.checkflowCheckoutApp = window.checkflowCheckoutApp || {};
	window.checkflowCheckoutApp.goToStep = goToStep;
	window.checkflowCheckoutApp.setBusy = setBusy;
	window.checkflowCheckoutApp.setError = setError;
	window.checkflowCheckoutApp.state = state;
})();
