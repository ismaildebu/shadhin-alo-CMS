/* Online poll: vote on change, update the percentages in place. Falls back to a normal form post without JS. */
(function () {
    'use strict';

    if (window.__saPollLoaded) { return; }
    window.__saPollLoaded = true;

    function message(root, kind) {
        var box = root.querySelector('[data-msg]');
        if (!box) { return; }
        var text = box.getAttribute('data-text-' + kind);
        if (!text) { return; }
        box.textContent = text;
        box.hidden = false;
        box.classList.toggle('is-error', kind === 'error');
    }

    function show(root, data) {
        var selected = data.selected === null || data.selected === undefined ? '' : String(data.selected);

        Array.prototype.forEach.call(root.querySelectorAll('[data-option]'), function (row) {
            var id = row.getAttribute('data-option');
            var item = null;

            data.options.forEach(function (o) { if (String(o.id) === id) { item = o; } });

            if (item) {
                row.style.setProperty('--pct', item.pct + '%');
                var pct = row.querySelector('[data-pct]');
                if (pct) { pct.textContent = item.pct_text; }
            }

            var input = row.querySelector('input');
            input.disabled = true;
            input.checked = (id === selected);
            row.classList.toggle('is-mine', id === selected);
        });

        var total = root.querySelector('[data-total]');
        if (total) { total.textContent = data.total_text; }

        root.classList.add('is-voted');
        message(root, data.already ? 'already' : 'ok');
    }

    function init(root) {
        var form = root.querySelector('form');
        if (!form) { return; }

        form.addEventListener('change', function (event) {
            var input = event.target;
            if (!input || input.name !== 'option' || root.__busy) { return; }

            root.__busy = true;
            root.classList.add('is-busy');

            fetch(form.getAttribute('action'), {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) {
                    if (!response.ok) { throw new Error('HTTP ' + response.status); }
                    return response.json();
                })
                .then(function (data) { show(root, data); })
                .catch(function () {
                    input.checked = false;
                    message(root, 'error');
                })
                .then(function () {
                    root.__busy = false;
                    root.classList.remove('is-busy');
                });
        });
    }

    document.addEventListener('click', function (event) {
        if (event.target.closest && event.target.closest('[data-print]')) { window.print(); }
    });

    function boot() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-poll]'), init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
