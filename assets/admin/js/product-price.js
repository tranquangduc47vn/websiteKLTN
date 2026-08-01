(function () {
	'use strict';

	function parseDigits(value) {
		return parseInt(String(value || '').replace(/\D/g, ''), 10) || 0;
	}

	function formatMoney(value) {
		var digits = String(value || '').replace(/\D/g, '');
		return digits ? Number(digits).toLocaleString('en-US') : '';
	}

	function bindMoneyInput(input) {
		if (!input || input.getAttribute('data-money-input') === '1') {
			return;
		}
		input.setAttribute('data-money-input', '1');
		input.setAttribute('data-non-negative', '1');
		input.setAttribute('data-non-negative-min', '0');
		if (typeof window.adminBindNonNegativeInput === 'function') {
			window.adminBindNonNegativeInput(input);
		}
		input.addEventListener('input', function () {
			var pos = input.selectionStart;
			var before = input.value.length;
			input.value = formatMoney(input.value);
			var after = input.value.length;
			if (pos !== null) {
				input.setSelectionRange(Math.max(0, pos + (after - before)), Math.max(0, pos + (after - before)));
			}
		});
		input.addEventListener('keydown', function (e) {
			if (e.key === '-' || e.key === 'Minus') {
				e.preventDefault();
			}
		});
	}

	function initDiscountField(form) {
		var priceInput = form.querySelector('#price');
		var discountInput = form.querySelector('#discount');
		var preview = form.querySelector('[data-discount-preview]');

		if (!priceInput || !discountInput) {
			return;
		}

		function updatePreview() {
			if (!preview) {
				return;
			}
			var price = parseDigits(priceInput.value);
			var pct = parseDigits(discountInput.value);
			if (!price || !pct) {
				preview.textContent = '';
				return;
			}
			pct = Math.min(100, pct);
			var off = Math.round(price * pct / 100);
			var finalPrice = Math.max(0, price - off);
			preview.textContent = 'Giá sau giảm: ' + finalPrice.toLocaleString('en-US') + ' ₫ (giảm ' + pct + '%, tương đương ' + off.toLocaleString('en-US') + ' ₫)';
		}

		discountInput.addEventListener('input', function () {
			var digits = discountInput.value.replace(/\D/g, '');
			if (digits === '') {
				discountInput.value = '';
			} else {
				discountInput.value = String(Math.min(100, parseInt(digits, 10)));
			}
			updatePreview();
		});

		priceInput.addEventListener('input', updatePreview);
		updatePreview();
	}

	function initProductPriceForm(form) {
		if (!form || form.getAttribute('data-product-price-init') === '1') {
			return;
		}
		form.setAttribute('data-product-price-init', '1');
		bindMoneyInput(form.querySelector('#price'));
		initDiscountField(form);

		form.addEventListener('submit', function (e) {
			var priceInput = form.querySelector('#price');
			if (!priceInput) {
				return;
			}
			var price = parseDigits(priceInput.value);
			if (price < 0) {
				e.preventDefault();
				alert('Giá sản phẩm không được âm.');
				priceInput.focus();
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('form.admin-form, form.admin-product-form, form[data-product-price-form]').forEach(initProductPriceForm);
	});
})();
