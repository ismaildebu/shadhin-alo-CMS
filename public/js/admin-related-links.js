(function () {
    'use strict';

    const modal = document.getElementById('related-modal');
    const openBtn = document.getElementById('related-open');

    if (!modal || !openBtn) {
        return;
    }

    const list = document.getElementById('related-list');
    const search = document.getElementById('related-search');
    const status = document.getElementById('related-status');
    const closeBtn = document.getElementById('related-close');

    const searchUrl = modal.dataset.searchUrl;
    const exclude = modal.dataset.news || '0';

    let timer = null;
    let seq = 0;
    let loadedOnce = false;
    let opener = null;

    function text(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function attribute(value) {
        return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
    }

    // The picks the editor already made in this form — they steer the suggestions.
    function formContext() {
        const categories = Array.prototype.slice
            .call(document.querySelectorAll('input[name="category_ids[]"]:checked'))
            .map(function (el) { return el.value; })
            .slice(0, 6);

        const tags = Array.prototype.slice
            .call(document.querySelectorAll('#tag-field input[name="tags[]"]'))
            .map(function (el) { return el.value; })
            .slice(0, 10);

        return { categories: categories, tags: tags };
    }

    function addItem(item) {
        const btn = document.createElement('button');

        btn.type = 'button';
        btn.className = 'ad-rel';

        const title = document.createElement('b');
        title.textContent = item.title;

        const meta = document.createElement('span');
        meta.textContent = (item.category ? item.category + ' · ' : '') + (item.date || '');

        btn.append(title, meta);
        btn.addEventListener('click', function () { choose(item); });
        list.appendChild(btn);
    }

    async function load(term) {
        const mine = ++seq;
        const context = formContext();
        const query = new URLSearchParams({ q: term || '', exclude: exclude });

        context.categories.forEach(function (id) { query.append('categories[]', id); });
        context.tags.forEach(function (name) { query.append('tags[]', name); });

        list.textContent = '';
        status.textContent = 'খোঁজা হচ্ছে…';

        try {
            const res = await fetch(searchUrl + '?' + query.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });

            if (!res.ok) { throw new Error('HTTP ' + res.status); }

            const data = await res.json();

            if (mine !== seq) { return; }

            data.items.forEach(addItem);
            status.textContent = data.items.length ? '' : 'কোনো সংবাদ পাওয়া যায়নি।';
            loadedOnce = true;
        } catch (e) {
            if (mine === seq) {
                status.textContent = 'সম্পর্কিত সংবাদ খোঁজা যায়নি। আবার চেষ্টা করুন।';
            }
        }
    }

    // The link lands in the body at the cursor, as its own line the editor can re-word.
    function choose(item) {
        const editor = window.newsEditor;

        if (!editor) {
            alert('এডিটর এখনো লোড হয়নি, একটু অপেক্ষা করুন।');

            return;
        }

        closeModal(false);

        editor.editing.view.focus();

        try {
            editor.model.insertContent(
                editor.data.parse('<p><a href="' + attribute(item.url) + '">' + text(item.title) + '</a></p>'),
                editor.model.document.selection
            );
        } catch (e) {
            alert('লিংক বসানো যায়নি — বডির মধ্যে নিজে থেকে বসিয়ে নিন: ' + item.url);
        }
    }

    function openModal(from) {
        opener = from || null;
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        search.focus();

        if (!loadedOnce) { load(''); }
    }

    function closeModal(restoreFocus) {
        modal.hidden = true;
        document.body.style.overflow = '';

        if (restoreFocus && opener) { opener.focus(); }
    }

    openBtn.addEventListener('click', function () { openModal(openBtn); });
    closeBtn.addEventListener('click', function () { closeModal(true); });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) { closeModal(true); }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) { closeModal(true); }
    });

    search.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { load(search.value.trim()); }, 300);
    });

    // Enter searches; it must never submit the news form.
    search.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(timer);
            load(search.value.trim());
        }
    });
})();
