(function () {
	'use strict';

	var cfg = window.__SUPPORT_CHAT_CONFIG__ || {};
	var widget = document.getElementById('support-chat-widget');
	if (!widget) {
		return;
	}

	var customerLoggedIn = cfg.customerLoggedIn !== false && cfg.customerLoggedIn !== 'false';
	var loginGateEl = document.getElementById('support-chat-login-gate');

	var toggleBtn = document.getElementById('support-chat-toggle');
	var panel = document.getElementById('support-chat-panel');
	var closeBtn = document.getElementById('support-chat-close');
	var messagesEl = document.getElementById('support-chat-messages');
	var form = document.getElementById('support-chat-form');
	var input = document.getElementById('support-chat-input');
	var quickRepliesEl = document.getElementById('support-chat-quick-replies');
	var statusTextEl = document.getElementById('support-chat-status-text');
	var statusLabelEl = statusTextEl ? statusTextEl.querySelector('.support-chat-panel__status-label') : null;
	var headerAvatar = document.querySelector('.support-chat-panel__avatar--status');

	var STORAGE_KEY = 'support_chat_conversation_id';
	var conversationId = 0;
	var lastMessageId = 0;
	var chatStatus = 'ai_active';
	var staffName = '';
	var historyLoaded = false;
	var sending = false;
	var pollTimer = null;
	var welcomeShown = false;

	try {
		conversationId = parseInt(localStorage.getItem(STORAGE_KEY), 10) || 0;
	} catch (e) {
		conversationId = 0;
	}

	function showLoginRequiredMessage() {
		widget.classList.add('is-login-required');
		if (loginGateEl) {
			loginGateEl.hidden = false;
		}
		if (messagesEl) {
			messagesEl.hidden = true;
		}
		if (quickRepliesEl) {
			quickRepliesEl.hidden = true;
		}
		if (form) {
			form.hidden = true;
		}
		if (statusLabelEl) {
			statusLabelEl.textContent = 'Yêu cầu đăng nhập';
		} else if (statusTextEl) {
			statusTextEl.textContent = 'Yêu cầu đăng nhập';
		}
		stopPolling();
	}

	function ensureLoggedIn() {
		if (customerLoggedIn) {
			return true;
		}
		showLoginRequiredMessage();
		return false;
	}

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = str == null ? '' : String(str);
		return div.innerHTML;
	}

	function linkify(text) {
		return escapeHtml(text).replace(/(https?:\/\/[^\s)]+)/g, function (url) {
			return '<a href="' + url + '" target="_blank" rel="noopener">' + url + '</a>';
		});
	}

	function formatChatText(text) {
		var t = String(text == null ? '' : text);
		t = t.replace(/\*\*([^*]+)\*\*/g, '$1');
		t = t.replace(/__([^_]+)__/g, '$1');
		t = t.replace(/\*([^*]+)\*/g, '$1');
		t = t.replace(/^#{1,6}\s+/gm, '');
		t = t.replace(/^\s*[-*]\s+/gm, '• ');
		return t;
	}

	function buildActionsHtml(actions) {
		if (!Array.isArray(actions) || !actions.length) {
			return '';
		}
		var html = '<div class="support-chat-actions">';
		actions.forEach(function (a) {
			if (!a || !a.label) {
				return;
			}
			if (a.kind === 'link' && a.url) {
				html += '<a class="support-chat-act support-chat-act--link" href="' + escapeHtml(a.url) + '">'
					+ escapeHtml(a.label) + '</a>';
			} else if (a.kind === 'send' && a.text) {
				html += '<button type="button" class="support-chat-act support-chat-act--send" data-text="'
					+ escapeHtml(a.text) + '">' + escapeHtml(a.label) + '</button>';
			}
		});
		html += '</div>';
		return html;
	}

	function buildProductCardsHtml(products) {
		if (!Array.isArray(products) || !products.length) {
			return '';
		}
		var html = '<div class="support-chat-product-grid">';
		products.forEach(function (p) {
			var pid = parseInt(p.id, 10) || 0;
			var img = p.image_url
				? '<img class="support-chat-pcard__img" src="' + escapeHtml(p.image_url) + '" alt="" loading="lazy" decoding="async">'
				: '<div class="support-chat-pcard__img support-chat-pcard__img--empty" aria-hidden="true"></div>';
			var priceHtml = '<span class="support-chat-pcard__price">' + escapeHtml(p.price_fmt || '') + '</span>';
			if (p.list_price_fmt) {
				priceHtml += '<span class="support-chat-pcard__price-old">' + escapeHtml(p.list_price_fmt) + '</span>';
			}
			var stockCls = p.in_stock ? 'support-chat-pcard__stock--in' : 'support-chat-pcard__stock--out';
			var meta = '';
			if (p.colors) {
				meta += '<span>Màu: ' + escapeHtml(p.colors) + '</span>';
			}
			if (p.sizes) {
				meta += '<span>Size: ' + escapeHtml(p.sizes) + '</span>';
			}
			var buyBtn = '';
			if (p.buyable && pid > 0) {
				buyBtn = '<button type="button" class="support-chat-act support-chat-act--buy" data-product-id="'
					+ pid + '">Thêm vào giỏ</button>';
			}
			html += '<article class="support-chat-pcard" data-product-id="' + pid + '">'
				+ '<a class="support-chat-pcard__media" href="' + escapeHtml(p.url || '#') + '" target="_blank" rel="noopener">'
				+ img + '</a>'
				+ '<div class="support-chat-pcard__body">'
				+ '<a class="support-chat-pcard__name" href="' + escapeHtml(p.url || '#') + '" target="_blank" rel="noopener">'
				+ escapeHtml(p.name || '') + '</a>'
				+ '<div class="support-chat-pcard__prices">' + priceHtml + '</div>'
				+ (meta ? '<div class="support-chat-pcard__meta">' + meta + '</div>' : '')
				+ '<span class="support-chat-pcard__stock ' + stockCls + '">' + escapeHtml(p.stock_label || '') + '</span>'
				+ '<div class="support-chat-pcard__actions">'
				+ '<a class="support-chat-act support-chat-act--view" href="' + escapeHtml(p.url || '#') + '" target="_blank" rel="noopener">Xem sản phẩm</a>'
				+ buyBtn
				+ '</div>'
				+ '<div class="support-chat-pcard__picker" hidden></div>'
				+ '</div></article>';
		});
		html += '</div>';
		return html;
	}

	function updateHeaderCartBadge(totalItems) {
		var n = parseInt(totalItems, 10);
		if (isNaN(n) || n < 0) {
			return;
		}
		var label = n > 99 ? '99+' : String(n);
		document.querySelectorAll('.wf-chrome-cart-badge, .jm-cart-slot .jm-icon-dot').forEach(function (el) {
			if (n > 0) {
				el.textContent = label;
				el.hidden = false;
				el.style.display = '';
			} else {
				el.textContent = '';
				el.hidden = true;
			}
		});
	}

	function variantStockLookup(variants, color, size) {
		if (!Array.isArray(variants)) {
			return null;
		}
		for (var i = 0; i < variants.length; i++) {
			var v = variants[i];
			if (v && v.color === color && v.size === size) {
				return v;
			}
		}
		return null;
	}

	function renderProductPicker(cardEl, data) {
		var picker = cardEl.querySelector('.support-chat-pcard__picker');
		if (!picker) {
			return;
		}
		var colors = Array.isArray(data.colors) ? data.colors : [];
		var sizes = Array.isArray(data.sizes) ? data.sizes : [];
		var color = data.default_color || colors[0] || '';
		var size = data.default_size || sizes[0] || '';
		var colorOpts = colors.map(function (c) {
			return '<option value="' + escapeHtml(c) + '"' + (c === color ? ' selected' : '') + '>' + escapeHtml(c || 'Mặc định') + '</option>';
		}).join('');
		var sizeOpts = sizes.map(function (s) {
			return '<option value="' + escapeHtml(s) + '"' + (s === size ? ' selected' : '') + '>' + escapeHtml(s || 'Freesize') + '</option>';
		}).join('');
		picker.innerHTML = ''
			+ '<div class="support-chat-picker">'
			+ '<label class="support-chat-picker__field"><span>Màu</span><select class="support-chat-picker__color">' + colorOpts + '</select></label>'
			+ '<label class="support-chat-picker__field"><span>Size</span><select class="support-chat-picker__size">' + sizeOpts + '</select></label>'
			+ '<label class="support-chat-picker__field support-chat-picker__field--qty"><span>SL</span>'
			+ '<input type="number" class="support-chat-picker__qty" min="1" max="99" value="1"></label>'
			+ '<p class="support-chat-picker__stock" aria-live="polite"></p>'
			+ '<p class="support-chat-picker__error" hidden></p>'
			+ '<button type="button" class="support-chat-act support-chat-act--confirm">Xác nhận thêm giỏ</button>'
			+ '<button type="button" class="support-chat-act support-chat-act--cancel">Huỷ</button>'
			+ '</div>';
		picker.hidden = false;
		picker._variants = data.variants || [];
		picker._productId = data.product_id;
		syncPickerStock(picker);
	}

	function syncPickerStock(picker) {
		if (!picker) {
			return;
		}
		var colorSel = picker.querySelector('.support-chat-picker__color');
		var sizeSel = picker.querySelector('.support-chat-picker__size');
		var stockEl = picker.querySelector('.support-chat-picker__stock');
		var confirmBtn = picker.querySelector('.support-chat-act--confirm');
		var color = colorSel ? colorSel.value : '';
		var size = sizeSel ? sizeSel.value : '';
		var row = variantStockLookup(picker._variants, color, size);
		var inStock = row && row.in_stock && row.stock > 0;
		if (stockEl) {
			stockEl.textContent = row
				? (inStock ? 'Còn ' + row.stock + ' sản phẩm' : 'Hết hàng với lựa chọn này')
				: 'Không có biến thể này';
			stockEl.className = 'support-chat-picker__stock' + (inStock ? ' support-chat-picker__stock--in' : ' support-chat-picker__stock--out');
		}
		if (confirmBtn) {
			confirmBtn.disabled = !inStock;
		}
	}

	function openProductPicker(productId, cardEl) {
		if (!ensureLoggedIn() || !cfg.productOptionsUrl || !productId) {
			return;
		}
		var url = cfg.productOptionsUrl + encodeURIComponent(productId) + '/options';
		cardEl.classList.add('support-chat-pcard--loading');
		fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(parseJsonResponse)
			.then(function (data) {
				cardEl.classList.remove('support-chat-pcard--loading');
				if (typeof window.applyCsrfResponse === 'function') {
					window.applyCsrfResponse(data);
				}
				if (!data || !data.ok) {
					appendMessage({
						sender_type: 'system',
						content: (data && data.message) ? data.message : 'Không tải được thông tin sản phẩm.',
						created: Math.floor(Date.now() / 1000),
					});
					return;
				}
				renderProductPicker(cardEl, data);
				scrollToBottom();
			})
			.catch(function () {
				cardEl.classList.remove('support-chat-pcard--loading');
				appendMessage({
					sender_type: 'system',
					content: 'Không tải được tùy chọn sản phẩm. Vui lòng thử lại.',
					created: Math.floor(Date.now() / 1000),
				});
			});
	}

	function postCartAdd(productId, qty, size, color) {
		if (!cfg.cartAddUrl || !productId) {
			return Promise.resolve({ ok: false, message: 'Không thể thêm giỏ hàng.' });
		}
		var url = cfg.cartAddUrl + encodeURIComponent(productId);
		var body = 'qty=' + encodeURIComponent(qty || 1)
			+ '&size=' + encodeURIComponent(size || '')
			+ '&color=' + encodeURIComponent(color || '');
		if (window.CI_CSRF) {
			body += '&' + encodeURIComponent(window.CI_CSRF.name) + '=' + encodeURIComponent(window.CI_CSRF.hash);
		}
		return fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'X-Requested-With': 'XMLHttpRequest',
			},
			body: body,
		}).then(parseJsonResponse);
	}

	function afterCartAddSuccess(data) {
		if (typeof window.applyCsrfResponse === 'function') {
			window.applyCsrfResponse(data);
		}
		updateHeaderCartBadge(data.total_items);
		var actions = [
			{ kind: 'link', label: 'Xem giỏ hàng', url: cfg.cartUrl || '/gio-hang' },
		];
		if (cfg.checkoutUrl) {
			actions.push({ kind: 'link', label: 'Tiến hành thanh toán', url: cfg.checkoutUrl });
		}
		appendMessage({
			sender_type: 'system',
			content: data.message || 'Đã thêm vào giỏ hàng.',
			actions: actions,
			created: Math.floor(Date.now() / 1000),
		});
	}

	function confirmPickerAdd(picker, cardEl) {
		if (!picker || picker._busy) {
			return;
		}
		var errEl = picker.querySelector('.support-chat-picker__error');
		var qtyEl = picker.querySelector('.support-chat-picker__qty');
		var colorSel = picker.querySelector('.support-chat-picker__color');
		var sizeSel = picker.querySelector('.support-chat-picker__size');
		var qty = qtyEl ? parseInt(qtyEl.value, 10) : 1;
		if (!qty || qty < 1) {
			qty = 1;
		}
		if (qty > 99) {
			qty = 99;
		}
		picker._busy = true;
		if (errEl) {
			errEl.hidden = true;
			errEl.textContent = '';
		}
		postCartAdd(picker._productId, qty, sizeSel ? sizeSel.value : '', colorSel ? colorSel.value : '')
			.then(function (data) {
				picker._busy = false;
				if (data && data.login_required) {
					customerLoggedIn = false;
					showLoginRequiredMessage();
					return;
				}
				if (!data || !data.ok) {
					if (errEl) {
						errEl.hidden = false;
						errEl.textContent = (data && data.message) ? data.message : 'Không thêm được vào giỏ.';
					}
					return;
				}
				picker.hidden = true;
				picker.innerHTML = '';
				cardEl.classList.remove('support-chat-pcard--open');
				afterCartAddSuccess(data);
			})
			.catch(function () {
				picker._busy = false;
				if (errEl) {
					errEl.hidden = false;
					errEl.textContent = 'Lỗi kết nối. Vui lòng thử lại.';
				}
			});
	}

	function formatTime(ts) {
		if (!ts) {
			return '';
		}
		var d = new Date(ts * 1000);
		return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
	}

	function scrollToBottom() {
		messagesEl.scrollTop = messagesEl.scrollHeight;
	}

	function appendToMessages(el) {
		if (!el || !messagesEl) {
			return;
		}
		var typingEl = document.getElementById('support-chat-typing');
		if (typingEl && typingEl.parentNode === messagesEl) {
			messagesEl.insertBefore(el, typingEl);
		} else {
			messagesEl.appendChild(el);
		}
	}

	function pinTypingToBottom() {
		var typingEl = document.getElementById('support-chat-typing');
		if (typingEl && messagesEl && typingEl.parentNode === messagesEl) {
			messagesEl.appendChild(typingEl);
			scrollToBottom();
		}
	}

	function parseJsonResponse(res) {
		return res.text().then(function (text) {
			if (!text) {
				return { ok: false, message: 'Phản hồi trống từ máy chủ.' };
			}
			try {
				return JSON.parse(text);
			} catch (e) {
				return { ok: false, message: 'Phản hồi không hợp lệ từ máy chủ.' };
			}
		});
	}

	function resetConversationStorage() {
		conversationId = 0;
		lastMessageId = 0;
		try {
			localStorage.removeItem(STORAGE_KEY);
		} catch (e) {}
	}

	function removeLocalMessages() {
		var locals = messagesEl.querySelectorAll('[data-local="1"]');
		for (var i = 0; i < locals.length; i++) {
			if (locals[i].parentNode) {
				locals[i].parentNode.removeChild(locals[i]);
			}
		}
	}

	function senderMeta(senderType) {
		switch (senderType) {
			case 'customer':
				return { cls: 'customer', avatar: 'fa-user', label: 'Bạn' };
			case 'staff':
				return { cls: 'staff', avatar: 'fa-headset', label: staffName || 'Nhân viên' };
			case 'system':
				return { cls: 'system', avatar: 'fa-circle-info', label: 'Hệ thống' };
			default:
				return { cls: 'ai', avatar: 'fa-robot', label: 'Trợ lý AI' };
		}
	}

	function appendMessage(msg) {
		if (!msg || (!msg.content && !(msg.products && msg.products.length) && !(msg.actions && msg.actions.length))) {
			return null;
		}
		if (msg.id && msg.id > lastMessageId) {
			lastMessageId = msg.id;
		}
		if (msg.id && document.querySelector('[data-msg-id="' + msg.id + '"]')) {
			return null;
		}

		var meta = senderMeta(msg.sender_type || 'ai');
		var el = document.createElement('div');
		el.className = 'support-chat-msg support-chat-msg--' + meta.cls;
		if (msg.id) {
			el.setAttribute('data-msg-id', msg.id);
		}
		if (msg._local) {
			el.setAttribute('data-local', '1');
		}

		var bubbleInner = '';
		if (msg.content) {
			bubbleInner += '<div class="support-chat-msg__text">' + linkify(formatChatText(msg.content)).replace(/\n/g, '<br>') + '</div>';
		}
		bubbleInner += buildProductCardsHtml(msg.products);
		bubbleInner += buildActionsHtml(msg.actions);

		el.innerHTML = ''
			+ '<div class="support-chat-msg__avatar" aria-hidden="true"><i class="fa-solid ' + meta.avatar + '"></i></div>'
			+ '<div class="support-chat-msg__body">'
			+ '<div class="support-chat-msg__label">' + escapeHtml(meta.label) + '</div>'
			+ '<div class="support-chat-msg__bubble">' + bubbleInner + '</div>'
			+ '<div class="support-chat-msg__time">' + formatTime(msg.created) + '</div>'
			+ '</div>';

		appendToMessages(el);
		pinTypingToBottom();
		scrollToBottom();
		return el;
	}

	function appendConnecting() {
		var existing = document.getElementById('support-chat-connecting');
		if (existing) {
			return;
		}
		var el = document.createElement('div');
		el.id = 'support-chat-connecting';
		el.className = 'support-chat-msg support-chat-msg--system';
		el.innerHTML = '<div class="support-chat-msg__bubble support-chat-msg__bubble--connecting">🤝 Đang kết nối nhân viên...</div>';
		appendToMessages(el);
		pinTypingToBottom();
		scrollToBottom();
	}

	function removeConnecting() {
		var el = document.getElementById('support-chat-connecting');
		if (el && el.parentNode) {
			el.parentNode.removeChild(el);
		}
	}

	function showTyping(label) {
		hideTyping();
		var el = document.createElement('div');
		el.className = 'support-chat-msg support-chat-msg--typing';
		el.id = 'support-chat-typing';
		el.innerHTML = '<div class="support-chat-msg__bubble"><span class="support-chat-typing-label">' + escapeHtml(label) + '</span>'
			+ '<span></span><span></span><span></span></div>';
		messagesEl.appendChild(el);
		scrollToBottom();
	}

	function hideTyping() {
		var el = document.getElementById('support-chat-typing');
		if (el && el.parentNode) {
			el.parentNode.removeChild(el);
		}
	}

	function updateStatus(status, name) {
		chatStatus = status || 'ai_active';
		if (name) {
			staffName = name;
		}

		var statusTarget = statusLabelEl || statusTextEl;
		if (statusTarget) {
			if (chatStatus === 'waiting_staff') {
				statusTarget.textContent = 'Đang chờ nhân viên';
			} else if (chatStatus === 'staff_joined') {
				statusTarget.textContent = (staffName || 'Nhân viên') + ' đang hỗ trợ';
			} else if (chatStatus === 'closed') {
				statusTarget.textContent = 'Cuộc trò chuyện đã kết thúc';
			} else {
				statusTarget.textContent = 'Trợ lý AI đang trực tuyến';
			}
		}

		if (headerAvatar) {
			headerAvatar.innerHTML = chatStatus === 'staff_joined'
				? '<i class="fa-solid fa-headset"></i>'
				: '<i class="fa-solid fa-robot"></i>';
		}

		if (chatStatus === 'waiting_staff') {
			// Tin hệ thống "Đang kết nối..." đã được lưu DB; không cần bubble tạm trùng.
		} else {
			removeConnecting();
		}
	}

	function showWelcomeIntro() {
		if (welcomeShown) {
			return;
		}
		welcomeShown = true;
		var welcome = cfg.welcomeMessage || 'Xin chào 👋';
		appendMessage({ sender_type: 'ai', content: welcome + '\n\nMình là trợ lý mua sắm của Webshop.', created: Math.floor(Date.now() / 1000) });
		if (cfg.welcomeIntro) {
			appendMessage({ sender_type: 'ai', content: cfg.welcomeIntro, created: Math.floor(Date.now() / 1000) });
		}
	}

	function ingestMessages(messages) {
		if (!Array.isArray(messages)) {
			return;
		}
		var hasServerCustomer = messages.some(function (m) {
			return m && m.sender_type === 'customer' && m.id;
		});
		if (hasServerCustomer) {
			removeLocalMessages();
		}
		messages.forEach(function (m) {
			appendMessage(m);
		});
		pinTypingToBottom();
	}

	function startPolling() {
		stopPolling();
		if (!conversationId || !cfg.pollUrl) {
			return;
		}
		pollOnce();
		pollTimer = window.setInterval(pollOnce, cfg.pollIntervalMs || 1500);
	}

	function stopPolling() {
		if (pollTimer) {
			window.clearInterval(pollTimer);
			pollTimer = null;
		}
	}

	function pollOnce() {
		if (!conversationId || !cfg.pollUrl) {
			return Promise.resolve(null);
		}
		var url = cfg.pollUrl + '?conversation_id=' + encodeURIComponent(conversationId)
			+ '&after_id=' + encodeURIComponent(lastMessageId || 0);

		return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (!data || !data.ok) {
					return data;
				}
				if (data.status) {
					updateStatus(data.status, data.staff_name);
				}
				ingestMessages(data.messages);
				return data;
			})
			.catch(function () {
				return null;
			});
	}

	document.body.classList.add('has-support-chat-widget');

	function openPanel() {
		widget.classList.add('is-open');
		panel.hidden = false;
		toggleBtn.setAttribute('aria-expanded', 'true');
		if (!ensureLoggedIn()) {
			return;
		}
		if (!historyLoaded) {
			loadHistory();
		}
		startPolling();
		if (input) {
			input.focus();
		}
	}

	function closePanel() {
		widget.classList.remove('is-open');
		panel.hidden = true;
		toggleBtn.setAttribute('aria-expanded', 'false');
		stopPolling();
		historyLoaded = false;
	}

	function loadHistory() {
		historyLoaded = true;

		if (!conversationId || !cfg.historyUrl) {
			showWelcomeIntro();
			return;
		}

		fetch(cfg.historyUrl + '?conversation_id=' + conversationId, {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
		})
			.then(parseJsonResponse)
			.then(function (data) {
				if (data && data.status) {
					updateStatus(data.status, data.staff_name);
				}
				if (data && data.ok && Array.isArray(data.messages) && data.messages.length) {
					messagesEl.innerHTML = '';
					welcomeShown = false;
					ingestMessages(data.messages);
				} else if (conversationId > 0 && data && data.ok && (!data.messages || !data.messages.length)) {
					showWelcomeIntro();
				} else {
					showWelcomeIntro();
				}
			})
			.catch(function () {
				showWelcomeIntro();
			});
	}

	function sendMessage(text) {
		text = (text || '').trim();
		if (!text || sending || !cfg.sendUrl) {
			return;
		}
		if (!ensureLoggedIn()) {
			return;
		}

		hideTyping();
		appendMessage({ sender_type: 'customer', content: text, created: Math.floor(Date.now() / 1000), _local: true });
		if (input) {
			input.value = '';
		}
		sending = true;

		var typingLabel = chatStatus === 'staff_joined' ? 'Nhân viên đang nhập...' : 'AI đang nhập...';
		if (chatStatus !== 'waiting_staff') {
			showTyping(typingLabel);
		}

		var body = 'message=' + encodeURIComponent(text) + '&conversation_id=' + encodeURIComponent(conversationId || 0);
		if (window.CI_CSRF) {
			body += '&' + encodeURIComponent(window.CI_CSRF.name) + '=' + encodeURIComponent(window.CI_CSRF.hash);
		}

		fetch(cfg.sendUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'X-Requested-With': 'XMLHttpRequest',
			},
			body: body,
		})
			.then(parseJsonResponse)
			.then(function (data) {
				hideTyping();
				sending = false;

				if (typeof window.applyCsrfResponse === 'function') {
					window.applyCsrfResponse(data);
				}

				if (!data || !data.ok) {
					removeLocalMessages();
					if (data && data.login_required) {
						customerLoggedIn = false;
						showLoginRequiredMessage();
						return;
					}
					var errMsg = (data && data.message)
						? data.message
						: 'Xin lỗi, có lỗi xảy ra. Vui lòng thử lại.';
					appendMessage({ sender_type: 'ai', content: errMsg, created: Math.floor(Date.now() / 1000) });
					return;
				}

				if (data.conversation_id) {
					conversationId = data.conversation_id;
					try {
						localStorage.setItem(STORAGE_KEY, conversationId);
					} catch (e) {}
					startPolling();
				}

				if (data.status) {
					updateStatus(data.status, data.staff_name);
				}

				if (data.handoff || data.connecting) {
					updateStatus('waiting_staff');
				}

				var replyContent = String(data.content || '').trim();
				var replyProducts = Array.isArray(data.products) ? data.products : [];
				var replyActions = Array.isArray(data.actions) ? data.actions : [];

				return pollOnce().then(function (pollData) {
					var gotMessages = pollData && Array.isArray(pollData.messages) && pollData.messages.length > 0;
					if (gotMessages) {
						ingestMessages(pollData.messages);
						return;
					}
					if (replyContent || replyProducts.length || replyActions.length) {
						appendMessage({
							sender_type: 'ai',
							content: replyContent,
							products: replyProducts,
							actions: replyActions,
							created: Math.floor(Date.now() / 1000),
						});
						return;
					}
					if (chatStatus === 'ai_active' && !data.handoff && !data.connecting) {
						appendMessage({
							sender_type: 'ai',
							content: 'Xin lỗi, mình chưa nhận được phản hồi. Vui lòng thử lại.',
							created: Math.floor(Date.now() / 1000),
						});
					}
				});
			})
			.catch(function () {
				hideTyping();
				sending = false;
				removeLocalMessages();
				appendMessage({ sender_type: 'ai', content: 'Không thể kết nối. Vui lòng thử lại sau.', created: Math.floor(Date.now() / 1000) });
			});
	}

	toggleBtn.addEventListener('click', openPanel);
	closeBtn.addEventListener('click', closePanel);

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		sendMessage(input ? input.value : '');
	});

	if (quickRepliesEl) {
		quickRepliesEl.addEventListener('click', function (e) {
			var target = e.target;
			while (target && target !== quickRepliesEl && !target.classList.contains('support-chat-quick-reply')) {
				target = target.parentNode;
			}
			if (target && target.classList && target.classList.contains('support-chat-quick-reply')) {
				sendMessage(target.getAttribute('data-text'));
			}
		});
	}

	if (messagesEl) {
		messagesEl.addEventListener('click', function (e) {
			var sendBtn = e.target.closest('.support-chat-act--send');
			if (sendBtn && messagesEl.contains(sendBtn)) {
				e.preventDefault();
				sendMessage(sendBtn.getAttribute('data-text'));
				return;
			}
			var buyBtn = e.target.closest('.support-chat-act--buy');
			if (buyBtn && messagesEl.contains(buyBtn)) {
				e.preventDefault();
				var card = buyBtn.closest('.support-chat-pcard');
				var productId = parseInt(buyBtn.getAttribute('data-product-id'), 10)
					|| (card ? parseInt(card.getAttribute('data-product-id'), 10) : 0);
				if (card && productId > 0) {
					card.classList.add('support-chat-pcard--open');
					openProductPicker(productId, card);
				}
				return;
			}
			var confirmBtn = e.target.closest('.support-chat-act--confirm');
			if (confirmBtn && messagesEl.contains(confirmBtn)) {
				e.preventDefault();
				var picker = confirmBtn.closest('.support-chat-pcard__picker');
				var cardEl = confirmBtn.closest('.support-chat-pcard');
				if (picker && cardEl) {
					confirmPickerAdd(picker, cardEl);
				}
				return;
			}
			var cancelBtn = e.target.closest('.support-chat-act--cancel');
			if (cancelBtn && messagesEl.contains(cancelBtn)) {
				e.preventDefault();
				var pickerCancel = cancelBtn.closest('.support-chat-pcard__picker');
				var cardCancel = cancelBtn.closest('.support-chat-pcard');
				if (pickerCancel) {
					pickerCancel.hidden = true;
					pickerCancel.innerHTML = '';
				}
				if (cardCancel) {
					cardCancel.classList.remove('support-chat-pcard--open');
				}
			}
		});

		messagesEl.addEventListener('change', function (e) {
			var picker = e.target.closest('.support-chat-pcard__picker');
			if (picker && (e.target.classList.contains('support-chat-picker__color')
				|| e.target.classList.contains('support-chat-picker__size'))) {
				syncPickerStock(picker);
			}
		});
	}
})();
