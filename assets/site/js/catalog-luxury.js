/**
 * Catalog — auto-submit filter + AJAX pagination (no full-page flash)
 */
(function ($) {
	'use strict';

	if (!$) {
		return;
	}

	$(function () {
		var $root = $('.jm-catalog-lux');
		if (!$root.length) {
			return;
		}

		var formEl = document.getElementById('filter-form');
		var $results = $('#jm-catalog-results');
		var submitLock = false;
		var pageLock = false;

		function beginLoading() {
			$root.addClass('is-filter-loading');
		}

		function beginPageLoading() {
			$root.addClass('is-page-loading');
		}

		function endPageLoading() {
			$root.removeClass('is-page-loading');
		}

		function submitFilterOnce() {
			if (submitLock) {
				return;
			}
			submitLock = true;
			beginLoading();
			window.requestAnimationFrame(function () {
				formEl.submit();
			});
		}

		function scrollToResults() {
			var el = $root.find('.jm-product-content')[0];
			if (!el) {
				return;
			}
			var stickyTop = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--wf-chrome-offset'), 10) || 120;
			var top = el.getBoundingClientRect().top + window.pageYOffset - stickyTop - 12;
			window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
		}

		function loadCatalogPage(url, pushHistory) {
			if (pageLock || !$results.length) {
				return;
			}

			pageLock = true;
			beginPageLoading();

			fetch(url, {
				method: 'GET',
				credentials: 'same-origin',
				headers: {
					'X-Requested-With': 'XMLHttpRequest',
					Accept: 'text/html'
				}
			})
				.then(function (response) {
					if (!response.ok) {
						throw new Error('HTTP ' + response.status);
					}
					return response.text();
				})
				.then(function (html) {
					var doc = new DOMParser().parseFromString(html, 'text/html');
					var fresh = doc.getElementById('jm-catalog-results');
					if (!fresh) {
						window.location.href = url;
						return;
					}

					$results.css('opacity', 0);
					window.requestAnimationFrame(function () {
						$results.html(fresh.innerHTML);
						if (pushHistory !== false) {
							window.history.pushState({ catalogPage: url }, '', url);
						}
						$results.css('opacity', 1);
						scrollToResults();
					});
				})
				.catch(function () {
					window.location.href = url;
				})
				.finally(function () {
					endPageLoading();
					pageLock = false;
					if (typeof window.jmCatalogCloseFilters === 'function') {
						window.jmCatalogCloseFilters();
					}
				});
		}

		if (formEl) {
			$root.find('.jm-size-label').each(function () {
				var input = this.querySelector('input[type="checkbox"]');
				if (input) {
					$(this).toggleClass('active', input.checked);
				}
			});

			$root.find('.jm-color-label').each(function () {
				var input = this.querySelector('input[type="checkbox"]');
				if (input) {
					$(this).toggleClass('active', input.checked);
				}
			});

			$(formEl).on('change', 'input[name="size[]"]', function () {
				$(this).closest('.jm-size-label').toggleClass('active', this.checked);
				submitFilterOnce();
			});

			$(formEl).on('change', 'input[name="color[]"]', function () {
				$(this).closest('.jm-color-label').toggleClass('active', this.checked);
				submitFilterOnce();
			});

			$(formEl).on('change', 'input[name="category[]"]', function () {
				submitFilterOnce();
			});

			$(formEl).on('change', 'input[name="price_range"]', function () {
				submitFilterOnce();
			});
		}

		$root.find('.jm-sort-box form').on('submit', function () {
			beginLoading();
		});

		$root.on('click', '.jm-catalog-pagination a[href]', function (event) {
			var href = this.getAttribute('href');
			if (!href || href.charAt(0) === '#') {
				return;
			}

			try {
				var target = new URL(href, window.location.origin);
				if (target.origin !== window.location.origin) {
					return;
				}
			} catch (e) {
				return;
			}

			event.preventDefault();
			loadCatalogPage(href, true);
		});

		window.addEventListener('popstate', function () {
			loadCatalogPage(window.location.href, false);
		});

		initMobileCatalogFilters($root, formEl);
	});

	function initMobileCatalogFilters($root, formEl) {
		var mobileMq = window.matchMedia('(max-width: 991px)');
		var $bar = $('#jmCatalogMobileBar');
		var $backdrop = $('#jmCatalogMfilterBackdrop');
		if (!$bar.length) {
			return;
		}

		var $sortSelect = $('#jmCatalogSortSelect');
		var $sortForm = $root.find('.jm-sort-box--toolbar form');
		var $sortDrop = $bar.find('[data-jm-mfilter-drop="sort"]');
		var $sortMenu = $sortDrop.find('.jm-sort-menu');

		var openPanel = null;

		function isMobile() {
			return mobileMq.matches;
		}

		function syncSortMenu() {
			if (!$sortSelect.length || !$sortMenu.length) {
				return;
			}
			var sortVal = $sortSelect.val();
			$sortMenu.find('.jm-sort-menu__option').each(function () {
				var active = this.getAttribute('data-sort-value') === sortVal;
				$(this).toggleClass('is-active', active).attr('aria-selected', active ? 'true' : 'false');
			});
			$bar.find('[data-jm-mfilter="sort"]').toggleClass('is-active', sortVal && sortVal !== 'new' && sortVal !== 'hot');
		}

		function closePanels() {
			openPanel = null;
			$bar.find('.jm-mfilter-chip').removeClass('is-open').attr('aria-expanded', 'false');
			$bar.find('.jm-mfilter-drop').attr('hidden', 'hidden');
			$backdrop.attr('hidden', 'hidden').attr('aria-hidden', 'true');
			$root.removeClass('jm-catalog-lux--drawer-open');
		}

		window.jmCatalogCloseFilters = closePanels;

		function openDrawer() {
			if (typeof window.wfChromeCloseNav === 'function') {
				window.wfChromeCloseNav();
			}
			closePanels();
			$root.addClass('jm-catalog-lux--drawer-open');
			$backdrop.removeAttr('hidden').attr('aria-hidden', 'false');
			$bar.find('[data-jm-mfilter="all"]').addClass('is-open').attr('aria-expanded', 'true');
			openPanel = 'all';
		}

		function openSortDrop() {
			if (openPanel === 'sort') {
				closePanels();
				return;
			}

			closePanels();
			openPanel = 'sort';
			syncSortMenu();
			$bar.find('[data-jm-mfilter="sort"]').addClass('is-open').attr('aria-expanded', 'true');
			$sortDrop.removeAttr('hidden');
		}

		function applySort(value) {
			if (!$sortSelect.length || !$sortForm.length) {
				return;
			}
			$sortSelect.val(value);
			syncSortMenu();
			closePanels();
			$sortForm.trigger('submit');
		}

		function applyLayout() {
			closePanels();
			syncSortMenu();
		}

		$bar.on('click', function (e) {
			e.stopPropagation();
		});

		$bar.on('click', '.jm-mfilter-chip', function () {
			if (!isMobile()) {
				return;
			}
			var panel = this.getAttribute('data-jm-mfilter');
			if (panel === 'all') {
				if ($root.hasClass('jm-catalog-lux--drawer-open')) {
					closePanels();
				} else {
					openDrawer();
				}
				return;
			}
			if (panel === 'sort') {
				openSortDrop();
			}
		});

		$sortDrop.on('click', '.jm-sort-menu__option', function () {
			if (!isMobile()) {
				return;
			}
			var value = this.getAttribute('data-sort-value');
			if (!value) {
				return;
			}
			applySort(value);
		});

		$(document).on('click.jmCatalogMfilter', function () {
			if (!isMobile() || openPanel !== 'sort') {
				return;
			}
			closePanels();
		});

		$backdrop.on('click', function () {
			closePanels();
		});

		window.addEventListener('pagehide', closePanels);
		window.addEventListener('pageshow', closePanels);

		$root.on('click', 'a[href]', function () {
			var href = this.getAttribute('href');
			if (!href || href.charAt(0) === '#') {
				return;
			}
			closePanels();
		});

		if (formEl) {
			$(formEl).on('change', 'input, select', syncSortMenu);
		}

		if (mobileMq.addEventListener) {
			mobileMq.addEventListener('change', applyLayout);
		} else if (mobileMq.addListener) {
			mobileMq.addListener(applyLayout);
		}

		applyLayout();
	}
})(window.jQuery);
