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
			document.querySelector(".woocommerce-checkout-review-order") ||
			document.querySelector("#order_review")
		);
	}

	function orderReviewCard() {
		return (
			document.querySelector(".wc-block-components-sidebar") ||
			document.querySelector(".woocommerce-checkout-review-order") ||
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
		var modules = document.querySelector(".checkflow-checkout-modules");
		if (!modules) {
			return;
		}
		var placement = modules.getAttribute("data-checkflow-placement") || "after_summary";
		var payment = document.querySelector("#payment, .woocommerce-checkout-payment, .wc-block-checkout__payment-method");
		if (placement === "before_payment" && payment && payment.parentNode) {
			payment.parentNode.insertBefore(modules, payment);
			modules.setAttribute("data-checkflow-positioned", "1");
			refreshOrderBumpRules();
			return;
		}
		if (placement === "after_payment" && payment && payment.parentNode) {
			payment.parentNode.insertBefore(modules, payment.nextSibling);
			modules.setAttribute("data-checkflow-positioned", "1");
			refreshOrderBumpRules();
			return;
		}

		var review = orderReview();
		if (!review) {
			return;
		}
		var slot = review.querySelector(":scope > .checkflow-checkout-modules-slot");
		if (!slot) {
			slot = document.createElement("div");
			slot.className = "checkflow-checkout-modules-slot";
			review.appendChild(slot);
		}
		slot.appendChild(modules);
		modules.setAttribute("data-checkflow-positioned", "1");
		refreshOrderBumpRules();
	}

	function csvRules(value) {
		return String(value || "")
			.split(",")
			.map(function (item) {
				return item.trim().toLowerCase();
			})
			.filter(Boolean);
	}

	function selectedPaymentMethod() {
		var checked = document.querySelector('input[name="payment_method"]:checked');
		return checked ? String(checked.value || "").toLowerCase() : "";
	}

	function selectedCheckoutCountry() {
		var shipping = document.querySelector('[name="shipping_country"], #shipping_country');
		var billing = document.querySelector('[name="billing_country"], #billing_country');
		var country = shipping && shipping.value ? shipping.value : billing && billing.value ? billing.value : "";
		return String(country || "").toLowerCase();
	}

	function refreshOrderBumpRules() {
		document.querySelectorAll(".checkflow-order-bump-module").forEach(function (module) {
			var paymentRules = csvRules(module.getAttribute("data-checkflow-bump-payments"));
			var countryRules = csvRules(module.getAttribute("data-checkflow-bump-countries"));
			var payment = selectedPaymentMethod();
			var country = selectedCheckoutCountry();
			var paymentOk = !paymentRules.length || !payment || paymentRules.indexOf(payment) !== -1;
			var countryOk = !countryRules.length || !country || countryRules.indexOf(country) !== -1;
			module.hidden = !(paymentOk && countryOk);
		});
		document.querySelectorAll(".checkflow-upsell-module").forEach(function (module) {
			if (module.getAttribute("data-checkflow-upsell-manual-hidden") === "1") {
				return;
			}
			var paymentRules = csvRules(module.getAttribute("data-checkflow-upsell-payments"));
			var countryRules = csvRules(module.getAttribute("data-checkflow-upsell-countries"));
			var payment = selectedPaymentMethod();
			var country = selectedCheckoutCountry();
			var paymentOk = !paymentRules.length || !payment || paymentRules.indexOf(payment) !== -1;
			var countryOk = !countryRules.length || !country || countryRules.indexOf(country) !== -1;
			module.hidden = !(paymentOk && countryOk);
		});
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

	function repairMojibakeText(value) {
		var text = String(value || "");
		if (text.indexOf("\u00e0\u00a6") === -1 && text.indexOf("\u00e0\u00a7") === -1) {
			return text;
		}
		if (!window.TextDecoder || !window.Uint8Array) {
			return text;
		}
		return text.replace(/(?:\u00e0[\u00a6\u00a7].)+/gu, function (segment) {
			return decodeMojibakeSegment(segment);
		});
	}

	function decodeMojibakeSegment(segment) {
		var cp1252 = {
			0x20ac: 0x80,
			0x201a: 0x82,
			0x0192: 0x83,
			0x201e: 0x84,
			0x2026: 0x85,
			0x2020: 0x86,
			0x2021: 0x87,
			0x02c6: 0x88,
			0x2030: 0x89,
			0x0160: 0x8a,
			0x2039: 0x8b,
			0x0152: 0x8c,
			0x017d: 0x8e,
			0x2018: 0x91,
			0x2019: 0x92,
			0x201c: 0x93,
			0x201d: 0x94,
			0x2022: 0x95,
			0x2013: 0x96,
			0x2014: 0x97,
			0x02dc: 0x98,
			0x2122: 0x99,
			0x0161: 0x9a,
			0x203a: 0x9b,
			0x0153: 0x9c,
			0x017e: 0x9e,
			0x0178: 0x9f,
		};
		var bytes = [];
		for (var i = 0; i < segment.length; i += 1) {
			var code = segment.codePointAt(i);
			if (code > 0xffff) {
				i += 1;
			}
			if (code <= 0xff) {
				bytes.push(code);
			} else if (cp1252[code]) {
				bytes.push(cp1252[code]);
			} else {
				return segment;
			}
		}
		try {
			var repaired = new TextDecoder("utf-8", { fatal: true }).decode(new Uint8Array(bytes));
			return /[\u0980-\u09ff]/.test(repaired) ? repaired : segment;
		} catch (e) {
			return segment;
		}
	}

	function repairCheckoutMojibake(root) {
		var scope = root || checkoutRoot() || document.body;
		if (!scope) {
			return;
		}
		var walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT);
		var node;
		while ((node = walker.nextNode())) {
			node.nodeValue = repairMojibakeText(node.nodeValue);
		}
		scope.querySelectorAll("input, textarea, select, option").forEach(function (field) {
			["placeholder", "aria-label", "title"].forEach(function (attr) {
				var value = field.getAttribute(attr);
				if (value) {
					field.setAttribute(attr, repairMojibakeText(value));
				}
			});
		});
	}

	function fieldCandidates(key) {
		var clean = String(key || "");
		var dash = clean.replace(/_/g, "-");
		var stripped = clean.replace(/^(billing|shipping)_/, "");
		var strippedDash = stripped.replace(/_/g, "-");
		var custom = clean.replace(/^checkflow_custom_/, "");
		return [clean, dash, stripped, strippedDash, "checkflow/" + custom].filter(Boolean);
	}

	function controlIsUsable(control) {
		if (!control || control.disabled) return false;
		if (control.type === "hidden") return false;
		var wrapper = fieldWrapper(control);
		if (wrapper && wrapper.offsetParent === null) return false;
		if (control.offsetParent === null && control.type !== "radio" && control.type !== "checkbox") return false;
		return true;
	}

	function findFieldControl(key) {
		var candidates = fieldCandidates(key);
		var fallback = null;
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
			var controls = Array.prototype.slice.call(document.querySelectorAll(selector));
			for (var j = 0; j < controls.length; j += 1) {
				var control = controls[j];
				if (!fallback && control.type !== "hidden") {
					fallback = control;
				}
				if (controlIsUsable(control) && String(control.value || "").trim()) {
					return control;
				}
			}
			for (var k = 0; k < controls.length; k += 1) {
				if (controlIsUsable(controls[k])) {
					return controls[k];
				}
			}
		}
		return fallback;
	}

	function findFilledFieldControl(key) {
		var candidates = fieldCandidates(key);
		var fallback = null;
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
			var controls = Array.prototype.slice.call(document.querySelectorAll(selector));
			for (var j = 0; j < controls.length; j += 1) {
				var control = controls[j];
				if (!fallback && control.type !== "hidden") fallback = control;
				if (controlIsUsable(control) && String(control.value || "").trim()) {
					return control;
				}
			}
		}
		return fallback;
	}

	function syncCanonicalCheckoutFields() {
		var form = checkoutForm();
		if (!form) return;
		var coreFields = [
			"billing_first_name",
			"billing_last_name",
			"billing_company",
			"billing_country",
			"billing_address_1",
			"billing_address_2",
			"billing_city",
			"billing_state",
			"billing_postcode",
			"billing_phone",
			"billing_email",
			"shipping_first_name",
			"shipping_last_name",
			"shipping_company",
			"shipping_country",
			"shipping_address_1",
			"shipping_address_2",
			"shipping_city",
			"shipping_state",
			"shipping_postcode",
		];
		coreFields.forEach(function (key) {
			var canonical = form.querySelector('[name="' + cssEscape(key) + '"]');
			var source = findFilledFieldControl(key);
			var sourceValue = source ? String(source.value || "").trim() : "";
			if (!sourceValue || (canonical && String(canonical.value || "").trim())) {
				return;
			}
			if (!canonical) {
				canonical = document.createElement("input");
				canonical.type = "hidden";
				canonical.name = key;
				canonical.setAttribute("data-checkflow-canonical-sync", "1");
				form.appendChild(canonical);
			}
			canonical.value = sourceValue;
		});
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

	function clearFieldError(control) {
		var wrapper = fieldWrapper(control);
		if (!wrapper) return;
		wrapper.classList.remove("checkflow-field-invalid");
		var error = wrapper.querySelector(".checkflow-field-error");
		if (error) {
			error.remove();
		}
		if (control.removeAttribute) {
			control.removeAttribute("aria-invalid");
		}
	}

	function fieldIsConditionHidden(control) {
		var wrapper = fieldWrapper(control);
		return !!(wrapper && wrapper.classList.contains("checkflow-field-conditional-hidden"));
	}

	function setFieldError(control, message) {
		var wrapper = fieldWrapper(control);
		if (!wrapper || !message) return;
		wrapper.classList.add("checkflow-field-invalid");
		control.setAttribute("aria-invalid", "true");
		var error = wrapper.querySelector(".checkflow-field-error");
		if (!error) {
			error = document.createElement("small");
			error.className = "checkflow-field-error";
			wrapper.appendChild(error);
		}
		error.textContent = message;
	}

	function fieldLabel(config) {
		return (config && config.label) || "This field";
	}

	function validationMessage(config, fallback) {
		return (config && config.validationMessage) || fallback;
	}

	function validateConfiguredValue(value, config) {
		var label = fieldLabel(config);
		var text = String(value || "").trim();
		if (config.required === true || config.required === "1") {
			if (!text) {
				return config.requiredMessage || label + " is required.";
			}
		}
		if (!text) {
			return "";
		}
		if (config.validation === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text)) {
			return validationMessage(config, label + " must be a valid email address.");
		}
		if (config.validation === "phone" && !/^[0-9+\-\s().]{7,20}$/.test(text)) {
			return validationMessage(config, label + " must be a valid phone number.");
		}
		if (config.validation === "number") {
			var number = Number(text);
			if (!isFinite(number)) {
				return validationMessage(config, label + " must be a number.");
			}
			if (config.min !== "" && config.min != null && number < Number(config.min)) {
				return validationMessage(config, label + " must be at least " + config.min + ".");
			}
			if (config.max !== "" && config.max != null && number > Number(config.max)) {
				return validationMessage(config, label + " must be no more than " + config.max + ".");
			}
		}
		if (config.validation === "text" && !/^[\p{L}\p{M}\s.'-]+$/u.test(text)) {
			return validationMessage(config, label + " can only contain letters.");
		}
		var minLength = parseInt(config.minLength || "0", 10);
		var maxLength = parseInt(config.maxLength || "0", 10);
		if (minLength && text.length < minLength) {
			return validationMessage(config, label + " must be at least " + minLength + " characters.");
		}
		if (maxLength && text.length > maxLength) {
			return validationMessage(config, label + " must be no more than " + maxLength + " characters.");
		}
		return "";
	}

	function validateAdvancedFields(focusFirst) {
		var meta = window.checkflowCheckout && checkflowCheckout.fieldMeta ? checkflowCheckout.fieldMeta : {};
		var firstInvalid = null;
		var messages = [];
		Object.keys(meta).forEach(function (key) {
			var config = meta[key] || {};
			var control = findFieldControl(key);
			if (!control) return;
			var shipDifferent = document.querySelector('input[name="ship_to_different_address"]');
			if (config.group === "shipping" && shipDifferent && !shipDifferent.checked) {
				clearFieldError(control);
				return;
			}
			if (fieldIsConditionHidden(control)) {
				clearFieldError(control);
				return;
			}
			var value = control.type === "checkbox" ? (control.checked ? "1" : "") : control.value;
			var message = validateConfiguredValue(value, config);
			if (message) {
				setFieldError(control, message);
				messages.push(message);
				if (!firstInvalid) {
					firstInvalid = control;
				}
			} else {
				clearFieldError(control);
			}
		});
		if (firstInvalid && focusFirst) {
			setError(messages[0] || "Please check the highlighted checkout fields.");
			firstInvalid.scrollIntoView({ behavior: "smooth", block: "center" });
			window.setTimeout(function () {
				firstInvalid.focus({ preventScroll: true });
			}, 250);
		}
		return !firstInvalid;
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
			if (!control.getAttribute("data-checkflow-validation-bound")) {
				control.setAttribute("data-checkflow-validation-bound", "1");
				control.addEventListener("input", function () {
					validateAdvancedFields(false);
				});
				control.addEventListener("change", function () {
					validateAdvancedFields(false);
				});
			}
		});
		applyConditionalFields();
	}

	function checkoutValueForSource(source, condition) {
		if (source === "payment_method") {
			var checkedPayment = document.querySelector('input[name="payment_method"]:checked');
			return checkedPayment ? checkedPayment.value : "";
		}
		if (source === "billing_country" || source === "shipping_country") {
			var country = findFieldControl(source);
			return country ? country.value : "";
		}
		if (source === "field") {
			var field = condition && condition.field ? findFieldControl(condition.field) : null;
			if (!field) return "";
			return field.type === "checkbox" ? (field.checked ? "1" : "") : field.value;
		}
		var cart = window.checkflowCheckout && checkflowCheckout.cartContext ? checkflowCheckout.cartContext : {};
		if (source === "cart_total") {
			return String(cart.total || 0);
		}
		if (source === "product_id") {
			return (cart.productIds || []).join(",");
		}
		if (source === "category_id") {
			return (cart.categoryIds || []).join(",");
		}
		return "";
	}

	function compareCondition(actual, expected, operator) {
		actual = String(actual || "").trim();
		expected = String(expected || "").trim();
		if (operator === "checked") {
			return ["1", "yes", "true", "on"].indexOf(actual.toLowerCase()) !== -1;
		}
		if (operator === "not_checked") {
			return ["1", "yes", "true", "on"].indexOf(actual.toLowerCase()) === -1;
		}
		if (operator === "greater_equal") {
			return isFinite(Number(actual)) && isFinite(Number(expected)) && Number(actual) >= Number(expected);
		}
		if (operator === "less_equal") {
			return isFinite(Number(actual)) && isFinite(Number(expected)) && Number(actual) <= Number(expected);
		}
		if (operator === "contains") {
			return actual.split(",").map(function (item) { return item.trim(); }).indexOf(expected) !== -1 || actual.toLowerCase().indexOf(expected.toLowerCase()) !== -1;
		}
		if (operator === "not_equals") {
			return actual.toLowerCase() !== expected.toLowerCase();
		}
		return actual.toLowerCase() === expected.toLowerCase();
	}

	function conditionVisible(config) {
		var condition = config && config.condition ? config.condition : {};
		if (!condition.enabled) {
			return true;
		}
		var matched = compareCondition(checkoutValueForSource(condition.source, condition), condition.value, condition.operator || "equals");
		return condition.action === "hide" ? !matched : matched;
	}

	function applyConditionalFields() {
		var meta = window.checkflowCheckout && checkflowCheckout.fieldMeta ? checkflowCheckout.fieldMeta : {};
		Object.keys(meta).forEach(function (key) {
			var config = meta[key] || {};
			var control = findFieldControl(key);
			if (!control) return;
			var wrapper = fieldWrapper(control);
			if (!wrapper) return;
			var visible = conditionVisible(config);
			wrapper.classList.toggle("checkflow-field-conditional-hidden", !visible);
			control.disabled = !visible;
			if (!visible) {
				clearFieldError(control);
			}
		});
	}

	function bindConditionalRefresh() {
		if (document.documentElement.getAttribute("data-checkflow-conditions-bound")) return;
		document.documentElement.setAttribute("data-checkflow-conditions-bound", "1");
		document.addEventListener("change", function (event) {
			if (event.target && event.target.matches && event.target.matches("input, select, textarea")) {
				window.setTimeout(applyConditionalFields, 20);
			}
		});
		document.addEventListener("input", function (event) {
			if (event.target && event.target.matches && event.target.matches("input, select, textarea")) {
				window.setTimeout(applyConditionalFields, 20);
			}
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
		placeOrder.addEventListener("click", function (event) {
			syncCanonicalCheckoutFields();
			if (!validateAdvancedFields(true)) {
				event.preventDefault();
				event.stopPropagation();
				setBusy(false);
				return false;
			}
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
				ensureQuickModulesPlacement();
				refreshOrderBumpRules();
				applyAdvancedFieldSettings();
				syncCanonicalCheckoutFields();
				repairCheckoutMojibake(checkoutRoot() || document.body);
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
		refreshOrderBumpRules();
		initCountdowns();
		applyAdvancedFieldSettings();
		syncCanonicalCheckoutFields();
		repairCheckoutMojibake(form);
		bindConditionalRefresh();
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
		refreshOrderBumpRules();
		initCountdowns();
		enhancePlaceOrder(root);
		applyAdvancedFieldSettings();
		syncCanonicalCheckoutFields();
		repairCheckoutMojibake(root);
		bindConditionalRefresh();
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
				refreshOrderBumpRules();
				initCountdowns();
				applyAdvancedFieldSettings();
				syncCanonicalCheckoutFields();
				repairCheckoutMojibake(checkoutRoot() || document.body);
			}, 250);
			window.setTimeout(function () {
				ensureTrustBadgesPlacement();
				ensureQuickModulesPlacement();
				refreshOrderBumpRules();
				initCountdowns();
				applyAdvancedFieldSettings();
				syncCanonicalCheckoutFields();
				repairCheckoutMojibake(checkoutRoot() || document.body);
			}, 1000);
			return;
		}
		initClassic();
		window.setTimeout(function () {
			ensureTrustBadgesPlacement();
			ensureQuickModulesPlacement();
			refreshOrderBumpRules();
			initCountdowns();
			applyAdvancedFieldSettings();
			syncCanonicalCheckoutFields();
			repairCheckoutMojibake(checkoutRoot() || document.body);
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
	document.addEventListener("change", function (event) {
		if (event.target && event.target.matches && event.target.matches('input[name="payment_method"], [name="billing_country"], [name="shipping_country"], #billing_country, #shipping_country')) {
			window.setTimeout(refreshOrderBumpRules, 20);
		}
	});
	document.addEventListener(
		"submit",
		function (event) {
			if (event.target && event.target.matches && event.target.matches("form.checkout")) {
				syncCanonicalCheckoutFields();
			}
		},
		true
	);

	window.checkflowCheckoutApp = window.checkflowCheckoutApp || {};
	window.checkflowCheckoutApp.goToStep = goToStep;
	window.checkflowCheckoutApp.setBusy = setBusy;
	window.checkflowCheckoutApp.setError = setError;
	window.checkflowCheckoutApp.state = state;
})();
