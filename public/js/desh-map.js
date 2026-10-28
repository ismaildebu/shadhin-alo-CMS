/* Cascading division -> district -> upazila filter for the home-page "desh" block. */
(function () {
    'use strict';

    if (window.__saDeshLoaded) { return; }
    window.__saDeshLoaded = true;

    function fill(select, label) {
        select.innerHTML = '';
        var first = document.createElement('option');
        first.value = '';
        first.textContent = label;
        select.appendChild(first);
        select.disabled = true;
    }

    function load(form, parent, target, label) {
        fill(target, label);
        target.__req = (target.__req || 0) + 1;
        var mine = target.__req;

        if (!parent.value) { return; }

        fetch(form.getAttribute('data-children-url').replace('__ID__', encodeURIComponent(parent.value)), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.ok ? r.json() : []; })
            .then(function (list) {
                if (target.__req !== mine) { return; }   // a newer choice replaced this one
                list.forEach(function (item) {
                    var o = document.createElement('option');
                    o.value = item.id;
                    o.textContent = item.name;
                    o.setAttribute('data-url', item.url);
                    target.appendChild(o);
                });
                target.disabled = list.length === 0;
            })
            .catch(function () { /* the map links still work without the filter */ });
    }

    function init(form) {
        var division = form.querySelector('[data-level="division"]');
        var district = form.querySelector('[data-level="district"]');
        var upazila = form.querySelector('[data-level="upazila"]');
        if (!division || !district || !upazila) { return; }

        var districtLabel = district.options[0].textContent;
        var upazilaLabel = upazila.options[0].textContent;

        division.addEventListener('change', function () {
            fill(upazila, upazilaLabel);
            upazila.__req = (upazila.__req || 0) + 1;
            load(form, division, district, districtLabel);
        });

        district.addEventListener('change', function () {
            load(form, district, upazila, upazilaLabel);
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var pick = upazila.value ? upazila : (district.value ? district : division);
            var option = pick.options[pick.selectedIndex];
            var url = option && option.getAttribute('data-url');
            if (!url) { return; }

            // listeners (analytics, tests) may cancel the navigation
            var go = new CustomEvent('sa:desh-go', { detail: { url: url }, cancelable: true, bubbles: true });
            if (form.dispatchEvent(go)) { window.location.href = url; }
        });
    }

    function boot() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-desh-filter]'), init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
