(function () {
	'use strict';

	var config = window.RSWUTMGrabber && typeof window.RSWUTMGrabber === 'object'
		? window.RSWUTMGrabber
		: {};

	var parameters = Array.isArray(config.parameters) && config.parameters.length
		? config.parameters
		: ['utm_campaign', 'utm_source', 'utm_medium', 'utm_term', 'utm_content', 'gclid', 'fbclid'];

	var cookieDays = parseInt(config.cookieDays, 10);
	if (!cookieDays || cookieDays < 1) {
		cookieDays = 30;
	} else if (cookieDays > 3650) {
		cookieDays = 3650;
	}

	var parameterLookup = Object.create(null);
	for (var i = 0; i < parameters.length; i++) {
		if (typeof parameters[i] === 'string' && parameters[i]) {
			parameterLookup[parameters[i].toLowerCase()] = parameters[i];
		}
	}

	function sanitizeValue(raw) {
		if (raw === null || raw === undefined) {
			return '';
		}
		var str = String(raw).replace(/[\u0000-\u001F\u007F]/g, '').trim();
		if (str.length > 1024) {
			str = str.slice(0, 1024);
		}
		return str;
	}

	function parseQueryParameters() {
		var found = Object.create(null);
		var search = window.location && typeof window.location.search === 'string'
			? window.location.search
			: '';

		if (!search || search.length < 2) {
			return found;
		}

		var rawQuery = search.charAt(0) === '?' ? search.slice(1) : search;

		function collectFromSearchParams(queryString) {
			try {
				var params = new URLSearchParams(queryString);
				params.forEach(function (value, key) {
					var normalizedKey = String(key).toLowerCase();
					if (Object.prototype.hasOwnProperty.call(parameterLookup, normalizedKey)) {
						found[parameterLookup[normalizedKey]] = sanitizeValue(value);
					}
				});
			} catch (e) {
				// Fallback manuale se URLSearchParams fallisce su query malformate.
				var pairs = queryString.split('&');
				for (var j = 0; j < pairs.length; j++) {
					var idx = pairs[j].indexOf('=');
					var rawKey = idx === -1 ? pairs[j] : pairs[j].slice(0, idx);
					var rawVal = idx === -1 ? '' : pairs[j].slice(idx + 1);
					try {
						var decodedKey = decodeURIComponent(rawKey.replace(/\+/g, ' ')).toLowerCase();
						if (Object.prototype.hasOwnProperty.call(parameterLookup, decodedKey)) {
							var decodedVal = decodeURIComponent(rawVal.replace(/\+/g, ' '));
							found[parameterLookup[decodedKey]] = sanitizeValue(decodedVal);
						}
					} catch (err) {
						// Ignora sequenze URI non valide.
					}
				}
			}
		}

		collectFromSearchParams(rawQuery);

		// Tollera query erroneamente codificate tutte insieme (?utm_source%3Dgoogle%26...)
		if (Object.keys(found).length === 0 && (/%3D/i.test(rawQuery) || /%26/i.test(rawQuery))) {
			try {
				var decodedQuery = decodeURIComponent(rawQuery);
				if (decodedQuery !== rawQuery && decodedQuery.indexOf('=') !== -1) {
					collectFromSearchParams(decodedQuery);
				}
			} catch (e) {
				// Ignora sequenze non decodificabili.
			}
		}

		return found;
	}

	function readCookie(name) {
		if (!document.cookie) {
			return '';
		}
		var cookies = document.cookie.split(';');
		for (var i = 0; i < cookies.length; i++) {
			var part = cookies[i].trim();
			var eqIdx = part.indexOf('=');
			if (eqIdx === -1) {
				continue;
			}
			var key = part.slice(0, eqIdx).trim();
			if (key === name) {
				var val = part.slice(eqIdx + 1);
				try {
					return sanitizeValue(decodeURIComponent(val));
				} catch (e) {
					return sanitizeValue(val);
				}
			}
		}
		return '';
	}

	function writeCookie(name, value) {
		var maxAge = value === '' ? 0 : cookieDays * 86400;
		var parts = [
			encodeURIComponent(name) + '=' + encodeURIComponent(value),
			'path=/',
			'max-age=' + maxAge,
			'SameSite=Lax'
		];

		if (window.location && window.location.protocol === 'https:') {
			parts.push('Secure');
		}

		document.cookie = parts.join('; ');
	}

	function syncCookiesFromUrl() {
		var urlValues = parseQueryParameters();
		var keys = Object.keys(urlValues);
		for (var i = 0; i < keys.length; i++) {
			writeCookie(keys[i], urlValues[keys[i]]);
		}
		return urlValues;
	}

	function getTrackingValues() {
		var urlValues = syncCookiesFromUrl();
		var resolved = Object.create(null);

		for (var i = 0; i < parameters.length; i++) {
			var param = parameters[i];
			if (Object.prototype.hasOwnProperty.call(urlValues, param)) {
				resolved[param] = urlValues[param];
			} else {
				resolved[param] = readCookie(param);
			}
		}

		return resolved;
	}

	function populateInputs(root) {
		var scope = root && typeof root.querySelectorAll === 'function' ? root : document;
		var inputs = scope.querySelectorAll('input[name]');
		if (!inputs.length) {
			return;
		}

		var values = getTrackingValues();

		for (var i = 0; i < inputs.length; i++) {
			var input = inputs[i];
			var rawName = input.getAttribute('name');
			if (!rawName) {
				continue;
			}

			var normalized = rawName.trim().toLowerCase();
			if (!Object.prototype.hasOwnProperty.call(parameterLookup, normalized)) {
				continue;
			}

			if (input.type && input.type.toLowerCase() !== 'hidden' && input.dataset && input.dataset.rswUtmEdited === '1') {
				continue;
			}

			var canonicalName = parameterLookup[normalized];
			var nextValue = Object.prototype.hasOwnProperty.call(values, canonicalName)
				? values[canonicalName]
				: '';

			if (input.value !== nextValue) {
				input.value = nextValue;
			}
		}
	}

	function init() {
		populateInputs(document);

		// Traccia modifiche manuali su eventuali input visibili.
		document.addEventListener('input', function (event) {
			var target = event.target;
			if (!target || target.tagName !== 'INPUT') {
				return;
			}
			var rawName = target.getAttribute('name');
			if (!rawName) {
				return;
			}
			var normalized = rawName.trim().toLowerCase();
			if (Object.prototype.hasOwnProperty.call(parameterLookup, normalized) && target.dataset) {
				target.dataset.rswUtmEdited = '1';
			}
		}, true);

		// Ricontrolla immediatamente prima del submit (in fase capture, prima di CF7).
		document.addEventListener('submit', function (event) {
			populateInputs(event.target || document);
		}, true);

		// Supporto eventi Contact Form 7 (re-popola dopo reset form post-invio).
		document.addEventListener('wpcf7reset', function (event) {
			populateInputs(event.target || document);
		});
		document.addEventListener('wpcf7mailsent', function (event) {
			window.setTimeout(function () {
				populateInputs(event.target || document);
			}, 0);
		});

		// Fallback per form ricostruiti dinamicamente, popup e navigazione client-side.
		if (typeof MutationObserver === 'function' && document.body) {
			var scheduled = false;
			var observer = new MutationObserver(function (mutations) {
				if (scheduled) {
					return;
				}
				for (var i = 0; i < mutations.length; i++) {
					if (mutations[i].addedNodes && mutations[i].addedNodes.length > 0) {
						scheduled = true;
						window.requestAnimationFrame(function () {
							scheduled = false;
							populateInputs(document);
						});
						break;
					}
				}
			});

			observer.observe(document.body, {
				childList: true,
				subtree: true
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
