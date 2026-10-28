(function () {
    'use strict';

    const modal = document.getElementById('gallery-modal');

    if (!modal) {
        return;
    }

    const grid = document.getElementById('gallery-grid');
    const search = document.getElementById('gallery-search');
    const moreBtn = document.getElementById('gallery-more');
    const closeBtn = document.getElementById('gallery-close');
    const status = document.getElementById('gallery-status');
    const listUrl = modal.dataset.listUrl;

    // Cover-image controls (news form)
    const openBtn = document.getElementById('gallery-open');
    const hidden = document.getElementById('gallery-image');
    const preview = document.getElementById('image-preview');
    const fileInput = document.getElementById('image-input');
    const note = document.getElementById('gallery-picked-note');
    const clearBtn = document.getElementById('gallery-clear');

    let page = 1;
    let term = '';
    let seq = 0;
    let timer = null;
    let loadedOnce = false;
    let onPick = null;
    let opener = null;

    function addItem(item) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ad-pick';
        btn.title = item.name;

        const img = document.createElement('img');
        img.src = item.url;
        img.alt = '';
        img.loading = 'lazy';

        const label = document.createElement('span');
        label.textContent = item.name;

        btn.append(img, label);
        btn.addEventListener('click', function () { choose(item); });
        grid.appendChild(btn);
    }

    async function load(reset) {
        const mine = ++seq;

        if (reset) {
            page = 1;
            grid.textContent = '';
        }

        status.textContent = 'লোড হচ্ছে…';
        moreBtn.hidden = true;

        try {
            const query = new URLSearchParams({ page: String(page) });
            if (term) { query.set('q', term); }

            const res = await fetch(listUrl + '?' + query.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });

            if (!res.ok) { throw new Error('HTTP ' + res.status); }

            const data = await res.json();

            // A newer search started meanwhile: drop this answer.
            if (mine !== seq) { return; }

            data.items.forEach(addItem);
            status.textContent = grid.children.length ? '' : 'কোনো ছবি পাওয়া যায়নি।';
            moreBtn.hidden = !data.has_more;
            loadedOnce = true;
        } catch (e) {
            if (mine === seq) {
                status.textContent = 'ছবি লোড করা যায়নি। আবার চেষ্টা করুন।';
            }
        }
    }

    // callback(item) runs when a photo is chosen; `from` is the element that gets the focus back on cancel.
    function open(callback, from) {
        onPick = typeof callback === 'function' ? callback : null;
        opener = from || null;

        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        search.focus();

        if (!loadedOnce) { load(true); }
    }

    function close(restoreFocus) {
        modal.hidden = true;
        document.body.style.overflow = '';

        if (restoreFocus && opener) { opener.focus(); }
    }

    function choose(item) {
        const callback = onPick;

        close(false);

        if (callback) { callback(item); }
    }

    closeBtn.addEventListener('click', function () { close(true); });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) { close(true); }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) { close(true); }
    });

    moreBtn.addEventListener('click', function () {
        page += 1;
        load(false);
    });

    search.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            term = search.value.trim();
            load(true);
        }, 300);
    });

    // Enter must search, not submit the whole news form.
    search.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(timer);
            term = search.value.trim();
            load(true);
        }
    });

    // ---- Cover image (main photo) ----
    if (openBtn && hidden && preview && fileInput && note && clearBtn) {
        openBtn.addEventListener('click', function () {
            open(function (item) {
                hidden.value = item.filename;
                fileInput.value = '';      // the gallery pick replaces any file chosen before
                preview.src = item.url;
                preview.hidden = false;
                note.hidden = false;
            }, openBtn);
        });

        // Choosing a new file after a gallery pick: the file wins.
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files.length) {
                hidden.value = '';
                note.hidden = true;
            }
        });

        clearBtn.addEventListener('click', function () {
            hidden.value = '';
            note.hidden = true;

            const original = preview.dataset.original || '';
            if (original) {
                preview.src = original;
            } else {
                preview.removeAttribute('src');
                preview.hidden = true;
            }
        });
    }

    window.AdminMediaPicker = { open: open };
})();