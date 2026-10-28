// Random walks over the three selects. Invariant after EVERY action:
//   upazila (if set) belongs to district (if set) belongs to division (if set); nothing "contradicts".
// Usage: node tests/js/location-select.fuzz.cjs  (needs: npm install --no-save jsdom)
const { JSDOM } = require('jsdom'); const fs = require('fs');
const file = process.argv[2] || require('path').join(__dirname, '..', '..', 'public', 'js', 'admin-location-select.js');
const src = fs.readFileSync(file, 'utf8');
const D = { 10: 1, 11: 1, 20: 2, 21: 2 };                         // district -> division
const U = { 100: 10, 101: 10, 110: 11, 200: 20, 201: 20, 210: 21 }; // upazila -> district
const HTML = (s) => `<form id="f"><div id="location-field">
 <select name="division_id" data-level="division"><option value="">-</option><option value="1">A</option><option value="2">B</option></select>
 <select name="district_id" data-level="district"><option value="">-</option>${Object.entries(D).map(([d, p]) => `<option value="${d}" data-parent="${p}">${d}</option>`).join('')}</select>
 <select name="upazila_id" data-level="upazila"><option value="">-</option>${Object.entries(U).map(([u, p]) => `<option value="${u}" data-parent="${p}">${u}</option>`).join('')}</select></div></form>`;
let seed = 12345; const rnd = (n) => (seed = (seed * 1103515245 + 12345) & 0x7fffffff) % n;
let violations = 0, runs = 0;
for (let run = 0; run < 400; run++) {
  const dom = new JSDOM(HTML(), { runScripts: 'outside-only' }); const w = dom.window; w.eval(src);
  w.AdminLocationSelect.init(w.document.getElementById('location-field'));
  const sel = (l) => w.document.querySelector(`select[data-level="${l}"]`);
  // optional: start from a random (possibly inconsistent) saved state, as a re-shown form would
  if (run % 3 === 0) {
    const dom2 = new JSDOM(HTML(), { runScripts: 'outside-only' });
    const pick = (o) => Object.keys(o)[rnd(Object.keys(o).length)];
    const st = { division: rnd(3) ? String(1 + rnd(2)) : '', district: rnd(2) ? pick(D) : '', upazila: rnd(2) ? pick(U) : '' };
    const html = HTML().replace(/<option value="(\d+)"( data-parent="\d+")?>/g, (m, v, p) => (st.division === v || st.district === v || st.upazila === v) ? `<option value="${v}"${p || ''} selected>` : m);
    const d3 = new JSDOM(html, { runScripts: 'outside-only' }); d3.window.eval(src);
    d3.window.AdminLocationSelect.init(d3.window.document.getElementById('location-field'));
    const g = (l) => d3.window.document.querySelector(`select[data-level="${l}"]`).value;
    runs++;
    const [dv, ds, up] = [g('division'), g('district'), g('upazila')];
    const bad = (up && ds && U[up] != ds) || (ds && dv && D[ds] != dv) || (up && !ds);
    if (bad) { violations++; if (violations <= 3) console.log('  load violation from', JSON.stringify(st), '->', dv, ds, up); }
  }
  for (let step = 0; step < 12; step++) {
    const level = ['division', 'district', 'upazila'][rnd(3)];
    const opts = [...sel(level).options].filter(o => !o.disabled);
    const o = opts[rnd(opts.length)];
    sel(level).value = o.value; sel(level).dispatchEvent(new w.Event('change', { bubbles: true }));
    const [dv, ds, up] = [sel('division').value, sel('district').value, sel('upazila').value];
    runs++;
    const bad = (up && ds && U[up] != ds) || (ds && dv && D[ds] != dv) || (up && !ds);
    if (bad) { violations++; if (violations <= 3) console.log('  violation after choosing', level, o.value, '->', dv, ds, up); }
  }
}
console.log(`${runs} states checked, ${violations} contradictory`); process.exit(violations ? 1 : 0);
