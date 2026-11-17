(function () {
	'use strict';

	var AUTO_MS = 3000;
	var SELECTOR =
		'.jm-store-flash, .jm-cart-flash, .jm-auth-alert-success, .jm-auth-alert-error, .jm-auth-alert';

	function dismissAlert(el) {
		if (!el || !el.parentNode) return;
		if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
			try {
				bootstrap.Alert.getOrCreateInstance(el).close();
				return;
			} catch (e) {
				/* fall through */
			}
		}
		el.style.transition = 'opacity 0.35s ease';
		el.style.opacity = '0';
		window.setTimeout(function () {
			if (el.parentNode) el.remove();
		}, 350);
	}

	function scheduleDismiss(el) {
		if (!el || el.hidden) return;
		if (el.getAttribute('data-auto-dismiss') === 'off') return;
		if (el.getAttribute('data-auto-dismiss-scheduled') === '1') return;
		el.setAttribute('data-auto-dismiss-scheduled', '1');
		window.setTimeout(function () {
			dismissAlert(el);
		}, AUTO_MS);
	}

	function initIn(root) {
		root = root || document;
		root.querySelectorAll(SELECTOR).forEach(scheduleDismiss);
	}

	document.addEventListener('DOMContentLoaded', function () {
		initIn();
	});

	window.jmScheduleFlashDismiss = scheduleDismiss;
	window.jmInitFlashAutoDismiss = initIn;
})();