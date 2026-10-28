const { JSDOM } = require('jsdom');
const nodeAssert = require('assert');
const plain = (v) => JSON.parse(JSON.stringify(v));
const assert = Object.assign((...a) => nodeAssert(...a), nodeAssert, {
    deepStrictEqual: (actual, expected, msg) => nodeAssert.deepStrictEqual(plain(actual), plain(expected), msg),
});
const fs = require('fs');
const path = require('path');
// Behaviour tests for public/js/admin-tag-input.js against a real (jsdom) DOM.
// Run:  npm install --no-save jsdom   then   node tests/js/tag-input.test.cjs
const src = fs.readFileSync(path.join(__dirname, '..', '..', 'public', 'js', 'admin-tag-input.js'), 'utf8');

let passed = 0, failed = 0;
const pending = [];
function test(name, fn) { pending.push({ name, fn }); }

function setup(initial = [], opts = {}) {
    const hidden = initial.map(n => `<input type="hidden" name="tags[]" value="${n.replace(/"/g, '&quot;')}">`).join('');
    const dom = new JSDOM(`<!DOCTYPE html><form id="f" action="/x" method="post">
      <div id="tag-field" data-suggest-url="/admin/tags/suggest" data-max="${opts.max || 10}" data-max-length="50">
        <div data-role="chips"></div>
        <input type="text" data-role="input" name="tag_input" list="tag-suggestions">
        <datalist id="tag-suggestions"></datalist>
        ${hidden}
      </div>
      <button id="go" type="submit">save</button></form>`, { runScripts: 'outside-only', pretendToBeVisual: true });
    const w = dom.window;
    const calls = [];
    const fetchStub = opts.fetch || ((url) => { calls.push(url); return Promise.resolve({ ok: true, json: () => Promise.resolve({ tags: [] }) }); });
    w.eval(src);
    const api = w.AdminTagInput.init(w.document.getElementById('tag-field'), { fetch: fetchStub, debounce: 0 });
    const input = w.document.querySelector('[data-role="input"]');
    const form = w.document.getElementById('f');
    return {
        w, api, input, form, calls,
        chips: () => [...form.querySelectorAll('[data-chip] > span:first-child')].map(e => e.textContent),
        names: () => api.names(),
        hiddenValues: () => [...form.querySelectorAll('input[type="hidden"][name="tags[]"]')].map(e => e.value),
        key(k) { const e = new w.KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true }); input.dispatchEvent(e); return e; },
        type(text) { input.value = text; input.dispatchEvent(new w.Event('input', { bubbles: true })); },
    };
}

test('rendered chips and hidden fields always agree with the internal state', () => {
    const t = setup(['এক']);
    for (const n of ['দুই', 'তিন', 'দুই']) { t.input.value = n; t.key('Enter'); }
    t.w.document.querySelectorAll('[data-chip] button')[0].click();
    assert.deepStrictEqual(t.chips(), t.names());
    assert.deepStrictEqual(t.hiddenValues(), t.names());
    assert.deepStrictEqual(t.names(), ['দুই', 'তিন']);
});

test('Enter turns the typed text into a chip with a hidden form field, and does not submit the form', () => {
    const t = setup();
    let submitted = false; t.form.addEventListener('submit', e => { submitted = true; e.preventDefault(); });
    t.input.value = 'রাজনীতি';
    const e = t.key('Enter');
    assert.strictEqual(e.defaultPrevented, true, 'Enter must be prevented');
    assert.strictEqual(submitted, false);
    assert.deepStrictEqual(t.chips(), ['রাজনীতি']);
    assert.deepStrictEqual(t.hiddenValues(), ['রাজনীতি']);
    assert.strictEqual(t.input.value, '');
});

test('comma (ASCII and Arabic) also confirms a tag', () => {
    const t = setup();
    t.input.value = 'এক'; t.key(',');
    t.input.value = 'দুই'; t.key('\u060C');
    assert.deepStrictEqual(t.chips(), ['এক', 'দুই']);
});

test('pasting "a, b, c" creates three chips at once', () => {
    const t = setup();
    t.type('খেলা, ক্রিকেট ، বিপিএল');
    assert.deepStrictEqual(t.chips(), ['খেলা', 'ক্রিকেট', 'বিপিএল']);
    assert.strictEqual(t.input.value, '');
});

test('initial server-rendered tags become chips and the old hidden inputs are not duplicated', () => {
    const t = setup(['ঢাকা', 'নির্বাচন']);
    assert.deepStrictEqual(t.chips(), ['ঢাকা', 'নির্বাচন']);
    assert.deepStrictEqual(t.hiddenValues(), ['ঢাকা', 'নির্বাচন']);
});

test('duplicates are ignored, case-insensitively and across য় spellings', () => {
    const t = setup();
    for (const n of ['Dhaka', 'dhaka', 'DHAKA', 'জাতী\u09DF', 'জাতী\u09AF\u09BC']) { t.input.value = n; t.key('Enter'); }
    assert.strictEqual(t.chips().length, 2);
});

test('the ten-tag limit is enforced', () => {
    const t = setup([], { max: 3 });
    for (const n of ['এ', 'বি', 'সি', 'ডি', 'ই']) { t.input.value = n; t.key('Enter'); }
    assert.deepStrictEqual(t.chips(), ['এ', 'বি', 'সি']);
    assert.ok(t.input.placeholder.includes('সর্বোচ্চ'));
});

test('a tag longer than the limit is cut, never rejected silently into nothing', () => {
    const t = setup();
    t.input.value = 'অ'.repeat(80); t.key('Enter');
    assert.strictEqual(t.chips()[0].length, 50);
});

test('Backspace on an empty input removes the last chip; on a non-empty input it does nothing special', () => {
    const t = setup(['এক', 'দুই']);
    t.input.value = 'অ'; t.key('Backspace');
    assert.deepStrictEqual(t.chips(), ['এক', 'দুই']);
    t.input.value = ''; t.key('Backspace');
    assert.deepStrictEqual(t.chips(), ['এক']);
    assert.deepStrictEqual(t.hiddenValues(), ['এক']);
});

test('the × button removes exactly that chip and its hidden field', () => {
    const t = setup(['এক', 'দুই', 'তিন']);
    t.w.document.querySelectorAll('[data-chip] button')[1].click();
    assert.deepStrictEqual(t.chips(), ['এক', 'তিন']);
    assert.deepStrictEqual(t.hiddenValues(), ['এক', 'তিন']);
});

test('text typed but not confirmed is turned into a chip when the form is submitted', () => {
    const t = setup(['এক']);
    let sent = null;
    t.form.addEventListener('submit', e => { sent = t.hiddenValues(); e.preventDefault(); }, { capture: false });
    t.input.value = 'শেষ মুহূর্তের';
    t.form.dispatchEvent(new t.w.Event('submit', { bubbles: true, cancelable: true }));
    assert.deepStrictEqual(sent, ['এক', 'শেষ মুহূর্তের']);
});

test('blur also keeps what was typed', () => {
    const t = setup();
    t.input.value = 'ব্লার';
    t.input.dispatchEvent(new t.w.Event('blur'));
    assert.deepStrictEqual(t.chips(), ['ব্লার']);
});

test('markup typed into a tag is stripped and never interpreted as HTML (XSS)', () => {
    const t = setup();
    t.input.value = '<img src=x onerror="window.pwned=1">'; t.key('Enter');
    assert.strictEqual(t.w.document.querySelector('img'), null, 'no <img> may ever be created');
    assert.strictEqual(t.w.pwned, undefined);
    assert.deepStrictEqual(t.names(), [], 'pure markup leaves nothing to tag with');

    t.input.value = 'গুরুত্বপূর্ণ <b onmouseover="window.pwned=2">খবর</b>'; t.key('Enter');
    assert.deepStrictEqual(t.names(), ['গুরুত্বপূর্ণ খবর']);
    assert.strictEqual(t.w.document.querySelector('b'), null);
    assert.strictEqual(t.w.pwned, undefined);
});

test('a name with quotes cannot break out of the hidden input', () => {
    const t = setup();
    t.input.value = '"><script>window.x=1</script>'; t.key('Enter');
    assert.strictEqual(t.w.document.querySelectorAll('script').length, 0);
    assert.strictEqual(t.w.x, undefined);
});

test('suggestions are requested for the part being typed, encoded, and shown in the datalist', async () => {
    const calls = [];
    const t = setup([], { fetch: (url) => { calls.push(url); return Promise.resolve({ ok: true, json: () => Promise.resolve({ tags: ['রাজধানী', 'রাজশাহী'] }) }); } });
    t.type('রাজ');
    await new Promise(r => setTimeout(r, 20));
    assert.strictEqual(calls.length, 1);
    assert.ok(calls[0].includes('/admin/tags/suggest?q=' + encodeURIComponent('রাজ')), calls[0]);
    const opts = [...t.w.document.querySelectorAll('#tag-suggestions option')].map(o => o.value);
    assert.deepStrictEqual(opts, ['রাজধানী', 'রাজশাহী']);
});

test('already-chosen tags are not suggested again', async () => {
    const t = setup(['রাজধানী'], { fetch: () => Promise.resolve({ ok: true, json: () => Promise.resolve({ tags: ['রাজধানী', 'রাজশাহী'] }) }) });
    t.type('রাজ');
    await new Promise(r => setTimeout(r, 20));
    assert.deepStrictEqual([...t.w.document.querySelectorAll('#tag-suggestions option')].map(o => o.value), ['রাজশাহী']);
});

test('an outdated slow answer never overwrites a newer one', async () => {
    let resolveSlow;
    const slow = new Promise(r => { resolveSlow = r; });
    let n = 0;
    const t = setup([], { fetch: () => (++n === 1 ? slow.then(() => ({ ok: true, json: () => Promise.resolve({ tags: ['পুরনো-উত্তর'] }) }))
                                               : Promise.resolve({ ok: true, json: () => Promise.resolve({ tags: ['নতুন-উত্তর'] }) })) });
    t.type('ক'); await new Promise(r => setTimeout(r, 10));
    t.type('কখ'); await new Promise(r => setTimeout(r, 10));
    resolveSlow(); await new Promise(r => setTimeout(r, 10));
    assert.deepStrictEqual([...t.w.document.querySelectorAll('#tag-suggestions option')].map(o => o.value), ['নতুন-উত্তর']);
});

test('a failing suggestion request is harmless', async () => {
    const t = setup([], { fetch: () => Promise.reject(new Error('offline')) });
    t.type('কিছু');
    await new Promise(r => setTimeout(r, 30));
    t.input.value = 'চলছে'; t.key('Enter');
    assert.deepStrictEqual(t.names(), ['চলছে']);
    assert.deepStrictEqual(t.chips(), ['চলছে']);
});

test('an empty term clears the suggestions and sends no request', async () => {
    const t = setup();
    t.type('  ');
    await new Promise(r => setTimeout(r, 20));
    assert.strictEqual(t.calls.length, 0);
});

test('calling init twice on one field (script loaded twice, or auto-init + manual) keeps ONE instance; chips are not wiped', async () => {
    const t = setup();
    const again = t.w.AdminTagInput.init(t.w.document.getElementById('tag-field'), { fetch: () => Promise.resolve({ ok: true, json: () => Promise.resolve({ tags: [] }) }), debounce: 0 });
    assert.strictEqual(again, t.api, 'the second init must return the existing instance');
    await new Promise(r => setTimeout(r, 30));
    t.input.value = 'চলছে'; t.key('Enter');
    assert.deepStrictEqual(t.chips(), ['চলছে']);
    assert.deepStrictEqual(t.hiddenValues(), ['চলছে']);
    assert.strictEqual(t.w.document.querySelectorAll('[data-chip]').length, 1);
});

test('the auto-init on DOMContentLoaded does not clash with a manual init', async () => {
    const dom = new JSDOM(`<!DOCTYPE html><div id="tag-field" data-suggest-url="/s" data-max="10" data-max-length="50"><div data-role="chips"></div><input data-role="input" name="tag_input" list="dl"><datalist id="dl"></datalist></div>`, { runScripts: 'outside-only' });
    const w = dom.window; w.eval(src);                       // registers the DOMContentLoaded auto-init
    const manual = w.AdminTagInput.init(w.document.getElementById('tag-field'), { fetch: () => Promise.resolve({ ok: true, json: () => Promise.resolve({ tags: [] }) }), debounce: 0 });
    await new Promise(r => setTimeout(r, 30));               // DOMContentLoaded fires meanwhile
    const input = w.document.querySelector('[data-role="input"]');
    input.value = 'একটি'; input.dispatchEvent(new w.KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
    assert.strictEqual(w.document.querySelectorAll('[data-chip]').length, 1);
    assert.deepStrictEqual(manual.names(), ['একটি']);
});

(async () => {
    for (const { name, fn } of pending) {
        try { await fn(); passed++; console.log('  ✓', name); }
        catch (e) { failed++; console.log('  ✗', name, '\n     ', e.message); }
    }
    console.log(`\n${passed} passed, ${failed} failed`);
    process.exit(failed ? 1 : 0);
})();
