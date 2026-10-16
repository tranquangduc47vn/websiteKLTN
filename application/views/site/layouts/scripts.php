<?php
$site_asset_url = site_asset_url('');
?>
<link rel="stylesheet" type="text/css" href="<?php echo $site_asset_url; ?>css/responsive-2026.css?v=16">
<?php echo csrf_form_inject_script(); ?>
<script src="<?php echo $site_asset_url; ?>bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo $site_asset_url; ?>js/header-icons.js"></script>
<script>
(function () {
	var header = document.getElementById('wfChromeHeader');
	var shell = header && header.closest('.wf-chrome-shell');
	var spacer = document.getElementById('wfChromeHeaderSpacer');
	var navToggle = document.getElementById('wfChromeNavToggle');
	var navDrawer = document.getElementById('wfChromeNavDrawer');
	var rootChrome = document.querySelector('.wf-chrome-2026');

	function syncSpacer() {
		if (!shell || !spacer) return;
		spacer.style.setProperty('--wf-chrome-offset', shell.offsetHeight + 'px');
	}

	if (shell) {
		var onScroll = function () {
			shell.classList.toggle('is-scrolled', window.scrollY > 8);
			var sliderSearch = document.querySelector('.slider-search-box');
			var carousel = document.getElementById('carousel-example-generic');
			if (sliderSearch && carousel) {
				var headerH = shell.offsetHeight || 120;
				var pastHero = carousel.getBoundingClientRect().bottom < headerH + 16;
				sliderSearch.classList.toggle('is-hidden-by-scroll', pastHero);
			}
		};
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
		window.addEventListener('resize', syncSpacer);
		syncSpacer();
	}

	if (navToggle && navDrawer) {
		var mobileNavMq = window.matchMedia('(max-width: 991px)');

		function setNavOpen(open) {
			if (!mobileNavMq.matches) {
				open = false;
			}
			navDrawer.classList.toggle('is-open', open);
			navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (rootChrome) rootChrome.classList.toggle('wf-chrome-nav-backdrop', open);
			if (open && typeof window.jmCatalogCloseFilters === 'function') {
				window.jmCatalogCloseFilters();
			}
		}

		window.wfChromeCloseNav = function () {
			setNavOpen(false);
		};

		navToggle.addEventListener('click', function () {
			if (!mobileNavMq.matches) return;
			setNavOpen(!navDrawer.classList.contains('is-open'));
		});

		document.addEventListener('click', function (e) {
			if (!mobileNavMq.matches || !navDrawer.classList.contains('is-open')) return;
			if (navDrawer.contains(e.target) || navToggle.contains(e.target)) return;
			setNavOpen(false);
		});

		navDrawer.querySelectorAll('a[href]').forEach(function (link) {
			link.addEventListener('click', function () {
				if (mobileNavMq.matches) setNavOpen(false);
			});
		});

		function clearDesktopSubmenuState() {
			if (mobileNavMq.matches) return;
			setNavOpen(false);
			navDrawer.querySelectorAll('.wf-chrome-menu__item--has-sub.is-open').forEach(function (item) {
				item.classList.remove('is-open');
				var t = item.querySelector('.wf-chrome-menu__trigger');
				if (t) t.setAttribute('aria-expanded', 'false');
			});
		}

		clearDesktopSubmenuState();
		if (!mobileNavMq.matches) {
			setNavOpen(false);
		}
		if (mobileNavMq.addEventListener) {
			mobileNavMq.addEventListener('change', clearDesktopSubmenuState);
		} else if (mobileNavMq.addListener) {
			mobileNavMq.addListener(clearDesktopSubmenuState);
		}

		navDrawer.querySelectorAll('.wf-chrome-menu__trigger').forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!mobileNavMq.matches) return;
				var item = btn.closest('.wf-chrome-menu__item--has-sub');
				if (!item) return;
				item.classList.toggle('is-open');
				btn.setAttribute('aria-expanded', item.classList.contains('is-open') ? 'true' : 'false');
			});
		});

		window.addEventListener('pagehide', function () {
			setNavOpen(false);
		});
		window.addEventListener('pageshow', function () {
			setNavOpen(false);
		});
	}

	window.addEventListener('pagehide', function () {
		if (typeof window.jmCatalogCloseFilters === 'function') {
			window.jmCatalogCloseFilters();
		}
	});
	window.addEventListener('pageshow', function () {
		if (typeof window.jmCatalogCloseFilters === 'function') {
			window.jmCatalogCloseFilters();
		}
	});
})();
</script>
