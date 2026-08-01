(function () {
	'use strict';

	function initBackForwardCacheGuard() {
		window.addEventListener('pageshow', function (event) {
			if (!event.persisted) {
				return;
			}
			if (!document.body || !document.body.classList.contains('admin-app')) {
				return;
			}
			window.location.reload();
		});
	}

	initBackForwardCacheGuard();

	function initLogout() {
		var logout = document.getElementById('logout');
		if (!logout) {
			return;
		}
		logout.addEventListener('click', function (e) {
			var target = logout.getAttribute('href');
			if (!target || target === '#' || target.indexOf('javascript:') === 0) {
				return;
			}
			e.preventDefault();
			window.location.href = target;
		});
	}

	function initSidebarAutoClose() {
		var sidebar = document.getElementById('adminSidebar');
		if (!sidebar || typeof bootstrap === 'undefined') {
			return;
		}
		sidebar.querySelectorAll('.nav-link').forEach(function (link) {
			link.addEventListener('click', function () {
				if (window.innerWidth < 992) {
					var instance = bootstrap.Offcanvas.getInstance(sidebar);
					if (instance) {
						instance.hide();
					}
				}
			});
		});
	}

	function dismissAdminAlert(alert) {
		if (!alert || !alert.parentNode || alert.getAttribute('data-admin-dismissed') === '1') {
			return;
		}
		alert.setAttribute('data-admin-dismissed', '1');
		alert.classList.remove('show');
		window.setTimeout(function () {
			if (alert.parentNode) {
				alert.remove();
			}
		}, 350);
	}

	function initFlashAutoDismiss() {
		document.querySelectorAll('.admin-flash-stack .alert').forEach(function (alert) {
			if (alert.getAttribute('data-admin-autodismiss-init') === '1') {
				return;
			}
			alert.setAttribute('data-admin-autodismiss-init', '1');

			var ms = parseInt(alert.getAttribute('data-admin-auto-dismiss'), 10);
			if (isNaN(ms) || ms < 1) {
				ms = 5000;
			}
			window.setTimeout(function () {
				dismissAdminAlert(alert);
			}, ms);
		});
	}

	function initAlertDismiss() {
		document.querySelectorAll('[data-admin-dismiss="alert"]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var alert = btn.closest('.alert');
				if (alert) {
					dismissAdminAlert(alert);
				}
			});
		});
	}

	function initDarkModeToggle() {
		var toggle = document.getElementById('adminThemeToggle');
		var body = document.body;
		if (!toggle || !body) {
			return;
		}

		var THEME_KEY = 'webshop-admin-theme';
		var icon = toggle.querySelector('[data-theme-icon]');

		function getStoredDark() {
			try {
				return localStorage.getItem(THEME_KEY) === 'dark';
			} catch (e) {
				return false;
			}
		}

		function applyAdminTheme(isDark) {
			body.classList.toggle('admin-dark', isDark);
			body.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
			if (icon) {
				icon.className = isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
			}
			toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
			toggle.setAttribute('title', isDark ? 'Bật giao diện sáng' : 'Bật dark mode');
			try {
				localStorage.setItem(THEME_KEY, isDark ? 'dark' : 'light');
			} catch (e) {}
		}

		applyAdminTheme(body.classList.contains('admin-dark') || getStoredDark());

		toggle.addEventListener('click', function () {
			applyAdminTheme(!body.classList.contains('admin-dark'));
		});
	}

	function initTableSearch() {
		document.querySelectorAll('[data-admin-table-search]').forEach(function (input) {
			var targetId = input.getAttribute('data-admin-table-search');
			var table = document.getElementById(targetId);
			if (!table) {
				return;
			}
			var rows = table.querySelectorAll('tbody tr');
			input.addEventListener('input', function () {
				var q = input.value.trim().toLowerCase();
				rows.forEach(function (row) {
					var text = row.textContent.toLowerCase();
					row.style.display = !q || text.indexOf(q) !== -1 ? '' : 'none';
				});
			});
		});
	}

	function updateAdminOrdersBadge(count) {
		var link = document.getElementById('admin-orders-nav-link');
		if (!link) {
			return;
		}

		count = parseInt(count, 10);
		if (isNaN(count) || count < 0) {
			count = 0;
		}

		var badge = document.getElementById('admin-pending-orders-badge');
		var label = count > 99 ? '99+' : String(count);

		if (count <= 0) {
			if (badge) {
				badge.remove();
			}
			return;
		}

		if (!badge) {
			badge = document.createElement('span');
			badge.id = 'admin-pending-orders-badge';
			badge.className = 'admin-nav-badge admin-nav-badge--urgent';
			badge.title = 'Đơn chưa hoàn thành';
			link.appendChild(badge);
		}

		badge.textContent = label;
	}

	function initAdminOrdersPoll() {
		var pollUrl = window.WEBSHOP_ADMIN_ORDERS_POLL_URL;
		if (!pollUrl) {
			return;
		}

		var POLL_MS = 8000;
		var timer = null;
		var inFlight = false;

		function fetchCount() {
			if (inFlight || document.hidden) {
				return;
			}
			inFlight = true;

			fetch(pollUrl, {
				method: 'GET',
				credentials: 'same-origin',
				headers: { 'Accept': 'application/json' }
			})
				.then(function (res) {
					if (!res.ok) {
						throw new Error('poll failed');
					}
					return res.json();
				})
				.then(function (data) {
					if (data && data.ok) {
						updateAdminOrdersBadge(data.count);
					}
				})
				.catch(function () {})
				.finally(function () {
					inFlight = false;
				});
		}

		function schedulePoll() {
			clearInterval(timer);
			timer = setInterval(fetchCount, POLL_MS);
		}

		fetchCount();
		schedulePoll();

		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) {
				fetchCount();
			}
		});
	}

	function initAutoFilterForms() {
		var forms = document.querySelectorAll('main.admin-main form[method="get"]:not([data-admin-no-auto-filter])');
		forms.forEach(function (form) {
			if (form.getAttribute('data-admin-auto-filter-init') === '1') {
				return;
			}
			form.setAttribute('data-admin-auto-filter-init', '1');

			var delay = parseInt(form.getAttribute('data-admin-auto-filter-delay'), 10);
			if (isNaN(delay) || delay < 0) {
				delay = 400;
			}

			var debounceTimer = null;

			function submitForm() {
				form.submit();
			}

			function debouncedSubmit() {
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(submitForm, delay);
			}

			form.querySelectorAll('select').forEach(function (el) {
				el.addEventListener('change', submitForm);
			});

			form.querySelectorAll('input[type="date"], input[type="datetime-local"]').forEach(function (el) {
				el.addEventListener('change', submitForm);
			});

			form.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(function (el) {
				el.addEventListener('change', submitForm);
			});

			form.querySelectorAll('input[type="search"], input[type="text"], input[type="number"]').forEach(function (el) {
				if (!el.name) {
					return;
				}
				el.addEventListener('input', debouncedSubmit);
				el.addEventListener('keydown', function (e) {
					if (e.key === 'Enter') {
						e.preventDefault();
						clearTimeout(debounceTimer);
						submitForm();
					}
				});
			});

			form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
				if (btn.closest('noscript') || btn.getAttribute('data-admin-keep-submit') === '1') {
					return;
				}
				btn.classList.add('d-none');
				btn.setAttribute('aria-hidden', 'true');
				btn.tabIndex = -1;
			});
		});
	}

	function parseInputMin(input) {
		var minAttr = input.getAttribute('min');
		if (minAttr === null || minAttr === '') {
			var dataMin = input.getAttribute('data-non-negative-min');
			if (dataMin !== null && dataMin !== '') {
				minAttr = dataMin;
			}
		}
		var minVal = parseFloat(minAttr);
		return isNaN(minVal) ? 0 : minVal;
	}

	function clampNonNegativeValue(input) {
		if (!input || input.value === '' || input.value === '-') {
			if (input && input.value === '-') {
				input.value = '';
			}
			return;
		}
		var minVal = parseInputMin(input);
		var raw = String(input.value).trim();
		var parsed = input.type === 'number' ? parseFloat(raw) : parseFloat(raw.replace(/[^\d.-]/g, ''));
		if (isNaN(parsed) || parsed < minVal) {
			input.value = String(minVal);
		}
	}

	function bindNonNegativeInput(input) {
		if (!input || input.getAttribute('data-non-negative-init') === '1') {
			return;
		}
		input.setAttribute('data-non-negative-init', '1');

		input.addEventListener('keydown', function (e) {
			if (e.key === '-' || e.key === 'Minus' || e.key === 'e' || e.key === 'E') {
				e.preventDefault();
			}
		});

		input.addEventListener('paste', function (e) {
			var text = (e.clipboardData || window.clipboardData).getData('text');
			if (/^-/.test(text)) {
				e.preventDefault();
				return;
			}
			var minVal = parseInputMin(input);
			var pasted = parseFloat(String(text).replace(/[^\d.-]/g, ''));
			if (!isNaN(pasted) && pasted < minVal) {
				e.preventDefault();
				input.value = String(minVal);
				input.dispatchEvent(new Event('input', { bubbles: true }));
			}
		});

		input.addEventListener('input', function () {
			if (String(input.value).indexOf('-') !== -1) {
				input.value = String(input.value).replace(/-/g, '');
			}
			clampNonNegativeValue(input);
		});

		input.addEventListener('blur', function () {
			clampNonNegativeValue(input);
		});
	}

	function initNonNegativeInputs(root) {
		var scope = root && root.querySelectorAll ? root : document;
		scope.querySelectorAll('input[type="number"][min], input[data-non-negative]').forEach(bindNonNegativeInput);
	}

	window.adminBindNonNegativeInput = bindNonNegativeInput;
	window.adminInitNonNegativeInputs = initNonNegativeInputs;

	window.adminPromptPost = function (formId, promptText) {
		var reason = window.prompt(promptText);
		if (reason === null || String(reason).trim() === '') {
			return;
		}
		var form = document.getElementById(formId);
		if (!form) {
			return;
		}
		var input = form.querySelector('input[name="reason"]');
		if (input) {
			input.value = reason;
		}
		form.submit();
	};

	document.addEventListener('DOMContentLoaded', function () {
		initLogout();
		initSidebarAutoClose();
		initAlertDismiss();
		initFlashAutoDismiss();
		initDarkModeToggle();
		initTableSearch();
		initAutoFilterForms();
		initAdminOrdersPoll();
		initNonNegativeInputs(document);

		if (window.jQuery && jQuery.fn.datepicker) {
			jQuery('#calendar').datepicker();
		}
	});

	window.addEventListener('load', function () {
		initFlashAutoDismiss();
	});
})();
