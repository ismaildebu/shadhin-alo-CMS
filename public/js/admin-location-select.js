/*
 * Division > district > upazila selects for the article form.
 *
 * Every option is rendered by the server, so the form works without JavaScript. This script only hides
 * the options that do not belong to the current parent, and clears a choice that no longer fits.
 * The server re-checks that the three choices describe one real place.
 */
(function (root) {
    'use strict';

    function init(field) {
        if (field.__locationSelect) { return field.__locationSelect; }

        var division = field.querySelector('select[data-level="division"]');
        var district = field.querySelector('select[data-level="district"]');
        var upazila = field.querySelector('select[data-level="upazila"]');

        if (!division || !district || !upazila) { return null; }

        // the districts under each division, and the division of each upazila's district
        var districtParent = {};
        Array.prototype.forEach.call(district.options, function (o) { if (o.value) { districtParent[o.value] = o.getAttribute('data-parent'); } });

        function filter(select, parentId) {
            Array.prototype.forEach.call(select.options, function (o) {
                if (!o.value) { return; }
                var show = !parentId || o.getAttribute('data-parent') === String(parentId);
                o.hidden = !show;
                o.disabled = !show; // hidden alone is ignored by some browsers
            });
        }

        function apply() {
            filter(district, division.value);
            filter(upazila, district.value);
        }

        // A সারাদেশ report must name its division and district; the upazila is optional because
        // a report from a district town (including Dhaka, Chattogram, Khulna and Rajshahi) has none.
        function applyRequired() {
            var form = field.closest ? field.closest('form') : null;
            var saradesh = form && form.querySelector('input[name="category_ids[]"][data-slug="saradesh"]');
            var needed = !!(saradesh && saradesh.checked);

            [division, district].forEach(function (select) {
                select.required = needed;
            });

            upazila.required = false;

            var hint = field.querySelector('[data-role="location-hint"]');
            if (hint) { hint.style.display = needed ? '' : 'none'; }
        }

        // The most specific choice is the truth: an upazila names its district, a district names its division.
        // So when the choices disagree on load (a form re-shown after an error, or an article saved with only
        // an upazila) we complete the upper levels from the lowest one, instead of throwing the lowest away.
        function reconcile() {
            var opt = upazila.options[upazila.selectedIndex];
            if (opt && opt.value) {
                district.value = opt.getAttribute('data-parent');
            }
            if (district.value && districtParent[district.value]) {
                division.value = districtParent[district.value];
            }
        }

        division.addEventListener('change', function () { district.value = ''; upazila.value = ''; apply(); });

        district.addEventListener('change', function () {
            // picking a district also tells us its division
            if (district.value && districtParent[district.value]) { division.value = districtParent[district.value]; }
            upazila.value = '';
            apply();
        });

        upazila.addEventListener('change', function () {
            var opt = upazila.options[upazila.selectedIndex];
            if (opt && opt.value) {
                var d = opt.getAttribute('data-parent');
                district.value = d;
                if (districtParent[d]) { division.value = districtParent[d]; }
                apply();
            }
        });

        reconcile();
        apply();
        applyRequired();

        var form = field.closest ? field.closest('form') : null;
        if (form) {
            Array.prototype.forEach.call(
                form.querySelectorAll('input[name="category_ids[]"]'),
                function (box) { box.addEventListener('change', applyRequired); }
            );
        }

        field.__locationSelect = { apply: apply };

        return field.__locationSelect;
    }

    var api = { init: init };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.AdminLocationSelect = api;
        root.document && root.document.addEventListener('DOMContentLoaded', function () {
            var field = root.document.getElementById('location-field');
            if (field) { init(field); }
        });
    }
})(typeof window !== 'undefined' ? window : globalThis);
