(function () {
	'use strict';

	function init() {
		var config = window.lookitCfPurge;
		var dialog = document.getElementById('lookit-cf-dialog');
		var opener = document.querySelector('#wp-admin-bar-lookit-cf-purge-open a');
		if (!config || !dialog || !opener) return;

		var __ = window.wp.i18n.__;
		var quick = document.querySelector('#wp-admin-bar-lookit-cf-purge-page a');
		var notification = document.getElementById('lookit-cf-notification');
		var form = document.getElementById('lookit-cf-form');
		var controls = document.getElementById('lookit-cf-controls');
		var page = document.getElementById('lookit-cf-page');
		var address = document.getElementById('lookit-cf-address');
		var preview = document.getElementById('lookit-cf-preview');
		var warning = document.getElementById('lookit-cf-entire-warning');
		var back = document.getElementById('lookit-cf-back');
		var submit = document.getElementById('lookit-cf-submit');
		var footer = document.getElementById('lookit-cf-footer');
		var result = document.getElementById('lookit-cf-result');
		var entire = false;
		var busy = false;

		function scope() {
			return entire ? 'all' : form.querySelector('input[name="scope"]:checked').value;
		}

		function target(value, mode) {
			var url = new URL((value === undefined ? address.value : value).trim());
			mode = mode || scope();
			if (!/^https?:$/.test(url.protocol) || url.username || url.password || url.hostname !== new URL(config.homeUrl).hostname) {
				throw new Error(__('Enter an HTTP or HTTPS address on this site.', 'lookit-cf-purge'));
			}
			if (mode === 'prefix') {
				if (url.pathname === '/') throw new Error(__('This would purge every page on this hostname. Choose a narrower address or purge the entire site.', 'lookit-cf-purge'));
				var decoded = decodeURIComponent(url.pathname);
				if (url.port || /\/\/|\\|[\x00-\x1f\x7f]/.test(decoded) || /%2f|%3f|%23/i.test(url.pathname) || url.pathname.split('/').length - 1 > 31) {
					throw new Error(__('Use a standard HTTP or HTTPS address with a normalized path and at most 31 path separators.', 'lookit-cf-purge'));
				}
			}
			return url;
		}

		function update() {
			page.hidden = entire;
			page.disabled = entire;
			warning.hidden = !entire;
			back.hidden = !entire;
			footer.hidden = entire;
			preview.hidden = scope() !== 'prefix';
			submit.textContent = { url: __('Purge this URL', 'lookit-cf-purge'), prefix: __('Purge matching URLs', 'lookit-cf-purge'), all: __('Purge entire site', 'lookit-cf-purge') }[scope()];
			submit.disabled = busy;
			if (scope() === 'prefix') {
				try {
					var url = target();
					preview.textContent = __('Purges every URL starting with %s\nIncludes child pages, all query strings, and all cache variants.', 'lookit-cf-purge').replace('%s', url.host + url.pathname) + (url.pathname.endsWith('/') ? '' : '\n' + __('Also matches other names starting with this path, not just child pages.', 'lookit-cf-purge'));
				} catch (error) {
					preview.textContent = error.message;
					submit.disabled = true;
				}
			}
		}

		function report(message, error) {
			var output = dialog.open ? result : notification;
			output.textContent = message;
			output.setAttribute('role', error ? 'alert' : 'status');
			output.hidden = false;
		}

		opener.addEventListener('click', function (event) {
			event.preventDefault();
			if (busy || dialog.open) return;
			notification.hidden = true;
			entire = false;
			form.reset();
			address.value = config.currentUrl || '';
			result.hidden = true;
			update();
			dialog.showModal();
			address.focus();
		});
		form.addEventListener('change', function () { result.hidden = true; update(); });
		address.addEventListener('input', function () { result.hidden = true; update(); });
		document.getElementById('lookit-cf-entire').addEventListener('click', function () { entire = true; result.hidden = true; update(); back.focus(); });
		back.addEventListener('click', function () { entire = false; result.hidden = true; update(); address.focus(); });
		document.getElementById('lookit-cf-cancel').addEventListener('click', function () { if (!busy) dialog.close(); });
		dialog.addEventListener('cancel', function (event) { if (busy) event.preventDefault(); });

		if (quick) quick.addEventListener('click', function (event) {
			event.preventDefault();
			if (busy || !config.currentUrl) return;
			try { request('url', target(config.currentUrl, 'url').href); }
			catch (error) { report(error.message, true); }
		});

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			if (busy) return;
			var url;
			if (!entire) {
				try { url = target().href; }
				catch (error) { report(error.message, true); return; }
			}
			request(scope(), url);
		});

		async function request(mode, url) {
			var body = new URLSearchParams({ action: 'lookit_cf_purge_' + mode, nonce: config.nonce });
			if (url) body.set('url', url);
			busy = true;
			controls.disabled = true;
			if (quick) { quick.setAttribute('aria-disabled', 'true'); quick.textContent = __('Purging…', 'lookit-cf-purge'); }
			submit.textContent = __('Purging…', 'lookit-cf-purge');
			report(__('Sending request to Cloudflare…', 'lookit-cf-purge'), false);
			var controller = new AbortController();
			var timeout = setTimeout(function () { controller.abort(); }, 20000);
			try {
				var response = await fetch(config.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin', signal: controller.signal });
				var data = await response.json();
				if (!response.ok || data.success !== true) throw new Error(typeof data.data === 'string' ? data.data : __('Cloudflare rejected the request.', 'lookit-cf-purge'));
				report(__('Cloudflare accepted the cache purge request.', 'lookit-cf-purge'), false);
			} catch (error) {
				report(error.name === 'AbortError' ? __('The request timed out. Cloudflare may have received it; check before retrying.', 'lookit-cf-purge') : error.message, true);
			} finally {
				clearTimeout(timeout);
				busy = false;
				controls.disabled = false;
				if (quick) { quick.removeAttribute('aria-disabled'); quick.textContent = __('Purge this page', 'lookit-cf-purge'); }
				update();
			}
		}
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
