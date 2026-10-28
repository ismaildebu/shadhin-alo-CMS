/*
 * Tag input for the article form.
 *
 * Markup contract (see resources/views/admin/news/_form.blade.php):
 *   <div id="tag-field" data-suggest-url="..." data-max="10" data-max-length="50">
 *     <div data-role="chips"></div>
 *     <input data-role="input" name="tag_input" list="tag-suggestions">
 *     <datalist id="tag-suggestions"></datalist>
 *     <input type="hidden" name="tags[]" value="..."> ...   (initial tags, one per tag)
 *   </div>
 *
 * Works without JavaScript too: the text input is submitted as "tag_input" and the server splits it at commas.
 */
(function (root) {
    'use strict';

    var SEPARATORS = /[,\u060C\uFF0C]/; // ASCII, Arabic and full-width comma

    function cleanName(raw, maxLength) {
        return String(raw || '')
            .replace(/<[^>]*>/g, '')
            .replace(/\s+/g, ' ')
            .trim()
            .normalize('NFC')
            .slice(0, maxLength);
    }

    function keyOf(name) {
        return name.normalize('NFC').toLowerCase().replace(/[\u200C\u200D]/g, '');
    }

    function init(field, options) {
        // Idempotent: two instances on one field would each render into the same box and wipe each other's chips.
        if (field.__tagInput) { return field.__tagInput; }

        options = options || {};
        var fetchFn = options.fetch || (typeof fetch === 'function' ? fetch.bind(root) : null);
        var max = parseInt(field.dataset.max || '10', 10);
        var maxLength = parseInt(field.dataset.maxLength || '50', 10);
        var suggestUrl = field.dataset.suggestUrl || '';
        var chipsBox = field.querySelector('[data-role="chips"]');
        var input = field.querySelector('[data-role="input"]');
        var datalist = field.ownerDocument.getElementById(input.getAttribute('list'));
        var form = input.form;
        var names = [];
        var timer = null;
        var requestId = 0;

        // Initial tags rendered by the server: turn the hidden inputs into chips.
        Array.prototype.slice.call(field.querySelectorAll('input[type="hidden"][name="tags[]"]')).forEach(function (el) {
            add(el.value, true);
            el.parentNode.removeChild(el);
        });
        render();

        function has(name) {
            var key = keyOf(name);
            return names.some(function (n) { return keyOf(n) === key; });
        }

        function add(raw, silent) {
            var name = cleanName(raw, maxLength);
            if (!name || has(name) || names.length >= max) { return false; }
            names.push(name);
            if (!silent) { render(); }
            return true;
        }

        function remove(index) {
            names.splice(index, 1);
            render();
        }

        function commitInput() {
            // "a, b" typed or pasted at once: every part becomes a chip.
            var parts = input.value.split(SEPARATORS);
            parts.forEach(function (part) { add(part, true); });
            input.value = '';
            render();
        }

        function render() {
            chipsBox.textContent = '';

            names.forEach(function (name, index) {
                var chip = field.ownerDocument.createElement('span');
                chip.className = 'tag-chip';
                chip.setAttribute('data-chip', '');
                chip.style.cssText = 'display:inline-flex;align-items:center;gap:.35rem;background:#e0f2e9;border:1px solid #9ad0b1;border-radius:9999px;padding:.1rem .6rem;margin:0 .35rem .35rem 0;';

                var label = field.ownerDocument.createElement('span');
                label.textContent = name; // textContent: never interpreted as HTML

                var button = field.ownerDocument.createElement('button');
                button.type = 'button';
                button.setAttribute('aria-label', name + ' সরান');
                button.textContent = '×';
                button.style.cssText = 'border:0;background:transparent;cursor:pointer;font-size:1.1rem;line-height:1;';
                button.addEventListener('click', function () { remove(index); });

                var hidden = field.ownerDocument.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'tags[]';
                hidden.value = name;

                chip.appendChild(label);
                chip.appendChild(button);
                chip.appendChild(hidden);
                chipsBox.appendChild(chip);
            });

            input.disabled = false;
            input.placeholder = names.length >= max
                ? 'সর্বোচ্চ ' + max + 'টি ট্যাগ'
                : 'ট্যাগ লিখে Enter বা কমা দিন';
            input.readOnly = names.length >= max;
        }

        function suggest() {
            var term = cleanName(input.value.split(SEPARATORS).pop(), maxLength);
            var id = ++requestId;

            if (!term || !suggestUrl || !fetchFn || !datalist) {
                if (datalist) { datalist.textContent = ''; }
                return;
            }

            fetchFn(suggestUrl + (suggestUrl.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(term), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (response) { return response.ok ? response.json() : { tags: [] }; })
                .then(function (data) {
                    if (id !== requestId) { return; } // a newer keystroke superseded this answer
                    datalist.textContent = '';
                    (data.tags || []).forEach(function (name) {
                        if (has(name)) { return; }
                        var option = field.ownerDocument.createElement('option');
                        option.value = name;
                        datalist.appendChild(option);
                    });
                })
                .catch(function () { /* suggestions are optional */ });
        }

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ',' || event.key === '\u060C') {
                event.preventDefault(); // Enter must not submit the whole form
                commitInput();
            } else if (event.key === 'Backspace' && input.value === '' && names.length) {
                remove(names.length - 1);
            }
        });

        input.addEventListener('input', function () {
            if (SEPARATORS.test(input.value)) { commitInput(); return; } // pasted "a, b, c"
            clearTimeout(timer);
            timer = setTimeout(suggest, options.debounce === undefined ? 200 : options.debounce);
        });

        input.addEventListener('blur', function () { commitInput(); });

        if (form) {
            // Text typed but not confirmed must not get lost on save.
            form.addEventListener('submit', commitInput);
        }

        field.__tagInput = {
            names: function () { return names.slice(); },
            add: function (name) { var ok = add(name); if (ok) { render(); } return ok; },
            suggest: suggest
        };

        return field.__tagInput;
    }

    var api = { init: init, cleanName: cleanName, keyOf: keyOf };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.AdminTagInput = api;
        root.document && root.document.addEventListener('DOMContentLoaded', function () {
            var field = root.document.getElementById('tag-field');
            if (field) { init(field); }
        });
    }
})(typeof window !== 'undefined' ? window : globalThis);
