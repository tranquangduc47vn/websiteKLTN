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
		if (statusTextEl) {
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
		if (!msg || !msg.content) {
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

		el.innerHTML = ''
			+ '<div class="support-chat-msg__avatar" aria-hidden="true"><i class="fa-solid ' + meta.avatar + '"></i></div>'
			+ '<div class="support-chat-msg__body">'
			+ '<div class="support-chat-msg__label">' + escapeHtml(meta.label) + '</div>'
			+ '<div class="support-chat-msg__bubble">' + linkify(msg.content).replace(/\n/g, '<br>') + '</div>'
			+ '<div class="support-chat-msg__time">' + formatTime(msg.created) + '</div>'
			+ '</div>';

		messagesEl.appendChild(el);
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
		messagesEl.appendChild(el);
		scrollToBottom();
	}

	function removeConnecting() {
		var el = document.getElementById('support-chat-connecting');
		if (el && el.parentNode) {
			el.parentNode.removeChild(el);
		}
	}

	function renderProducts(products) {
		if (!Array.isArray(products) || !products.length) {
			return;
		}
		var html = '';
		products.forEach(function (p) {
			html += '<div class="support-chat-product">'
				+ '<a href="' + escapeHtml(p.url) + '" target="_blank" rel="noopener"><strong>' + escapeHtml(p.name) + '</strong></a>'
				+ '<span>' + escapeHtml(p.price_fmt) + '</span></div>';
		});
		var wrap = document.createElement('div');
		wrap.className = 'support-chat-msg support-chat-msg--ai';
		wrap.innerHTML = '<div class="support-chat-msg__avatar" aria-hidden="true"><i class="fa-solid fa-robot"></i></div>'
			+ '<div class="support-chat-msg__body"><div class="support-chat-msg__bubble">' + html + '</div></div>';
		messagesEl.appendChild(wrap);
		scrollToBottom();
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

		if (statusTextEl) {
			if (chatStatus === 'waiting_staff') {
				statusTextEl.textContent = 'Đang chờ nhân viên';
			} else if (chatStatus === 'staff_joined') {
				statusTextEl.textContent = (staffName || 'Nhân viên') + ' đang hỗ trợ';
			} else if (chatStatus === 'closed') {
				statusTextEl.textContent = 'Cuộc trò chuyện đã kết thúc';
			} else {
				statusTextEl.textContent = 'Trợ lý AI đang trực tuyến';
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

				if (Array.isArray(data.products) && data.products.length) {
					renderProducts(data.products);
				}

				var replyContent = String(data.content || '').trim();

				return pollOnce().then(function (pollData) {
					var gotMessages = pollData && Array.isArray(pollData.messages) && pollData.messages.length > 0;
					if (gotMessages) {
						ingestMessages(pollData.messages);
						return;
					}
					if (replyContent) {
						appendMessage({ sender_type: 'ai', content: replyContent, created: Math.floor(Date.now() / 1000) });
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
})();
