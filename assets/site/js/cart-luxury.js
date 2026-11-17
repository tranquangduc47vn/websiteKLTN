(function () {
	'use strict';

	var cfg = window.JM_CART_AJAX || {};
	var busy = false;
	var interactionsEnabled = false;

	function formatMoney(n) {
		return (Number(n) || 0).toLocaleString('vi-VN') + ' ₫';
	}

	function appendCsrf(data) {
		if (typeof window.appendCsrf === 'function') {
			return window.appendCsrf(data);
		}
		return data || {};
	}

	function applyCsrfResponse(data) {
		if (typeof window.applyCsrfResponse === 'function') {
			window.applyCsrfResponse(data);
		}
	}

	function postForm(url, data) {
		return fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With': 'XMLHttpRequest',
			},
			credentials: 'same-origin',
			body: new URLSearchParams(appendCsrf(data)).toString(),
		}).then(function (res) {
			return res.json();
		}).then(function (data) {
			applyCsrfResponse(data);
			return data;
		});
	}

	function setBusy(on) {
		busy = on;
		document.body.classList.toggle('jm-cart-is-busy', on);
	}

	function updateHeaderBadge(totalItems) {
		var badge = document.querySelector('.wf-chrome-cart-badge');
		if (!badge) return;
		if (totalItems > 0) {
			badge.textContent = totalItems > 99 ? '99+' : String(totalItems);
			badge.style.display = '';
		} else {
			badge.style.display = 'none';
		}
	}

	function updateTotals(totalItems, totalPrice) {
		var countEl = document.querySelector('.jm-cart-panel__count');
		var amountEl = document.querySelector('.jm-cart-summary__amount');
		if (countEl) {
			countEl.textContent = totalItems + ' sản phẩm';
		}
		if (amountEl) {
			amountEl.textContent = formatMoney(totalPrice);
		}
		updateHeaderBadge(totalItems);
	}

	function showFlash(message, isError) {
		var flash = document.querySelector('.jm-cart-flash');
		if (!flash) {
			flash = document.createElement('div');
			flash.className = 'jm-cart-flash';
			flash.setAttribute('role', 'alert');
			var inner = document.querySelector('.jm-cart-lux__inner');
			if (inner) {
				inner.insertBefore(flash, inner.firstChild);
			}
		}
		flash.textContent = message;
		flash.classList.toggle('jm-cart-flash--error', !!isError);
		flash.hidden = !message;
		if (message) {
			flash.removeAttribute('data-auto-dismiss-scheduled');
			if (typeof window.jmScheduleFlashDismiss === 'function') {
				window.jmScheduleFlashDismiss(flash);
			}
		}
	}

	function showStockNotice(data) {
		if (window.QD && window.QD.showStockNotice && window.QD.showStockNotice(data)) {
			return;
		}
		showFlash(data.message || 'Không đủ tồn kho.', true);
	}

	function findRow(rowid) {
		return document.querySelector('.jm-cart-row[data-rowid="' + CSS.escape(rowid) + '"]');
	}

	/** Đồng bộ rowid trên dòng giỏ: data-rowid, input ẩn, nút xóa. */
	function setRowRowid(row, rowid) {
		if (!row || !rowid) return;
		row.setAttribute('data-rowid', rowid);
		var hidden = row.querySelector('input[name="rowid"]');
		if (hidden) hidden.value = rowid;
		row.querySelectorAll('.jm-cart-remove[data-rowid]').forEach(function (btn) {
			btn.setAttribute('data-rowid', rowid);
		});
	}

	function updateRowNumbers() {
		document.querySelectorAll('.jm-cart-row').forEach(function (row, idx) {
			var cell = row.querySelector('.jm-cart-col--idx');
			if (cell) cell.textContent = String(idx + 1);
		});
	}

	function applyQtyUpdate(data) {
		if (data.removed) {
			var row = findRow(data.rowid);
			if (row) row.remove();
			updateRowNumbers();
			if (document.querySelectorAll('.jm-cart-row').length === 0) {
				window.location.reload();
			}
			return;
		}
		var row = findRow(data.rowid);
		if (!row) return;
		var qtyEls = row.querySelectorAll('.jm-cart-qty__val');
		var priceEls = row.querySelectorAll('.jm-cart-line-price');
		qtyEls.forEach(function (qtyEl) {
			qtyEl.textContent = String(data.qty);
		});
		priceEls.forEach(function (priceEl) {
			priceEl.textContent = formatMoney(data.subtotal);
		});
	}

	/**
	 * @returns {{ needsReload: boolean }}
	 */
	function applyOptionsUpdate(data) {
		var newRowid = data.rowid;
		var oldRowid = data.old_rowid || newRowid;
		var sourceRow = findRow(oldRowid);

		if (!newRowid) {
			return { needsReload: false };
		}

		if (oldRowid && newRowid && oldRowid !== newRowid) {
			var existingTarget = findRow(newRowid);
			if (existingTarget && (!sourceRow || existingTarget !== sourceRow)) {
				return { needsReload: true };
			}
		}

		if (!sourceRow) {
			sourceRow = findRow(newRowid);
			if (!sourceRow) {
				return { needsReload: true };
			}
		}

		if (newRowid) {
			setRowRowid(sourceRow, newRowid);
		}

		var sizeSelect = sourceRow.querySelector('select[name="size"]');
		var colorSelect = sourceRow.querySelector('select[name="color"]');
		if (sizeSelect && data.size) sizeSelect.value = data.size;
		if (colorSelect && data.color) colorSelect.value = data.color;

		var qtyEls = sourceRow.querySelectorAll('.jm-cart-qty__val');
		var priceEls = sourceRow.querySelectorAll('.jm-cart-line-price');
		qtyEls.forEach(function (qtyEl) {
			qtyEl.textContent = String(data.qty);
		});
		priceEls.forEach(function (priceEl) {
			priceEl.textContent = formatMoney(data.subtotal);
		});

		return { needsReload: false };
	}

	function handleResponse(data, rollback) {
		if (data.login_required) {
			window.location.href = cfg.loginUrl || '/dang-nhap';
			return;
		}
		if (!data.ok) {
			if (rollback) rollback();
			if (data.stock !== undefined || (data.message && data.message.indexOf('chỉ còn') !== -1)) {
				if (!data.rowid || findRow(data.rowid)) {
					showStockNotice(data);
				} else {
					showFlash(data.message || 'Không đủ tồn kho.', true);
				}
			} else {
				showFlash(data.message || 'Không thể cập nhật giỏ hàng.', true);
			}
			return;
		}
		updateTotals(data.total_items, data.total_price);
		if (data.action === 'qty') {
			applyQtyUpdate(data);
		} else if (data.action === 'options') {
			var optionsResult = applyOptionsUpdate(data);
			if (optionsResult.needsReload) {
				window.location.reload();
				return;
			}
		} else if (data.action === 'delete') {
			if (data.empty) {
				window.location.reload();
				return;
			}
			if (data.rowid) {
				var row = findRow(data.rowid);
				if (row) row.remove();
				updateRowNumbers();
			}
		}
		if (data.message) {
			showFlash(data.message, false);
		}
	}

	function onQtyClick(e) {
		if (!interactionsEnabled || busy) return;
		var btn = e.target.closest('.jm-cart-qty__btn');
		if (!btn || busy) return;
		e.preventDefault();

		var row = btn.closest('.jm-cart-row');
		if (!row) return;
		var rowid = row.getAttribute('data-rowid');
		var action = btn.getAttribute('data-action');
		if (!rowid || !action) return;

		var qtyEl = row.querySelector('.jm-cart-qty__val');
		var prevQty = qtyEl ? (parseInt(qtyEl.textContent, 10) || 1) : 1;
		var url = cfg.updateUrl || '/gio-hang/update';

		setBusy(true);
		postForm(url, { rowid: rowid, action: action })
			.then(function (data) {
				handleResponse(data, function () {
					row.querySelectorAll('.jm-cart-qty__val').forEach(function (el) {
						el.textContent = String(prevQty);
					});
				});
			})
			.catch(function () {
				showFlash('Không thể cập nhật số lượng.', true);
			})
			.finally(function () {
				setBusy(false);
			});
	}

	function onOptionsChange(e) {
		if (!interactionsEnabled || busy) return;
		var select = e.target.closest('.jm-cart-opt__select');
		if (!select || busy) return;

		var form = select.closest('.jm-cart-opts-form');
		if (!form) return;

		var row = form.closest('.jm-cart-row');
		if (!row) return;

		var rowidInput = form.querySelector('input[name="rowid"]');
		var sizeSelect = form.querySelector('select[name="size"]');
		var colorSelect = form.querySelector('select[name="color"]');
		var prevSize = sizeSelect ? sizeSelect.getAttribute('data-prev') || sizeSelect.value : '';
		var prevColor = colorSelect ? colorSelect.getAttribute('data-prev') || colorSelect.value : '';

		var payload = {
			rowid: rowidInput ? rowidInput.value : row.getAttribute('data-rowid'),
			size: sizeSelect ? sizeSelect.value : (form.querySelector('input[name="size"]') || {}).value || '',
			color: colorSelect ? colorSelect.value : (form.querySelector('input[name="color"]') || {}).value || '',
		};

		setBusy(true);
		postForm(cfg.optionsUrl || '/gio-hang/cap-nhat-thuoc-tinh', payload)
			.then(function (data) {
				handleResponse(data, function () {
					if (sizeSelect) sizeSelect.value = prevSize;
					if (colorSelect) colorSelect.value = prevColor;
				});
				if (data.ok) {
					if (sizeSelect) sizeSelect.setAttribute('data-prev', sizeSelect.value);
					if (colorSelect) colorSelect.setAttribute('data-prev', colorSelect.value);
				}
			})
			.catch(function () {
				if (sizeSelect) sizeSelect.value = prevSize;
				if (colorSelect) colorSelect.value = prevColor;
				showFlash('Không thể đổi size/màu.', true);
			})
			.finally(function () {
				setBusy(false);
			});
	}

	function postDelete(rowid) {
		setBusy(true);
		var payload = {};
		if (rowid) {
			payload.rowid = rowid;
		}
		return postForm(cfg.delUrl || '/gio-hang/del', payload)
			.then(function (data) {
				handleResponse(data);
			})
			.catch(function () {
				showFlash('Không thể xóa sản phẩm.', true);
			})
			.finally(function () {
				setBusy(false);
			});
	}

	function onRemoveClick(e) {
		var link = e.target.closest('.jm-cart-remove');
		if (!link || busy) return;
		if (!window.confirm('Xóa sản phẩm này khỏi giỏ hàng?')) {
			e.preventDefault();
			return;
		}
		e.preventDefault();
		var rowid = link.getAttribute('data-rowid') || '';
		postDelete(rowid);
	}

	function onClearClick(e) {
		var link = e.target.closest('.jm-cart-clear');
		if (!link || busy) return;
		if (!window.confirm('Xóa toàn bộ giỏ hàng?')) {
			e.preventDefault();
			return;
		}
		e.preventDefault();
		postDelete('');
	}

	function initSelectPrev() {
		document.querySelectorAll('.jm-cart-opt__select').forEach(function (sel) {
			sel.setAttribute('data-prev', sel.value);
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var root = document.querySelector('.jm-cart-lux');
		if (!root) return;

		initSelectPrev();
		root.addEventListener('click', onQtyClick);
		root.addEventListener('change', onOptionsChange);
		root.addEventListener('click', onRemoveClick);
		root.addEventListener('click', onClearClick);

		window.setTimeout(function () {
			interactionsEnabled = true;
		}, 300);
	});
})();
