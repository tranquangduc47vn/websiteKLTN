(function () {
	'use strict';

	var panel = document.getElementById('admAiDescPanel');
	if (!panel) {
		return;
	}

	var url = panel.getAttribute('data-ai-url') || '';
	var productId = parseInt(panel.getAttribute('data-product-id') || '0', 10) || 0;

	var btnGenerate = document.getElementById('admAiDescGenerate');
	var btnInsert = document.getElementById('admAiDescInsert');
	var btnRegenerate = document.getElementById('admAiDescRegenerate');
	var btnDismiss = document.getElementById('admAiDescDismiss');
	var alertEl = document.getElementById('admAiDescAlert');
	var previewBox = document.getElementById('admAiDescPreview');
	var previewHtml = document.getElementById('admAiDescPreviewHtml');
	var form = panel.closest('form');
	var loading = false;

	function csrfToken() {
		if (window.CI_CSRF && window.CI_CSRF.hash) {
			return window.CI_CSRF.hash;
		}
		var input = form && form.querySelector('input[name="_token"]');
		return input ? input.value : '';
	}

	function ckeditorInstance() {
		var field = (window.PRODUCT_AI_DESC && window.PRODUCT_AI_DESC.ckeditorField) || 'content';
		if (!window.CKEDITOR || !CKEDITOR.instances[field]) {
			return null;
		}
		return CKEDITOR.instances[field];
	}

	function editorHasContent() {
		var ed = ckeditorInstance();
		if (ed) {
			var html = (ed.getData() || '').replace(/<[^>]+>/g, '').replace(/\s+/g, '').trim();
			return html.length > 0;
		}
		var ta = document.getElementById('content');
		if (!ta) {
			return false;
		}
		return ta.value.replace(/\s+/g, '').length > 0;
	}

	function setEditorHtml(html) {
		var ed = ckeditorInstance();
		if (ed) {
			ed.setData(html);
			return;
		}
		var ta = document.getElementById('content');
		if (ta) {
			ta.value = html;
		}
	}

	function showAlert(text, ok) {
		if (!alertEl) {
			return;
		}
		alertEl.textContent = text;
		alertEl.classList.remove('d-none', 'adm-ai-desc-alert--ok', 'adm-ai-desc-alert--err');
		alertEl.classList.add(ok ? 'adm-ai-desc-alert--ok' : 'adm-ai-desc-alert--err');
	}

	function hideAlert() {
		if (alertEl) {
			alertEl.classList.add('d-none');
		}
	}

	function selectedValues(name) {
		if (!form) {
			return [];
		}
		var nodes = form.querySelectorAll('[name="' + name + '"]:checked, [name="' + name + '[]"]:checked');
		var out = [];
		nodes.forEach(function (node) {
			var val = (node.value || '').trim();
			if (val) {
				out.push(val);
			}
		});
		return out;
	}

	function buildFormData() {
		var fd = new FormData();
		fd.append('_token', csrfToken());
		if (productId > 0) {
			fd.append('product_id', String(productId));
		}
		var nameEl = document.getElementById('name');
		if (nameEl && nameEl.value.trim()) {
			fd.append('name', nameEl.value.trim());
		}
		var catalogEl = document.getElementById('catalog_id');
		if (catalogEl && catalogEl.value) {
			fd.append('catalog_id', catalogEl.value);
		}
		var priceEl = document.getElementById('price');
		if (priceEl && priceEl.value.trim()) {
			fd.append('price', priceEl.value.trim());
		}
		var discountEl = document.getElementById('discount');
		if (discountEl && discountEl.value.trim()) {
			fd.append('discount', discountEl.value.trim());
		}
		selectedValues('color').forEach(function (c) {
			fd.append('color[]', c);
		});
		selectedValues('size').forEach(function (s) {
			fd.append('size[]', s);
		});
		var mainHidden = form.querySelector('input[name="product_image_main"]');
		if (mainHidden && mainHidden.value) {
			fd.append('product_image_main', mainHidden.value);
		}
		var listHidden = form.querySelector('input[name="product_image_list"]');
		if (listHidden && listHidden.value) {
			fd.append('product_image_list', listHidden.value);
		}
		var mainFile = document.getElementById('image');
		if (mainFile && mainFile.files && mainFile.files[0]) {
			fd.append('image', mainFile.files[0]);
		}
		var galleryFile = document.getElementById('list_image');
		if (galleryFile && galleryFile.files) {
			for (var i = 0; i < galleryFile.files.length && i < 3; i++) {
				fd.append('list_image[]', galleryFile.files[i]);
			}
		}
		return fd;
	}

	function setLoading(on) {
		loading = on;
		if (btnGenerate) {
			btnGenerate.disabled = on;
			btnGenerate.classList.toggle('is-loading', on);
			var label = btnGenerate.querySelector('.adm-ai-desc-generate__label');
			if (label) {
				label.textContent = on ? 'Đang tạo mô tả (có thể 1–2 phút)…' : 'Tạo mô tả tự động';
			}
		}
		if (btnRegenerate) {
			btnRegenerate.disabled = on;
		}
	}

	function requestDescription() {
		if (loading || !url) {
			return;
		}
		var nameEl = document.getElementById('name');
		if (!nameEl || !nameEl.value.trim()) {
			showAlert('Vui lòng nhập tên sản phẩm trước.', false);
			return;
		}
		setLoading(true);
		hideAlert();
		fetch(url, {
			method: 'POST',
			body: buildFormData(),
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
		})
			.then(function (res) {
				return res.json().then(function (json) {
					return { status: res.status, json: json };
				});
			})
			.then(function (payload) {
				var json = payload.json || {};
				if (window.CI_CSRF && json.csrf_hash) {
					window.CI_CSRF.hash = json.csrf_hash;
				}
				if (!json.ok) {
					showAlert(json.message || 'Không tạo được mô tả.', false);
					return;
				}
				if (previewBox) {
					previewBox.classList.remove('d-none');
				}
				if (previewHtml) {
					previewHtml.value = json.html || '';
				}
				showAlert(json.message || 'Đã tạo mô tả.', true);
			})
			.catch(function () {
				showAlert('Lỗi kết nối. Vui lòng thử lại.', false);
			})
			.finally(function () {
				setLoading(false);
			});
	}

	function insertContent() {
		if (!previewHtml || !previewHtml.value.trim()) {
			showAlert('Chưa có nội dung để chèn.', false);
			return;
		}
		var html = previewHtml.value;
		if (editorHasContent()) {
			var okReplace = window.confirm(
				'Trình soạn thảo đang có mô tả. Thay thế toàn bộ bằng nội dung AI?\n\nChọn Hủy để giữ nguyên nội dung hiện tại.'
			);
			if (!okReplace) {
				return;
			}
		}
		setEditorHtml(html);
		showAlert('Đã chèn vào mô tả. Kiểm tra lại trước khi lưu sản phẩm.', true);
	}

	if (btnGenerate) {
		btnGenerate.addEventListener('click', requestDescription);
	}
	if (btnRegenerate) {
		btnRegenerate.addEventListener('click', requestDescription);
	}
	if (btnInsert) {
		btnInsert.addEventListener('click', insertContent);
	}
	if (btnDismiss) {
		btnDismiss.addEventListener('click', function () {
			if (previewBox) {
				previewBox.classList.add('d-none');
			}
			hideAlert();
		});
	}
})();