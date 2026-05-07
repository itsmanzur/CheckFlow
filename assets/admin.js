(function ($) {
	"use strict";

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

		var rawHash = window.location.hash ? window.location.hash.replace(/^#\s*/, "").replace(/^cf-/, "") : "";
		if (rawHash && document.querySelector('[data-pane="' + rawHash + '"]')) {
			setPane(rawHash);
		}
	});
})(jQuery);
