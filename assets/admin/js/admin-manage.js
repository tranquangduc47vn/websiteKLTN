(function () {
	'use strict';

	function confirmModal(message, onYes) {
		var modalEl = document.getElementById('admConfirmModal');
		if (!modalEl || !window.bootstrap || !window.bootstrap.Modal) {
			if (window.confirm(message)) {
				onYes();
			}
			return;
		}
		var text = modalEl.querySelector('[data-adm-confirm-text]');
		var yes = modalEl.querySelector('[data-adm-confirm-yes]');
		if (!text || !yes) {
			if (window.confirm(message)) {
				onYes();
			}
			return;
		}
		text.textContent = message;
		var modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
		function cleanup() {
			yes.removeEventListener('click', onClick);
			modalEl.removeEventListener('hidden.bs.modal', onHide);
		}
		function onClick() {
			cleanup();
			modal.hide();
			onYes();
		}
		function onHide() {
			cleanup();
		}
		yes.addEventListener('click', onClick);
		modalEl.addEventListener('hidden.bs.modal', onHide);
		modal.show();
	}

	window.admConfirm = confirmModal;

	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!form || !form.getAttribute) {
			return;
		}
		var message = form.getAttribute('data-adm-confirm');
		if (message && form.getAttribute('data-adm-confirmed') !== '1') {
			event.preventDefault();
			confirmModal(message, function () {
				form.setAttribute('data-adm-confirmed', '1');
				form.submit();
			});
			return;
		}
		form.classList.add('is-busy');
	});

	function bindPreview(input, preview) {
		if (!input || !preview) {
			return;
		}
		input.addEventListener('change', function () {
			var file = input.files && input.files[0];
			if (!file || file.type.indexOf('image/') !== 0) {
				return;
			}
			var img = preview.querySelector('img') || document.createElement('img');
			img.alt = '';
			img.src = URL.createObjectURL(file);
			if (!img.parentNode) {
				preview.appendChild(img);
			}
		});
	}

	var invalid = document.querySelector('.admin-main .is-invalid');
	if (invalid && typeof invalid.focus === 'function') {
		invalid.focus();
	}

	bindPreview(document.getElementById('image'), document.getElementById('admMainPreview'));
	document.querySelectorAll('input[type="file"][data-adm-preview]').forEach(function (input) {
		bindPreview(input, document.querySelector(input.getAttribute('data-adm-preview')));
	});
})();
