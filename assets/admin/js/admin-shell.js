(function () {
	'use strict';

	var button = document.getElementById('adminSidebarToggle');
	var sidebar = document.getElementById('adminSidebar');
	if (!button || !sidebar) {
		return;
	}

	button.addEventListener('click', function () {
		if (window.matchMedia('(min-width: 992px)').matches) {
			var collapsed = document.body.classList.toggle('admin-sidebar-collapsed');
			try {
				localStorage.setItem('webshop-admin-sidebar', collapsed ? 'collapsed' : 'open');
			} catch (e) {}
			button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
			return;
		}

		if (window.bootstrap && window.bootstrap.Offcanvas) {
			window.bootstrap.Offcanvas.getOrCreateInstance(sidebar).toggle();
		}
	});
})();
