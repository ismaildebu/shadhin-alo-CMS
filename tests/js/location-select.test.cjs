const { JSDOM } = require('jsdom');
const assert = require('assert');
const fs = require('fs');
const path = require('path');
// Behaviour tests for public/js/admin-location-select.js against a real (jsdom) DOM.
// Run:  npm install --no-save jsdom   then   node tests/js/location-select.test.cjs
const src = fs.readFileSync(path.join(__dirname, '..', '..', 'public', 'js', 'admin-location-select.js'), 'utf8');

// 2 divisions, 3 districts, 5 upazilas
const HTML = (sel = {}) => `<!DOCTYPE html><form id="f"><div id="location-field">
  <select name="division_id" data-level="division">
    <option value="">— বিভাগ —</option>
    <option value="1"${sel.division === '1' ? ' selected' : ''}>ঢাকা</option>
    <option value="2"${sel.division === '2' ? ' selected' : ''}>সিলেট</option>
  </select>
  <select name="district_id" data-level="district">
    <option value="">— জেলা —</option>
    <option value="10" data-parent="1"${sel.district === '10' ? ' selected' : ''}>ঢাকা</option>
    <option value="11" data-parent="1"${sel.district === '11' ? ' selected' : ''}>গাজীপুর</option>
    <option value="20" data-parent="2"${sel.district === '20' ? ' selected' : ''}>সিলেট</option>
  </select>
  <select name="upazila_id" data-level="upazila">
    <option value="">— উপজেলা —</option>
    <option value="100" data-parent="10"${sel.upazila === '100' ? ' selected' : ''}>সাভার</option>
    <option value="101" data-parent="10"${sel.upazila === '101' ? ' selected' : ''}>ধামরাই</option>
    <option value="110" data-parent="11"${sel.upazila === '110' ? ' selected' : ''}>কালীগঞ্জ</option>
    <option value="200" data-parent="20"${sel.upazila === '200' ? ' selected' : ''}>বিয়ানীবাজার</option>
    <option value="201" data-parent="20"${sel.upazila === '201' ? ' selected' : ''}>জৈন্তাপুর</option>
  </select></div></form>`;

function setup(sel) {
    const dom = new JSDOM(HTML(sel), { runScripts: 'outside-only' });
    const w = dom.window;
    w.eval(src);
    const field = w.document.getElementById('location-field');
    const api = w.AdminLocationSelect.init(field);
    const $ = (level) => w.document.querySelector(`select[data-level="${level}"]`);
    const change = (el, value) => { el.value = value; el.dispatchEvent(new w.Event('change', { bubbles: true })); };
    const visible = (level) => [...$(level).options].filter(o => o.value && !o.disabled && !o.hidden).map(o => o.value);
    return { w, api, field, $, change, visible };
}

let passed = 0, failed = 0;
const tests = [];
const test = (name, fn) => tests.push({ name, fn });

test('with nothing chosen every district and upazila is offered', () => {
    const t = setup();
    assert.deepStrictEqual(t.visible('district'), ['10', '11', '20']);
    assert.deepStrictEqual(t.visible('upazila'), ['100', '101', '110', '200', '201']);
});

test('choosing a division narrows the districts, and the upazilas stay all/none-specific until a district is chosen', () => {
    const t = setup();
    t.change(t.$('division'), '1');
    assert.deepStrictEqual(t.visible('district'), ['10', '11']);
});

test('choosing a district narrows the upazilas to its own', () => {
    const t = setup();
    t.change(t.$('division'), '1');
    t.change(t.$('district'), '10');
    assert.deepStrictEqual(t.visible('upazila'), ['100', '101']);
});

test('changing the division clears a district and upazila that no longer fit', () => {
    const t = setup({ division: '1', district: '10', upazila: '100' });
    t.change(t.$('division'), '2');
    assert.strictEqual(t.$('district').value, '');
    assert.strictEqual(t.$('upazila').value, '');
    assert.deepStrictEqual(t.visible('district'), ['20']);
});

test('changing the district clears the upazila', () => {
    const t = setup({ division: '1', district: '10', upazila: '100' });
    t.change(t.$('district'), '11');
    assert.strictEqual(t.$('upazila').value, '');
    assert.deepStrictEqual(t.visible('upazila'), ['110']);
});

test('picking a district alone fills in its division', () => {
    const t = setup();
    t.change(t.$('district'), '20');
    assert.strictEqual(t.$('division').value, '2');
});

test('picking an upazila alone fills in its district and division, and keeps the upazila', () => {
    const t = setup();
    t.change(t.$('upazila'), '200');
    assert.strictEqual(t.$('district').value, '20');
    assert.strictEqual(t.$('division').value, '2');
    assert.strictEqual(t.$('upazila').value, '200', 'the upazila itself must survive the cascade');
});

test('the saved choice is kept on load and the lists are filtered to match it', () => {
    const t = setup({ division: '1', district: '10', upazila: '101' });
    assert.strictEqual(t.$('division').value, '1');
    assert.strictEqual(t.$('district').value, '10');
    assert.strictEqual(t.$('upazila').value, '101');
    assert.deepStrictEqual(t.visible('district'), ['10', '11']);
    assert.deepStrictEqual(t.visible('upazila'), ['100', '101']);
});

test('hidden options are also disabled, so they cannot be submitted or picked', () => {
    const t = setup();
    t.change(t.$('division'), '2');
    const d10 = [...t.$('district').options].find(o => o.value === '10');
    assert.strictEqual(d10.hidden, true);
    assert.strictEqual(d10.disabled, true);
});

test('going back to "no division" shows every district again', () => {
    const t = setup();
    t.change(t.$('division'), '1');
    t.change(t.$('division'), '');
    assert.deepStrictEqual(t.visible('district'), ['10', '11', '20']);
});

test('the submitted form carries exactly the three chosen ids', () => {
    const t = setup();
    t.change(t.$('upazila'), '100');
    const data = new t.w.FormData(t.w.document.getElementById('f'));
    assert.strictEqual(data.get('division_id'), '1');
    assert.strictEqual(data.get('district_id'), '10');
    assert.strictEqual(data.get('upazila_id'), '100');
});

test('an inconsistent saved choice is reconciled from the most specific level, never losing the lowest one', () => {
    // division 1 (Dhaka) but district 20 (Sylhet): the upazila 100 belongs to district 10 of division 1
    const t = setup({ division: '1', district: '20', upazila: '100' });
    assert.strictEqual(t.$('upazila').value, '100', 'the most specific choice is kept');
    assert.strictEqual(t.$('district').value, '10', 'its district is filled in from the upazila');
    assert.strictEqual(t.$('division').value, '1', 'and the division from the district');
    assert.deepStrictEqual(t.visible('upazila'), ['100', '101']);
});

test('a district that contradicts its division is reconciled upward, too (district wins over division)', () => {
    const t = setup({ division: '1', district: '20' });
    assert.strictEqual(t.$('district').value, '20');
    assert.strictEqual(t.$('division').value, '2');
});

test('the upazila stays selected when picking it moves the district and division (no state lost in the cascade)', () => {
    const t = setup({ division: '2', district: '20' });
    t.change(t.$('upazila'), '100');            // an upazila of Dhaka district while Sylhet is selected
    assert.strictEqual(t.$('upazila').value, '100');
    assert.strictEqual(t.$('district').value, '10');
    assert.strictEqual(t.$('division').value, '1');
    // and the form submits it
    assert.strictEqual(new t.w.FormData(t.w.document.getElementById('f')).get('upazila_id'), '100');
});

test('an upazila saved WITHOUT its district or division (the server derives them) is not thrown away on load', () => {
    const t = setup({ upazila: '100' });
    assert.strictEqual(t.$('upazila').value, '100', 'a lone upazila must survive page load');
    assert.strictEqual(t.$('district').value, '10', 'and its district is filled in');
    assert.strictEqual(t.$('division').value, '1', 'and its division');
});

test('init is idempotent (two scripts or auto-init + manual do not stack listeners)', () => {
    const t = setup();
    const again = t.w.AdminLocationSelect.init(t.field);
    assert.strictEqual(again, t.api);
    t.change(t.$('upazila'), '201');
    assert.strictEqual(t.$('upazila').value, '201');
    assert.strictEqual(t.$('district').value, '20');
});

test('a form without the three selects is left alone', () => {
    const dom = new JSDOM('<div id="location-field"><p>nothing here</p></div>', { runScripts: 'outside-only' });
    dom.window.eval(src);
    assert.strictEqual(dom.window.AdminLocationSelect.init(dom.window.document.getElementById('location-field')), null);
});

test('with JavaScript disabled nothing is hidden: every option is still in the markup', () => {
    const dom = new JSDOM(HTML(), { runScripts: 'outside-only' }); // script NOT evaluated
    const w = dom.window;
    assert.strictEqual(w.document.querySelectorAll('select[data-level="district"] option[value]:not([value=""])').length, 3);
    assert.strictEqual([...w.document.querySelectorAll('option')].filter(o => o.hidden || o.disabled).length, 0);
});

(async () => {
    for (const { name, fn } of tests) {
        try { await fn(); passed++; console.log('  ✓', name); }
        catch (e) { failed++; console.log('  ✗', name, '\n     ', e.message); }
    }
    console.log(`\n${passed} passed, ${failed} failed`);
    process.exit(failed ? 1 : 0);
})();
