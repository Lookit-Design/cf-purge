const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { execFileSync } = require('node:child_process');
const { resolve } = require('node:path');
const { JSDOM } = require('jsdom');

const markup = execFileSync('php', ['-r', `
define('ABSPATH', __DIR__);
function current_user_can($capability) { return true; }
function esc_html_e($text, $domain) { echo htmlspecialchars($text, ENT_QUOTES); }
require $argv[1];
Lookit_CF_Purge_Admin_Bar::render_dialog();
`, resolve('includes/class-lookit-cf-purge-admin-bar.php')], { encoding: 'utf8' });
const script = readFileSync('assets/admin.js', 'utf8');

function setup(t, options = {}) {
	const dom = new JSDOM('<div id="wp-admin-bar-lookit-cf-purge-page"><a href="#">Direct action</a></div><div id="wp-admin-bar-lookit-cf-purge-open"><a href="#lookit-cf-dialog">Open</a></div>' + markup, { url: 'https://example.com/news/', runScripts: 'outside-only' });
	t.after(() => dom.window.close());
	const w = dom.window;
	Object.defineProperty(w.document, 'readyState', { value: 'complete' });
	w.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
	w.HTMLDialogElement.prototype.close = function () { this.open = false; };
	w.lookitCfPurge = { ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php', homeUrl: 'https://example.com/', currentUrl: options.currentUrl ?? 'https://example.com/news/', nonce: 'test-nonce' };
	const calls = [];
	w.fetch = async (url, args) => {
		calls.push({ url, args, body: new URLSearchParams(args.body.toString()) });
		return options.fetch ? options.fetch(url, args) : { ok: true, json: async () => ({ success: true }) };
	};
	w.wp = { i18n: { __: text => text } };
	w.eval(script);
	const get = id => w.document.getElementById('lookit-cf-' + id);
	const click = id => get(id).click();
	const open = () => w.document.querySelector('#wp-admin-bar-lookit-cf-purge-open a').click();
	const input = value => { get('address').value = value; get('address').dispatchEvent(new w.Event('input', { bubbles: true })); };
	const scope = value => { const el = w.document.querySelector(`input[name="scope"][value="${value}"]`); el.checked = true; el.dispatchEvent(new w.Event('change', { bubbles: true })); };
	const submit = () => get('form').dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true }));
	return { w, calls, get, click, open, input, scope, submit };
}
const flush = () => new Promise(resolve => setImmediate(resolve));

test('opening prefills the page; cancelling sends nothing and reopening resets scope', t => {
	const ui = setup(t);
	ui.open();
	assert.equal(ui.get('dialog').open, true);
	assert.equal(ui.get('address').value, 'https://example.com/news/');
	ui.scope('prefix');
	assert.equal(ui.w.document.querySelector('[name="scope"]:checked').value, 'prefix');
	ui.click('cancel');
	assert.equal(ui.get('dialog').open, false);
	assert.equal(ui.calls.length, 0);
	ui.open();
	assert.equal(ui.w.document.querySelector('[name="scope"]:checked').value, 'url');
	assert.equal(ui.get('result').hidden, true);
});

for (const scope of ['url', 'prefix']) {
	test(`${scope} submits the edited same-site address and nonce to its own endpoint`, async t => {
		const ui = setup(t); ui.open(); ui.scope(scope); ui.input('https://EXAMPLE.com/News/?lang=fr');
		ui.submit(); await flush();
		assert.equal(ui.calls.length, 1);
		assert.equal(ui.calls[0].url, ui.w.lookitCfPurge.ajaxUrl);
		assert.equal(ui.calls[0].body.get('action'), 'lookit_cf_purge_' + scope);
		assert.equal(ui.calls[0].body.get('url'), 'https://example.com/News/?lang=fr');
		assert.equal(ui.calls[0].body.get('nonce'), 'test-nonce');
		assert.equal(ui.calls[0].args.credentials, 'same-origin');
		assert.equal(ui.get('controls').disabled, false);
		assert.equal(ui.get('result').getAttribute('role'), 'status');
	});
}

test('whole-site clearing requires entering its warning step; going back does not send a request', async t => {
	const ui = setup(t); ui.open(); ui.scope('prefix'); ui.input('https://example.com/other/');
	ui.click('entire');
	assert.equal(ui.calls.length, 0);
	assert.equal(ui.get('page').disabled, true);
	assert.equal(ui.get('entire-warning').hidden, false);
	ui.click('back');
	assert.equal(ui.get('address').value, 'https://example.com/other/');
	assert.equal(ui.w.document.querySelector('[name="scope"]:checked').value, 'prefix');
	assert.equal(ui.calls.length, 0);
	ui.click('entire'); ui.submit(); await flush();
	assert.equal(ui.calls[0].body.get('action'), 'lookit_cf_purge_all');
	assert.equal(ui.calls[0].body.has('url'), false);
});

test('the whole-site warning does not depend on a valid page address', async t => {
	const ui = setup(t, { currentUrl: '' }); ui.open(); ui.click('entire'); ui.submit(); await flush();
	assert.equal(ui.calls.length, 1);
	assert.equal(ui.calls[0].body.get('action'), 'lookit_cf_purge_all');
});

for (const address of ['', 'https://other.com/news/', 'javascript:alert(1)', 'https://user@example.com/news/']) {
	test(`invalid or foreign target sends no request: ${address}`, t => {
		const ui = setup(t); ui.open(); ui.input(address); ui.submit();
		assert.equal(ui.calls.length, 0);
		assert.equal(ui.get('result').getAttribute('role'), 'alert');
	});
}

for (const address of ['https://example.com/', 'https://example.com/?page=2', 'https://example.com/news/../', 'https://example.com//', 'https://example.com/%2f', 'https://example.com:8443/news/', 'https://example.com/' + 'a/'.repeat(31)]) {
	test(`broad scope blocks unsafe targets: ${address}`, t => {
		const ui = setup(t); ui.open(); ui.scope('prefix'); ui.input(address);
		assert.equal(ui.get('submit').disabled, true);
		ui.submit(); assert.equal(ui.calls.length, 0);
	});
}

test('editing an invalid broad target restores submit, and switching to exact allows the homepage', t => {
	const ui = setup(t); ui.open(); ui.scope('prefix'); ui.input('https://example.com/');
	assert.equal(ui.get('submit').disabled, true);
	ui.input('https://example.com/news/'); assert.equal(ui.get('submit').disabled, false);
	ui.input('https://example.com/'); ui.scope('url'); assert.equal(ui.get('submit').disabled, false);
});

test('pending requests prevent duplicate submission and Escape dismissal; controls recover on completion', async t => {
	let complete;
	const ui = setup(t, { fetch: () => new Promise(resolve => { complete = resolve; }) });
	ui.open(); ui.submit(); ui.submit();
	assert.equal(ui.calls.length, 1);
	assert.equal(ui.get('controls').disabled, true);
	const cancel = new ui.w.Event('cancel', { cancelable: true }); ui.get('dialog').dispatchEvent(cancel);
	assert.equal(cancel.defaultPrevented, true);
	complete({ ok: true, json: async () => ({ success: true }) }); await flush();
	assert.equal(ui.get('controls').disabled, false);
	ui.click('cancel'); assert.equal(ui.get('dialog').open, false);
});

test('server errors are rendered as text; retry preserves the selected scope', async t => {
	const message = '<img src=x onerror=alert(1)>';
	const ui = setup(t, { fetch: async () => ({ ok: true, json: async () => ({ success: false, data: message }) }) });
	ui.open(); ui.scope('prefix'); ui.submit(); await flush();
	assert.equal(ui.get('result').textContent, message);
	assert.equal(ui.get('result').querySelector('img'), null);
	assert.equal(ui.get('result').getAttribute('role'), 'alert');
	assert.equal(ui.get('controls').disabled, false);
	ui.submit(); await flush();
	assert.equal(ui.calls.length, 2);
	assert.equal(ui.calls[1].body.get('action'), 'lookit_cf_purge_prefix');
});

for (const fetch of [async () => { throw new Error('Network failed'); }, async () => ({ ok: true, json: async () => { throw new SyntaxError('Invalid JSON'); } }), async () => ({ ok: false, json: async () => ({ success: true }) })]) {
	test('transport and malformed-response failures restore controls without fallback requests', async t => {
		const ui = setup(t, { fetch }); ui.open(); ui.submit(); await flush();
		assert.equal(ui.calls.length, 1);
		assert.equal(ui.get('controls').disabled, false);
		assert.equal(ui.get('result').getAttribute('role'), 'alert');
	});
}

test('timeout aborts the request and restores the dialog without automatically retrying', async t => {
	const ui = setup(t, { fetch: (_url, args) => new Promise((_resolve, reject) => args.signal.addEventListener('abort', () => reject(new ui.w.DOMException('', 'AbortError')))) });
	let expire;
	ui.w.setTimeout = callback => { expire = callback; return 1; };
	ui.w.clearTimeout = () => {};
	ui.open(); ui.submit(); expire(); await flush();
	assert.equal(ui.calls.length, 1);
	assert.equal(ui.calls[0].args.signal.aborted, true);
	assert.equal(ui.get('controls').disabled, false);
	assert.equal(ui.get('result').getAttribute('role'), 'alert');
});

test('one-click action purges the current page without opening or confirming the dialog', async t => {
	const ui = setup(t);
	ui.w.confirm = () => assert.fail('Direct purge must not require confirmation');
	ui.open(); ui.input('https://example.com/another/'); ui.scope('prefix'); ui.click('cancel');
	ui.w.document.querySelector('#wp-admin-bar-lookit-cf-purge-page a').click(); await flush();
	assert.equal(ui.get('dialog').open, false);
	assert.equal(ui.calls.length, 1);
	assert.equal(ui.calls[0].body.get('action'), 'lookit_cf_purge_url');
	assert.equal(ui.calls[0].body.get('url'), 'https://example.com/news/');
	assert.equal(ui.calls[0].body.get('nonce'), 'test-nonce');
	assert.equal(ui.get('notification').hidden, false);
	assert.equal(ui.get('notification').getAttribute('role'), 'status');
});

test('direct purge shares the pending-request lock with the dialog', async t => {
	let complete;
	const ui = setup(t, { fetch: () => new Promise(resolve => { complete = resolve; }) });
	const quick = ui.w.document.querySelector('#wp-admin-bar-lookit-cf-purge-page a');
	quick.click(); quick.click(); ui.open(); ui.submit();
	assert.equal(ui.calls.length, 1);
	assert.equal(ui.get('dialog').open, false);
	assert.equal(quick.getAttribute('aria-disabled'), 'true');
	complete({ ok: true, json: async () => ({ success: true }) }); await flush();
	assert.equal(quick.hasAttribute('aria-disabled'), false);
	ui.open(); assert.equal(ui.get('dialog').open, true);
});

test('direct purge errors remain text-only outside the dialog and allow retry', async t => {
	const message = '<img src=x onerror=alert(1)>';
	const ui = setup(t, { fetch: async () => ({ ok: false, json: async () => ({ success: false, data: message }) }) });
	const quick = ui.w.document.querySelector('#wp-admin-bar-lookit-cf-purge-page a');
	quick.click(); await flush();
	assert.equal(ui.get('notification').getAttribute('role'), 'alert');
	assert.equal(ui.get('notification').textContent, message);
	assert.equal(ui.get('notification').querySelector('img'), null);
	assert.equal(ui.get('dialog').open, false);
	quick.click(); await flush(); assert.equal(ui.calls.length, 2);
});

test('direct action cannot submit a missing current page', t => {
	const ui = setup(t, { currentUrl: '' });
	ui.w.document.querySelector('#wp-admin-bar-lookit-cf-purge-page a').click();
	assert.equal(ui.calls.length, 0);
});
