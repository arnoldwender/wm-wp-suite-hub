/**
 * WM Suite Gutenberg blocks — front-end behaviour of the accessibility toolbar, the newsticker, the
 * Peppol format check and the external-content placeholder. Vanilla JS, no dependencies, no requests.
 *
 * Until 1.2.2 it also drove a whistleblower "wizard" that sent nothing and generated a random access
 * key in the browser; the block now shows the portal of WM HinSchG Whistleblower Desk instead.
 */

(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		initA11ySwitcher();
		initNewstickers();
		initPeppolVerifiers();
		initScriptPlaceholders();
	});

	/* ─────────────────────────────────────────────────────────────
	   1. ACCESSIBILITY SWITCHER
	───────────────────────────────────────────────────────────── */
	// localStorage throws where the browser blocks site data. Unguarded, the first read ended the
	// DOMContentLoaded handler, so the ticker, the Peppol check and the placeholder never started.
	var store = {
		get: function (key) { try { return localStorage.getItem(key); } catch (e) { return null; } },
		set: function (key, value) { try { localStorage.setItem(key, value); } catch (e) {} },
		remove: function (key) { try { localStorage.removeItem(key); } catch (e) {} }
	};

	function initA11ySwitcher() {
		var buttons = document.querySelectorAll('[data-wm-a11y-action]');
		if (!buttons.length) return;

		// Load saved preferences
		var contrast = store.get('wm_a11y_contrast');
		var dyslexic = store.get('wm_a11y_dyslexic');
		var scale = store.get('wm_a11y_scale');

		if (contrast === 'high') {
			document.documentElement.classList.add('wm-a11y-high-contrast');
			syncBtnState('toggle-contrast', true);
		}
		if (dyslexic === 'true') {
			document.documentElement.classList.add('wm-a11y-dyslexic');
			syncBtnState('toggle-dyslexic', true);
		}
		if (scale) {
			document.documentElement.classList.add('wm-a11y-scale-' + scale);
			syncBtnState('scale-' + scale, true);
		}

		buttons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var action = btn.getAttribute('data-wm-a11y-action');

				if (action === 'toggle-contrast') {
					var isHigh = document.documentElement.classList.toggle('wm-a11y-high-contrast');
					store.set('wm_a11y_contrast', isHigh ? 'high' : 'normal');
					btn.classList.toggle('is-active', isHigh);
					btn.setAttribute('aria-pressed', isHigh ? 'true' : 'false');
				} else if (action === 'toggle-dyslexic') {
					var isDys = document.documentElement.classList.toggle('wm-a11y-dyslexic');
					store.set('wm_a11y_dyslexic', isDys ? 'true' : 'false');
					btn.classList.toggle('is-active', isDys);
					btn.setAttribute('aria-pressed', isDys ? 'true' : 'false');
				} else if (action === 'scale-reset') {
					document.documentElement.classList.remove('wm-a11y-scale-120', 'wm-a11y-scale-140');
					store.remove('wm_a11y_scale');
					document.querySelectorAll('[data-wm-a11y-action^="scale-"]').forEach(function (b) {
						b.classList.remove('is-active');
						b.setAttribute('aria-pressed', 'false');
					});
				} else if (action === 'scale-120' || action === 'scale-140') {
					var scaleVal = action.replace('scale-', '');
					document.documentElement.classList.remove('wm-a11y-scale-120', 'wm-a11y-scale-140');
					document.documentElement.classList.add('wm-a11y-scale-' + scaleVal);
					store.set('wm_a11y_scale', scaleVal);
					document.querySelectorAll('[data-wm-a11y-action^="scale-"]').forEach(function (b) {
						b.classList.remove('is-active');
						b.setAttribute('aria-pressed', 'false');
					});
					btn.classList.add('is-active');
					btn.setAttribute('aria-pressed', 'true');
				}
			});
		});

		function syncBtnState(action, active) {
			var b = document.querySelector('[data-wm-a11y-action="' + action + '"]');
			if (b) {
				b.classList.toggle('is-active', active);
				b.setAttribute('aria-pressed', active ? 'true' : 'false');
			}
		}
	}

	/* ─────────────────────────────────────────────────────────────
	   2. ACCESSIBLE NEWSTICKER (PAUSE / PLAY)
	───────────────────────────────────────────────────────────── */
	function initNewstickers() {
		var tickers = document.querySelectorAll('.wm-newsticker-wrap');
		tickers.forEach(function (ticker) {
			var pauseBtn = ticker.querySelector('.wm-newsticker-pause-btn');
			if (!pauseBtn) return;

			pauseBtn.addEventListener('click', function () {
				var isPaused = ticker.classList.toggle('is-paused');
				var statusText = pauseBtn.querySelector('.wm-sr-status');
				if (statusText && window.wmSuiteData && window.wmSuiteData.i18n) {
					statusText.textContent = isPaused ? window.wmSuiteData.i18n.paused : window.wmSuiteData.i18n.playing;
				}
				pauseBtn.setAttribute('aria-pressed', isPaused ? 'true' : 'false');
			});
		});
	}

	/* ─────────────────────────────────────────────────────────────
	   3. PEPPOL ID FORMAT CHECK (form only, no participant lookup)
	───────────────────────────────────────────────────────────── */
	function initPeppolVerifiers() {
		var verifiers = document.querySelectorAll('.wm-peppol-verifier');
		verifiers.forEach(function (container) {
			var form = container.querySelector('.wm-peppol-form');
			var input = container.querySelector('.wm-peppol-input');
			var result = container.querySelector('.wm-peppol-result');
			if (!form || !input || !result) return;

			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var val = input.value.trim();

				// Regex for Peppol Scheme:Identifier (e.g. 0208:0843657437, 9958:DE123456789, 0088:...)
				var peppolRegex = /^([0-9]{4}):([A-Za-z0-9\-_.]+)$/;
				if (peppolRegex.test(val)) {
					result.className = 'wm-peppol-result wm-peppol-result--valid';
					result.textContent = (window.wmSuiteData && window.wmSuiteData.i18n ? window.wmSuiteData.i18n.validPeppol : 'Format valid (Scheme:ID)') + ': ' + val;
				} else {
					result.className = 'wm-peppol-result wm-peppol-result--invalid';
					result.textContent = window.wmSuiteData && window.wmSuiteData.i18n ? window.wmSuiteData.i18n.invalidPeppol : 'Invalid format. Use Scheme:ID (e.g. 0208:0843657437)';
				}
			});
		});
	}

	/* ─────────────────────────────────────────────────────────────
	   4. EXTERNAL CONTENT PLACEHOLDER
	───────────────────────────────────────────────────────────── */
	function initScriptPlaceholders() {
		var placeholders = document.querySelectorAll('.wm-script-placeholder');
		placeholders.forEach(function (box) {
			var btn = box.querySelector('.wm-unblock-btn');
			if (!btn) return;

			btn.addEventListener('click', function () {
				var targetId = box.getAttribute('data-unblock-target');
				var target = targetId ? document.getElementById(targetId) : null;
				if (!target) return;

				if (target.tagName === 'TEMPLATE') {
					// Nothing inside a <template> is fetched until it is inserted, so the provider
					// receives nothing before this click.
					box.parentNode.insertBefore(document.importNode(target.content, true), box);
				} else {
					// A hidden element is only shown again: an iframe in it loaded with the page.
					target.style.display = '';
				}
				box.style.display = 'none';
			});
		});
	}
})();
