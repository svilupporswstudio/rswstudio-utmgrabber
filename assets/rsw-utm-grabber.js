(function () {
	'use strict';

	var cfg = window.RSWUTMGrabber && typeof window.RSWUTMGrabber === 'object'
		? window.RSWUTMGrabber
		: {};

	var parameters = Array.isArray(cfg.parameters) && cfg.parameters.length
		? cfg.parameters
		: ['utm_campaign', 'utm_source', 'utm_medium', 'utm_term', 'utm_content', 'gclid', 'fbclid'];

	var cookieDays = parseInt(cfg.cookieDays, 10);
	if (!cookieDays || cookieDays < 1) {
		cookieDays = 30;
	} else if (cookieDays > 3650) {
		cookieDays = 3650;
	}
	var maxAge = cookieDays * 24 * 60 * 60;

	function getSearchParams() {
		var params = new URLSearchParams(window.location.search);
		var found = parameters.some(function (name) { return params.has(name); });

		if (found) {
			return params;
		}

		/* Fallback per query tutta URL-encoded. */
		var raw = window.location.search.replace(/^\?/, '');
		if (!raw || !/%3D|%26/i.test(raw)) {
			return params;
		}

		try {
			var decoded = decodeURIComponent(raw);
			if (decoded !== raw && decoded.indexOf('=') !== -1) {
				return new URLSearchParams(decoded);
			}
		} catch (e) {}

		return params;
	}

	function setCookie(name, value) {
		var effectiveMaxAge = value === '' ? 0 : maxAge;
		var cookie = encodeURIComponent(name) + '=' + encodeURIComponent(value)
			+ '; Max-Age=' + effectiveMaxAge
			+ '; Path=/'
			+ '; SameSite=Lax';

		if (window.location.protocol === 'https:') {
			cookie += '; Secure';
		}

		document.cookie = cookie;
	}

	function getCookie(name) {
		var prefix = encodeURIComponent(name) + '=';
		var parts = document.cookie ? document.cookie.split('; ') : [];

		for (var i = 0; i < parts.length; i++) {
			if (parts[i].indexOf(prefix) === 0) {
				try {
					return decodeURIComponent(parts[i].substring(prefix.length));
				} catch (e) {
					return '';
				}
			}
		}

		return null;
	}

	function resolveValues() {
		var search = getSearchParams();
		var values = Object.create(null);

		parameters.forEach(function (name) {
			if (search.has(name)) {
				values[name] = (search.get(name) || '').substring(0, 1024);
				setCookie(name, values[name]);
			} else {
				var cookieValue = getCookie(name);
				values[name] = cookieValue === null ? '' : String(cookieValue).substring(0, 1024);
			}
		});

		return values;
	}

	function populateInputs(root) {
		var values = resolveValues();
		var scope = root && typeof root.querySelectorAll === 'function' ? root : document;

		parameters.forEach(function (name) {
			var fields = scope.querySelectorAll('input[name="' + name + '"]');
			for (var i = 0; i < fields.length; i++) {
				fields[i].value = values[name];
				fields[i].setAttribute('value', values[name]);
			}
		});
	}

	function stripWrapperText(root) {
		if (!root || typeof document.createTreeWalker !== 'function' || typeof NodeFilter === 'undefined') {
			return;
		}

		var re = /\[\/?(?:utm_campaign_i|utm_source_i|utm_medium_i|utm_term_i|utm_content_i|gclid_i|fbclid_i)\]/gi;
		var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
		var ignoredTags = { SCRIPT: true, STYLE: true, TEXTAREA: true, PRE: true, CODE: true };
		var nodes = [];
		var node;

		while ((node = walker.nextNode())) {
			var parentTag = node.parentNode && node.parentNode.nodeName;
			if (parentTag && ignoredTags[parentTag]) {
				continue;
			}
			if (node.nodeValue && node.nodeValue.indexOf('_i]') !== -1) {
				nodes.push(node);
			}
		}

		nodes.forEach(function (textNode) {
			textNode.nodeValue = textNode.nodeValue.replace(re, '');
		});
	}

	function process() {
		populateInputs(document);
		stripWrapperText(document.body || document.documentElement);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', process);
	} else {
		process();
	}

	/* Ricontrolla immediatamente prima dell'invio. */
	document.addEventListener('submit', function (event) {
		if (event.target && typeof event.target.querySelectorAll === 'function') {
			populateInputs(event.target);
		}
	}, true);

	/* CF7 può ricostruire il form via JS. */
	document.addEventListener('wpcf7init', process);
	document.addEventListener('wpcf7reset', process);

	if (window.MutationObserver) {
		var timer = null;
		var observer = new MutationObserver(function () {
			window.clearTimeout(timer);
			timer = window.setTimeout(process, 30);
		});

		var startObserver = function () {
			if (document.body) {
				observer.observe(document.body, { childList: true, subtree: true });
			}
		};

		if (document.body) {
			startObserver();
		} else {
			document.addEventListener('DOMContentLoaded', startObserver);
		}
	}
})();
