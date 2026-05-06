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
	}

	function persistLocale(locale) {
		if (!window.checkflowAdmin || !checkflowAdmin.ajaxUrl) {
			return;
		}
		$.ajax({
			url: checkflowAdmin.ajaxUrl,
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
		if (!window.checkflowAdmin || !checkflowAdmin.ajaxUrl) return;
		var $btn = $(btnEl);
		$btn.prop("disabled", true);
		$.ajax({
			url: checkflowAdmin.ajaxUrl,
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
		renderMiniChart();

		$(document).on("click", ".tab", function () {
			$(this).siblings(".tab").removeClass("on");
			$(this).addClass("on");
		});

		$(document).on("click", ".tgl", function () {
			$(this).toggleClass("on");
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
