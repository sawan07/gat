/**
 * GAT chat bubble: talks to the donation assistant's /api/new-thread and
 * /api/chat. The conversation is kept in sessionStorage so it survives moving
 * between pages (and is gone when the tab closes).
 *
 * Safety: assistant text is inserted as text nodes; only https:// URLs become
 * links, and the Pay button only accepts Stripe URLs.
 */
(function () {
	'use strict';
	var C = window.GAT_CHAT;
	if (!C || !C.api || document.getElementById('gatc-launcher')) return;
	var T = C.i18n || {};
	var KEY = 'gat_chat_v1';
	var MAX_KEEP = 40;

	// ---- state ----------------------------------------------------------
	var state = { threadId: null, history: [], open: false };
	try {
		var saved = JSON.parse(sessionStorage.getItem(KEY) || 'null');
		if (saved && Array.isArray(saved.history)) state = saved;
	} catch (e) { /* storage blocked: chat still works for this page */ }
	function save() {
		state.history = state.history.slice(-MAX_KEEP);
		try { sessionStorage.setItem(KEY, JSON.stringify(state)); } catch (e) { /* ignore */ }
	}

	// ---- helpers ---------------------------------------------------------
	function el(tag, cls, attrs) {
		var n = document.createElement(tag);
		if (cls) n.className = cls;
		if (attrs) for (var k in attrs) n.setAttribute(k, attrs[k]);
		return n;
	}
	function svg(path, size) {
		var s = size || 22;
		var wrap = el('span', 'gatc-ico', { 'aria-hidden': 'true' });
		wrap.innerHTML = '<svg width="' + s + '" height="' + s + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + path + '</svg>';
		return wrap;
	}
	var ICON_CHAT = '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.6A8 8 0 1 1 21 12z"/>';
	var ICON_CLOSE = '<path d="M18 6 6 18M6 6l12 12"/>';
	var ICON_NEW = '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>';
	var ICON_SEND = '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/>';

	function fillWithLinks(node, text) {
		var re = /https:\/\/[^\s<>"')\]]+/g, last = 0, m;
		while ((m = re.exec(text))) {
			var url = m[0].replace(/[.,;:!?]+$/, '');
			node.appendChild(document.createTextNode(text.slice(last, m.index)));
			var a = el('a', null, { href: url, target: '_blank', rel: 'noopener' });
			a.textContent = url;
			node.appendChild(a);
			last = m.index + url.length;
			re.lastIndex = last;
		}
		node.appendChild(document.createTextNode(text.slice(last)));
	}

	// ---- build UI --------------------------------------------------------
	var launcher = el('button', 'gatc-launcher', {
		id: 'gatc-launcher', type: 'button', 'aria-expanded': 'false', 'aria-controls': 'gatc-panel'
	});
	launcher.appendChild(svg(ICON_CHAT, 24));
	var lLabel = el('span', 'gatc-launcher__label');
	lLabel.textContent = T.open || 'Chat with us';
	launcher.appendChild(lLabel);

	var panel = el('section', 'gatc-panel', {
		id: 'gatc-panel', role: 'dialog', 'aria-modal': 'false', 'aria-labelledby': 'gatc-title', hidden: ''
	});

	var head = el('header', 'gatc-head');
	var logo = el('img', 'gatc-logo', { src: C.logo, alt: '', width: '40', height: '40' });
	var id = el('div', 'gatc-id');
	var title = el('h2', 'gatc-title', { id: 'gatc-title' });
	title.textContent = T.title || 'GAT Assistant';
	var sub = el('p', 'gatc-sub');
	sub.textContent = T.subtitle || '';
	id.append(title, sub);
	var restartBtn = el('button', 'gatc-iconbtn', { type: 'button', 'aria-label': T.restart || 'Start a new chat', title: T.restart || '' });
	restartBtn.appendChild(svg(ICON_NEW, 18));
	var closeBtn = el('button', 'gatc-iconbtn', { type: 'button', 'aria-label': T.close || 'Close chat', title: T.close || '' });
	closeBtn.appendChild(svg(ICON_CLOSE, 20));
	head.append(logo, id, restartBtn, closeBtn);

	var msgs = el('div', 'gatc-messages', { 'aria-live': 'polite', 'aria-relevant': 'additions' });
	var chips = el('div', 'gatc-chips');
	(C.chips || []).forEach(function (c) {
		var b = el('button', 'gatc-chip', { type: 'button' });
		b.textContent = c;
		chips.appendChild(b);
	});

	var form = el('form', 'gatc-form');
	var input = el('textarea', 'gatc-input', { rows: '1', maxlength: '1000', placeholder: T.placeholder || '', 'aria-label': T.placeholder || 'Message' });
	var sendBtn = el('button', 'gatc-send', { type: 'submit', 'aria-label': T.send || 'Send' });
	sendBtn.appendChild(svg(ICON_SEND, 18));
	form.append(input, sendBtn);

	var note = el('p', 'gatc-note');
	note.textContent = T.note || '';

	panel.append(head, msgs, chips, form, note);
	document.body.append(launcher, panel);

	// ---- rendering -------------------------------------------------------
	function scrollDown() { msgs.scrollTop = msgs.scrollHeight; }

	function renderMsg(m) {
		var row = el('div', 'gatc-row gatc-row--' + m.role);
		if (m.role === 'pay') {
			var p = m.payment;
			var monthly = p.frequency === 'monthly';
			var amount = '£' + Number(p.amount).toFixed(2);
			var card = el('div', 'gatc-pay');
			var t = el('div', 'gatc-pay__title');
			t.textContent = amount + (monthly ? ' ' + (T.aMonth || 'a month') : '') + ' · ' + p.campaign_name;
			var a = el('a', 'gatc-pay__btn', { href: p.url, target: '_blank', rel: 'noopener' });
			a.textContent = monthly ? (T.payMonthly || 'Set up monthly gift') : (T.pay || 'Pay %s securely').replace('%s', amount);
			var n = el('div', 'gatc-pay__note');
			n.textContent = T.payNote || '';
			card.append(t, a, n);
			row.appendChild(card);
		} else {
			var b = el('div', 'gatc-bubble');
			if (m.role === 'agent') fillWithLinks(b, m.text); else b.textContent = m.text;
			if (m.role === 'system' && m.donate && C.donateUrl) {
				b.appendChild(document.createTextNode(' '));
				var d = el('a', null, { href: C.donateUrl });
				d.textContent = T.donatePage || 'Donate page';
				b.appendChild(d);
			}
			row.appendChild(b);
		}
		msgs.appendChild(row);
		scrollDown();
	}

	function add(m, keep) {
		if (keep !== false) { state.history.push(m); save(); }
		renderMsg(m);
	}

	function renderAll() {
		msgs.textContent = '';
		renderMsg({ role: 'agent', text: C.greeting || '' });
		state.history.forEach(renderMsg);
		chips.hidden = state.history.length > 0;
	}

	var typingRow = null;
	function typing(on) {
		if (on && !typingRow) {
			typingRow = el('div', 'gatc-row gatc-row--agent gatc-row--typing');
			var b = el('div', 'gatc-bubble gatc-typing', { 'aria-label': '…' });
			b.innerHTML = '<span></span><span></span><span></span>';
			typingRow.appendChild(b);
			msgs.appendChild(typingRow);
			scrollDown();
		} else if (!on && typingRow) {
			typingRow.remove();
			typingRow = null;
		}
	}

	// ---- API ---------------------------------------------------------------
	function post(path, body) {
		return fetch(C.api + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: body ? JSON.stringify(body) : '{}',
			credentials: 'omit',
			mode: 'cors'
		});
	}

	function newThread() {
		return post('/api/new-thread').then(function (r) {
			if (!r.ok) throw new Error('thread');
			return r.json();
		}).then(function (d) {
			state.threadId = d.thread_id;
			save();
			return d.thread_id;
		});
	}

	var sending = false;
	function send(text) {
		text = (text || '').trim();
		if (!text || sending) return;
		sending = true;
		sendBtn.disabled = true;
		chips.hidden = true;
		add({ role: 'user', text: text });
		typing(true);

		var ready = state.threadId ? Promise.resolve(state.threadId) : newThread();
		ready.then(function (tid) {
			return post('/api/chat', { thread_id: tid, message: text });
		}).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (d) { return { r: r, d: d }; });
		}).then(function (res) {
			typing(false);
			if (res.r.status === 404) {
				state.threadId = null;
				save();
				add({ role: 'system', text: T.expired || 'Please send your message again.' });
				return;
			}
			if (!res.r.ok) {
				add({ role: 'system', text: res.d.detail || T.offline, donate: !res.d.detail }, false);
				return;
			}
			add({ role: 'agent', text: res.d.reply || '' });
			var p = res.d.payment;
			if (p && /^https:\/\/(buy|checkout)\.stripe\.com\//.test(p.url || '')) {
				add({ role: 'pay', payment: { url: p.url, amount: p.amount, frequency: p.frequency, campaign_name: p.campaign_name } });
			}
		}).catch(function () {
			typing(false);
			add({ role: 'system', text: T.offline || 'Sorry, the assistant can’t be reached right now.', donate: true }, false);
		}).then(function () {
			sending = false;
			sendBtn.disabled = false;
			if (state.open) input.focus();
		});
	}

	// ---- open / close -------------------------------------------------------
	var lastFocus = null;
	function setOpen(open, focus) {
		state.open = open;
		save();
		panel.hidden = !open;
		launcher.setAttribute('aria-expanded', String(open));
		document.documentElement.classList.toggle('gatc-is-open', open);
		if (open) {
			scrollDown();
			if (focus !== false) input.focus();
		} else if (lastFocus && document.contains(lastFocus)) {
			lastFocus.focus();
		} else {
			launcher.focus();
		}
	}
	function openChat(e) {
		if (e) e.preventDefault();
		lastFocus = document.activeElement;
		setOpen(true);
	}

	launcher.addEventListener('click', function () {
		if (state.open) setOpen(false); else openChat();
	});
	closeBtn.addEventListener('click', function () { setOpen(false); });
	restartBtn.addEventListener('click', function () {
		state = { threadId: null, history: [], open: true };
		save();
		renderAll();
		input.focus();
	});
	panel.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') { e.stopPropagation(); setOpen(false); }
	});
	form.addEventListener('submit', function (e) {
		e.preventDefault();
		if (sending || !input.value.trim()) return; // keep the text until the reply arrives
		var v = input.value;
		input.value = '';
		input.style.height = 'auto';
		send(v);
	});
	input.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true })); }
	});
	input.addEventListener('input', function () {
		input.style.height = 'auto';
		input.style.height = Math.min(input.scrollHeight, 110) + 'px';
	});
	chips.addEventListener('click', function (e) {
		var c = e.target.closest('.gatc-chip');
		if (c) send(c.textContent);
	});
	// Any [data-gat-chat-open] element or link to #gat-chat opens the chat.
	document.addEventListener('click', function (e) {
		var t = e.target.closest && e.target.closest('[data-gat-chat-open], a[href="#gat-chat"]');
		if (t) openChat(e);
	});

	renderAll();
	if (location.hash === '#gat-chat') openChat();
	// Keep it open across pages on desktop; on phones (full screen) start closed.
	else if (state.open && window.matchMedia('(min-width: 641px)').matches) setOpen(true, false);
	else if (state.open) { state.open = false; save(); }
})();
