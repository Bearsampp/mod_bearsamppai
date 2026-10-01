/**
 * Bearsampp AI module - floating AI chat widget.
 *
 * @author      Bearsampp
 * @copyright   (C) 2026 Bearsampp
 * @license     GNU General Public License version 3; see LICENSE
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'mod_bearsamppai_theme';
	var mqDark = window.matchMedia('(prefers-color-scheme: dark)');

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	function escapeHtml(value) {
		const div = document.createElement('div');
		div.textContent = String(value || '');
		return div.innerHTML;
	}

	function renderMarkdown(value) {
		let out = escapeHtml(value);

		// Fenced code blocks.
		out = out.replace(/```([\s\S]*?)```/g, function (match, code) {
			return '<pre class="mod-bearsamppai__code">' + code.replace(/\n/g, '<br>') + '</pre>';
		});

		// Inline code.
		out = out.replace(/`([^`]+)`/g, '<code>$1</code>');

		// Markdown links (http/https only).
		out = out.replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

		// Bold.
		out = out.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

		// Italic.
		out = out.replace(/\*([^*]+)\*/g, '<em>$1</em>');

		// Line breaks.
		out = out.replace(/\n/g, '<br>');

		return out;
	}

	function timeNow() {
		const d = new Date();
		const hh = String(d.getHours()).padStart(2, '0');
		const mm = String(d.getMinutes()).padStart(2, '0');
		const ss = String(d.getSeconds()).padStart(2, '0');
		return hh + ':' + mm + ':' + ss;
	}

	function scrollToBottom(container) {
		container.scrollTop = container.scrollHeight;
	}

	function addBubble(container, text, role) {
		const wrap = document.createElement('div');
		wrap.className = 'mod-bearsamppai__msg mod-bearsamppai__msg--' + role;
		wrap.setAttribute('data-time', timeNow());

		const inner = document.createElement('div');
		inner.className = 'mod-bearsamppai__msg-bubble';
		inner.innerHTML = (role === 'user') ? escapeHtml(text) : renderMarkdown(text);

		wrap.appendChild(inner);
		container.appendChild(wrap);
		scrollToBottom(container);

		return wrap;
	}

	function addSources(bubble, sources) {
		const list = document.createElement('ul');

		list.className = 'mod-bearsamppai__sources';

		sources.forEach(function (source) {
			if (!source || typeof source.url !== 'string' || source.url === '') {
				return;
			}

			const item = document.createElement('li');
			const link = document.createElement('a');

			link.className = 'mod-bearsamppai__source';
			link.href = source.url;
			link.textContent = String(source.title || source.url);
			link.target = '_blank';
			link.rel = 'noopener noreferrer';

			item.appendChild(link);
			list.appendChild(item);
		});

		if (list.childNodes.length === 0) {
			return;
		}

		bubble.appendChild(list);
	}

	function addTyping(container) {
		const wrap = document.createElement('div');
		wrap.className = 'mod-bearsamppai__msg mod-bearsamppai__msg--assistant';
		wrap.setAttribute('data-typing', '1');

		const inner = document.createElement('div');
		inner.className = 'mod-bearsamppai__msg-bubble mod-bearsamppai__msg-bubble--typing';
		inner.textContent = '…';

		wrap.appendChild(inner);
		container.appendChild(wrap);
		scrollToBottom(container);

		return wrap;
	}

	function sendAsk(endpoint, moduleId, message) {
		return fetch(endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
			},
			body: 'module_id=' + encodeURIComponent(moduleId)
				+ '&message=' + encodeURIComponent(message)
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}

				return response.json();
			})
			.then(function (json) {
				const data = json && typeof json.data === 'object' ? json.data : (json || {});

				if (data && data.success === true && typeof data.answer === 'string' && data.answer !== '') {
					return { answer: data.answer, sources: Array.isArray(data.sources) ? data.sources : [] };
				}

				if (data && typeof data.error === 'string' && data.error !== '') {
					return { error: data.error };
				}

				return { error: 'Unexpected response' };
			});
	}

	function announce(root, text) {
		const region = root.querySelector('[data-bearsamppai-chat-announce]');

		if (region) {
			region.textContent = '';
			window.setTimeout(function () {
				region.textContent = text;
			}, 50);
		}
	}

	function initToggle(root) {
		const toggle = root.querySelector('[data-bearsamppai-chat-toggle]');
		const panel = root.querySelector('[data-bearsamppai-chat-panel]');
		const close = root.querySelector('[data-bearsamppai-chat-close]');
		const input = root.querySelector('[data-bearsamppai-chat-input]');

		if (!toggle || !panel) {
			return;
		}

		function setOpen(open) {
			panel.hidden = !open;
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			root.classList.toggle('is-open', open);

			if (open) {
				if (input) {
					input.focus();
				}
			} else if (document.activeElement === input || panel.contains(document.activeElement)) {
				toggle.focus();
			}
		}

		toggle.addEventListener('click', function () {
			setOpen(panel.hidden);
		});

		if (close) {
			close.addEventListener('click', function () {
				setOpen(false);
			});
		}

		root.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !panel.hidden) {
				setOpen(false);
			}
		});

		root.__bearsamppaiOpen = setOpen;
	}

	function initForm(root) {
		const form = root.querySelector('[data-bearsamppai-chat-form]');
		const messages = root.querySelector('[data-bearsamppai-chat-messages]');
		const input = root.querySelector('[data-bearsamppai-chat-input]');
		const send = root.querySelector('[data-bearsamppai-chat-send]');
		const moduleId = root.getAttribute('data-module-id');
		const endpoint = root.getAttribute('data-endpoint');

		if (!form || !messages || !input) {
			return;
		}

		function setBusy(busy) {
			input.disabled = busy;
			messages.setAttribute('aria-busy', busy ? 'true' : 'false');
			if (send) {
				send.disabled = busy;
			}
		}

		form.addEventListener('submit', function (event) {
			event.preventDefault();

			const message = input.value.trim();

			if (message === '') {
				return;
			}

			addBubble(messages, message, 'user');
			input.value = '';
			setBusy(true);

			const typing = addTyping(messages);

			sendAsk(endpoint, moduleId, message)
				.then(function (data) {
					messages.removeChild(typing);
					const bubble = addBubble(messages, data.answer || data.error || 'No response', 'assistant');

					// Source pages the answer was drawn from, so a visitor can open
					// the original article rather than trusting the summary.
					if (Array.isArray(data.sources) && data.sources.length) {
						addSources(bubble, data.sources);
					}
				})
				.catch(function (err) {
					messages.removeChild(typing);
					addBubble(messages, String(err && err.message ? err.message : err), 'assistant');
				})
				.finally(function () {
					setBusy(false);
					input.focus();
				});
		});
	}

	function storedTheme() {
		try {
			const v = localStorage.getItem(STORAGE_KEY);
			return v === 'light' || v === 'dark' ? v : null;
		} catch (e) {
			return null;
		}
	}

	function forcedTheme(root) {
		const t = root.getAttribute('data-theme');
		return t === 'light' || t === 'dark' ? t : 'auto';
	}

	function computeScheme(root, stored) {
		if (stored) {
			return stored;
		}

		const forced = forcedTheme(root);

		if (forced === 'light') {
			return 'light';
		}

		if (forced === 'dark') {
			return 'dark';
		}

		return mqDark.matches ? 'dark' : 'light';
	}

	function applyScheme(root, scheme) {
		root.setAttribute('data-theme-scheme', scheme);

		const btn = root.querySelector('[data-bearsamppai-chat-theme]');

		if (btn) {
			btn.setAttribute('aria-pressed', scheme === 'dark' ? 'true' : 'false');
		}
	}

	function initTheme(root) {
		const btn = root.querySelector('[data-bearsamppai-chat-theme]');

		applyScheme(root, computeScheme(root, storedTheme()));

		function onMqChange() {
			if (forcedTheme(root) === 'auto' && !storedTheme()) {
				applyScheme(root, computeScheme(root, null));
			}
		}

		if (mqDark.addEventListener) {
			mqDark.addEventListener('change', onMqChange);
		} else if (mqDark.addListener) {
			mqDark.addListener(onMqChange);
		}

		if (!btn) {
			return;
		}

		btn.addEventListener('click', function () {
			const current = root.getAttribute('data-theme-scheme') === 'dark' ? 'dark' : 'light';
			const next = current === 'dark' ? 'light' : 'dark';

			try {
				localStorage.setItem(STORAGE_KEY, next);
			} catch (e) {}

			applyScheme(root, next);
			announce(root, next === 'dark' ? 'Dark theme enabled' : 'Light theme enabled');
		});
	}

	function copyText(text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}

		return new Promise(function (resolve, reject) {
			const ta = document.createElement('textarea');
			ta.value = text;
			ta.setAttribute('readonly', '');
			ta.style.position = 'absolute';
			ta.style.left = '-9999px';
			document.body.appendChild(ta);
			ta.select();

			try {
				document.execCommand('copy');
				resolve();
			} catch (err) {
				reject(err);
			} finally {
				document.body.removeChild(ta);
			}
		});
	}

	function initStatus(root) {
		const el = root.querySelector('[data-bearsamppai-chat-status]');

		if (!el) {
			return;
		}

		const moduleId = root.getAttribute('data-module-id');
		const endpoint = (root.getAttribute('data-endpoint') || '').replace(/method=ask/, 'method=ping');
		const interval = Math.max(10, parseInt(el.getAttribute('data-status-interval') || '30', 10) || 30);

		const labels = {
			online: el.getAttribute('data-status-online') || 'Connected',
			offline: el.getAttribute('data-status-offline') || 'Offline',
			checking: el.getAttribute('data-status-checking') || 'Checking connection...'
		};

		function setState(state) {
			el.classList.remove('is-online', 'is-offline', 'is-checking');
			el.classList.add('is-' + state);
			el.setAttribute('title', labels[state] || '');
			el.setAttribute('aria-label', labels[state] || '');
		}

		function check() {
			if (document.hidden || !navigator.onLine) {
				setState(document.hidden ? 'checking' : 'offline');
				return;
			}

			setState('checking');

			const url = endpoint + '&module_id=' + encodeURIComponent(moduleId) + '&_=' + Date.now();

			fetch(url, { method: 'GET' })
				.then(function (response) {
					return response.json();
				})
				.then(function (json) {
					const data = json && typeof json.data === 'object' ? json.data : (json || {});
					setState(data && data.success === true ? 'online' : 'offline');
				})
				.catch(function () {
					setState('offline');
				});
		}

		setState('checking');
		check();

		window.setInterval(check, interval * 1000);

		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) {
				check();
			}
		});
	}

	function initCopy(root) {
		const btn = root.querySelector('[data-bearsamppai-chat-copy]');

		if (!btn) {
			return;
		}

		const originalLabel = btn.getAttribute('aria-label');

		btn.addEventListener('click', function () {
			const messages = root.querySelector('[data-bearsamppai-chat-messages]');

			if (!messages) {
				return;
			}

			const titleEl = root.querySelector('.mod-bearsamppai__chat-title');
			const assistantName = titleEl ? titleEl.textContent.trim() : 'AI';
			const parts = [];
			const nodes = messages.children;

			for (let i = 0; i < nodes.length; i++) {
				const msg = nodes[i];

				if (msg.getAttribute('data-typing')) {
					continue;
				}

				const role = msg.classList.contains('mod-bearsamppai__msg--user') ? 'You' : assistantName;
				const time = msg.getAttribute('data-time') || '';
				const bubble = msg.querySelector('.mod-bearsamppai__msg-bubble');
				const text = bubble ? bubble.innerText.trim() : '';

				if (text === '') {
					continue;
				}

				parts.push(role + (time !== '' ? ' (' + time + ')' : '') + '\n' + text);
			}

			if (parts.length === 0) {
				announce(root, 'Nothing to copy yet');
				return;
			}

			copyText(parts.join('\n\n'))
				.then(function () {
					btn.setAttribute('aria-label', 'Copied');
					announce(root, 'Conversation copied to clipboard');
					window.setTimeout(function () {
						btn.setAttribute('aria-label', originalLabel);
					}, 1500);
				})
				.catch(function () {
					announce(root, 'Could not copy conversation');
				});
		});
	}

	ready(function () {
		const chats = Array.prototype.slice.call(document.querySelectorAll('[data-bearsamppai-chat]'));

		chats.forEach(function (root) {
			initToggle(root);
			initForm(root);
			initTheme(root);
			initStatus(root);
			initCopy(root);
		});

		document.addEventListener('keydown', function (event) {
			if ((event.ctrlKey || event.metaKey) && event.key === '/') {
				event.preventDefault();

				for (let i = 0; i < chats.length; i++) {
					const root = chats[i];

					if (root.__bearsamppaiOpen) {
						root.__bearsamppaiOpen(true);
						break;
					}
				}
			}
		});
	});
})();