/**
 * ই-পেপার বিল্ডার। কোনো লাইব্রেরি নয় — pointer events দিয়ে কলামের রেখা টানা,
 * ব্লক সরানো ও ছবির সাইজ বদলানো হয়। DOM-ই একমাত্র সত্য; সেখান থেকেই JSON বানানো হয়।
 */
(() => {
    'use strict';

    const stage = document.getElementById('ep-stage');

    if (!stage) {
        return;
    }

    const field = document.getElementById('ep-layout');
    const form = document.getElementById('ep-form');
    const pool = document.getElementById('ep-pool-list');
    const search = document.getElementById('ep-search');
    const more = document.getElementById('ep-more');
    const limits = JSON.parse(stage.dataset.limits);
    const newsUrl = stage.dataset.newsUrl;
    const blockTpl = document.querySelector(stage.dataset.blockTpl).content.firstElementChild;
    const colTpl = document.querySelector(stage.dataset.colTpl).content.firstElementChild;

    const ghost = document.createElement('div');
    ghost.className = 'ep-ghost';
    ghost.style.pointerEvents = 'none';

    const marker = document.createElement('div');
    marker.className = 'ep-marker';

    const clamp = (value, low, high) => Math.min(high, Math.max(low, value));
    const num = (value, fallback) => {
        const parsed = Number(value);

        return Number.isFinite(parsed) ? parsed : fallback;
    };
    const columns = () => [...stage.querySelectorAll(':scope > .ep-col')];
    const blocksOf = (col) => [...col.querySelectorAll('.ep-block')];

    let lastColumn = columns()[0] || null;

    let skipClick = false;

    /** চার পিক্সেল নড়ার আগে ড্র্যাগ ধরা হয় না — নাহলে ক্লিক ও স্ক্রল ভেঙে যায়। */
    function trackDrag(event, handlers) {
        const start = { x: event.clientX, y: event.clientY };
        let started = false;

        const move = (ev) => {
            if (!started) {
                if (Math.abs(ev.clientX - start.x) + Math.abs(ev.clientY - start.y) < 4) {
                    return;
                }
                started = true;
                if (handlers.onStart) {
                    handlers.onStart(ev);
                }
            }
            handlers.onMove(ev);
        };

        const finish = (ev) => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', finish);
            window.removeEventListener('pointercancel', finish);

            if (started && handlers.onEnd) {
                handlers.onEnd(ev);
            }
        };

        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', finish);
        window.addEventListener('pointercancel', finish);
    }

    function setWidth(col, width) {
        col.dataset.width = String(Math.round(width * 100) / 100);
        col.style.flexGrow = col.dataset.width;
    }

    function serialize() {
        field.value = JSON.stringify({
            columns: columns().map((col) => ({
                width: num(col.dataset.width, 25),
                blocks: blocksOf(col).map((block) => ({
                    news_id: num(block.dataset.news, 0),
                    image: block.dataset.image || 'auto',
                    image_height: block.dataset.image === 'custom'
                        ? num(block.dataset.height, limits.minImageHeight)
                        : null,
                })),
            })),
        });
    }

    function refresh() {
        const used = new Set();

        columns().forEach((col, index) => {
            col.querySelector('.ep-col-name').textContent = 'কলাম ' + (index + 1);
            col.classList.toggle('is-empty', blocksOf(col).length === 0);

            blocksOf(col).forEach((block) => used.add(num(block.dataset.news, 0)));
        });

        pool.querySelectorAll('.ep-pool-item').forEach((row) => {
            row.classList.toggle('is-used', used.has(num(row.dataset.id, 0)));
        });

        serialize();
    }

    function buildBlock(item) {
        const block = blockTpl.cloneNode(true);

        block.dataset.news = item.id;
        block.dataset.image = 'auto';
        block.dataset.height = '';
        block.dataset.hasimg = item.image ? '1' : '0';

        block.querySelector('.ep-block-title').textContent = item.title;
        block.querySelector('.ep-block-cat').textContent = item.category || '';
        block.querySelector('.ep-block-date').textContent = item.date || '';

        const img = block.querySelector('.ep-block-img');

        if (item.image) {
            img.src = item.image;
        } else {
            img.removeAttribute('src');
        }

        return block;
    }

    function dropTarget(event) {
        const el = document.elementFromPoint(event.clientX, event.clientY);

        if (!el || !el.closest) {
            return null;
        }

        const col = el.closest('.ep-col');

        return col ? col.querySelector('.ep-drop') : null;
    }

    function placeMarker(event) {
        const drop = dropTarget(event);

        if (!drop) {
            marker.remove();

            return;
        }

        const after = [...drop.querySelectorAll('.ep-block:not(.is-dragging)')]
            .find((block) => {
                const box = block.getBoundingClientRect();

                return event.clientY < box.top + box.height / 2;
            });

        if (after) {
            drop.insertBefore(marker, after);
        } else {
            drop.appendChild(marker);
        }
    }

    function showGhost(node, event) {
        ghost.innerHTML = '';
        ghost.appendChild(node);
        ghost.style.width = node.getBoundingClientRect
            ? Math.max(180, node.getBoundingClientRect().width) + 'px'
            : '220px';
        document.body.appendChild(ghost);
        moveGhost(event);
    }

    function moveGhost(event) {
        ghost.style.left = (event.clientX - 20) + 'px';
        ghost.style.top = (event.clientY - 16) + 'px';
    }

    function dragFrom(event, source, node, existing) {
        event.preventDefault();

        trackDrag(event, {
            onStart() {
                if (existing) {
                    existing.classList.add('is-dragging');
                }
                showGhost(node, event);
            },
            onMove(ev) {
                moveGhost(ev);
                placeMarker(ev);
            },
            onEnd() {
                ghost.remove();

                const host = marker.parentNode;

                if (existing) {
                    existing.classList.remove('is-dragging');
                }

                if (host) {
                    host.replaceChild(existing || node, marker);
                    refresh();
                } else {
                    marker.remove();
                }

                // টেনে বসানো হলে আসা clickটি উপেক্ষা করতে হবে, নাহলে ট্যাপেই ব্লক দ্বিগুণ হয়।
                skipClick = true;
                window.setTimeout(() => {
                    skipClick = false;
                }, 0);
            },
        });
    }

    // ---- কলামের রেখা টেনে প্রস্থ ----

    function dividerPair(divider) {
        const left = divider.previousElementSibling;
        const right = divider.nextElementSibling;

        return left && right ? [left, right] : null;
    }

    function dragDivider(divider, event) {
        const pair = dividerPair(divider);

        if (!pair) {
            return;
        }

        const [left, right] = pair;
        const sum = num(left.dataset.width, 25) + num(right.dataset.width, 25);
        const pixels = left.getBoundingClientRect().width + right.getBoundingClientRect().width;

        if (pixels <= 0) {
            return;
        }

        const perWeight = pixels / sum;
        const low = clamp(limits.minWidth, limits.minWidth, sum - limits.minWidth);
        const high = clamp(limits.maxWidth, low, sum - limits.minWidth);
        const startX = event.clientX;
        const startLeft = num(left.dataset.width, 25);

        event.preventDefault();
        divider.classList.add('is-drag');

        trackDrag(event, {
            onMove(ev) {
                const width = clamp(startLeft + (ev.clientX - startX) / perWeight, low, high);

                setWidth(left, width);
                setWidth(right, sum - width);
            },
            onEnd() {
                divider.classList.remove('is-drag');
                serialize();
            },
        });
    }

    stage.addEventListener('pointerdown', (event) => {
        const divider = event.target.closest('.ep-divider');

        if (divider) {
            dragDivider(divider, event);

            return;
        }

        const col = event.target.closest('.ep-col');

        if (col) {
            lastColumn = col;
        }

        if (event.target.closest('button') || event.target.closest('.ep-img-grip')) {
            return;
        }

        const block = event.target.closest('.ep-block');

        if (block && event.button === 0) {
            dragFrom(event, block, block.cloneNode(true), block);
        }
    });

    // ---- টুলবারের বোতাম ----

    function setImage(block, mode) {
        const img = block.querySelector('.ep-block-img');
        const wrap = block.querySelector('.ep-block-imgwrap');

        block.dataset.image = mode;

        if (mode === 'custom') {
            const measured = img && img.getBoundingClientRect().height
                ? Math.round(img.getBoundingClientRect().height)
                : num(block.dataset.height, 220);

            const height = clamp(measured, limits.minImageHeight, limits.maxImageHeight);

            block.dataset.height = String(height);
            img.style.height = height + 'px';
            wrap.hidden = false;
        } else {
            block.dataset.height = '';
            img.style.height = '';
        }

        serialize();
    }

    function nudge(block, direction) {
        const drop = block.parentElement;
        const list = [...drop.querySelectorAll('.ep-block')];
        const index = list.indexOf(block);
        const target = direction === 'up' ? list[index - 1] : list[index + 1];

        if (!target) {
            return;
        }

        if (direction === 'up') {
            drop.insertBefore(block, target);
        } else {
            drop.insertBefore(target, block);
        }

        refresh();
    }

    function removeColumn(col) {
        if (columns().length <= 1) {
            alert('পাতায় অন্তত একটি কলাম রাখতে হবে।');

            return;
        }

        if (blocksOf(col).length && !confirm('এই কলামের ' + blocksOf(col).length + 'টি সংবাদও মুছে যাবে। রাখবেন?')) {
            return;
        }

        const divider = col.previousElementSibling && col.previousElementSibling.classList.contains('ep-divider')
            ? col.previousElementSibling
            : col.nextElementSibling;

        if (divider) {
            divider.remove();
        }

        col.remove();

        if (lastColumn === col) {
            lastColumn = columns()[0] || null;
        }

        refresh();
    }

    function addColumn() {
        if (columns().length >= limits.maxColumns) {
            alert('এক পাতায় সর্বোচ্চ ' + limits.maxColumns + 'টি কলাম রাখা যাবে।');

            return;
        }

        if (columns().length) {
            const divider = document.createElement('div');

            divider.className = 'ep-divider';
            divider.setAttribute('role', 'separator');
            divider.setAttribute('aria-orientation', 'vertical');
            divider.setAttribute('tabindex', '0');
            divider.title = 'টেনে প্রস্থ বদলান';
            stage.appendChild(divider);
        }

        const col = colTpl.cloneNode(true);

        setWidth(col, 100 / (columns().length + 1));
        stage.appendChild(col);
        columns().forEach((each, index) => setWidth(each, 100 / columns().length));
        refresh();
    }

    function equalize() {
        const list = columns();

        if (!list.length) {
            return;
        }

        list.forEach((col) => setWidth(col, 100 / list.length));
        serialize();
    }

    document.addEventListener('click', (event) => {
        if (skipClick) {
            return;
        }

        const button = event.target.closest('[data-act]');

        if (!button) {
            return;
        }

        const act = button.dataset.act;
        const block = button.closest('.ep-block');
        const col = button.closest('.ep-col');

        if (act === 'add-column') {
            addColumn();
        } else if (act === 'equal') {
            equalize();
        } else if (act === 'del-column' && col) {
            removeColumn(col);
        } else if (block && act.startsWith('img-')) {
            setImage(block, act.slice(4));
        } else if (block && (act === 'up' || act === 'down')) {
            nudge(block, act);
        } else if (block && act === 'remove') {
            block.remove();
            refresh();
        }
    });

    // ---- ছবির নিচের গ্রিপ টেনে সাইজ ----

    stage.addEventListener('pointerdown', (event) => {
        const grip = event.target.closest('.ep-img-grip');

        if (!grip) {
            return;
        }

        const block = grip.closest('.ep-block');
        const img = block.querySelector('.ep-block-img');

        if (block.dataset.image !== 'custom') {
            setImage(block, 'custom');
        }

        const startHeight = img.getBoundingClientRect().height;
        const startY = event.clientY;

        event.preventDefault();
        grip.classList.add('is-drag');

        trackDrag(event, {
            onMove(ev) {
                const height = clamp(Math.round(startHeight + (ev.clientY - startY)),
                    limits.minImageHeight, limits.maxImageHeight);

                block.dataset.height = String(height);
                img.style.height = height + 'px';
            },
            onEnd() {
                grip.classList.remove('is-drag');
                serialize();
            },
        });
    });

    // ---- কীবোর্ড: রেখায় ফোকাস নিয়ে তীর দিয়ে প্রস্থ ----

    stage.addEventListener('keydown', (event) => {
        const divider = event.target.closest('.ep-divider');

        if (!divider) {
            return;
        }

        const step = event.key === 'ArrowLeft' ? -2 : event.key === 'ArrowRight' ? 2 : 0;

        if (!step) {
            return;
        }

        const pair = dividerPair(divider);

        if (!pair) {
            return;
        }

        const [left, right] = pair;
        const sum = num(left.dataset.width, 25) + num(right.dataset.width, 25);
        const width = clamp(num(left.dataset.width, 25) + step, limits.minWidth, sum - limits.minWidth);

        setWidth(left, width);
        setWidth(right, sum - width);
        event.preventDefault();
        serialize();
    });

    // ---- সংবাদ প্যানেল ----

    function poolRow(item) {
        const row = document.createElement('div');

        row.className = 'ep-pool-item';
        row.tabIndex = 0;
        row.dataset.id = item.id;
        row.dataset.title = item.title;
        row.dataset.category = item.category || '';
        row.dataset.date = item.date || '';
        row.dataset.image = item.image || '';

        const thumb = document.createElement('span');

        thumb.className = 'ep-pool-thumb';

        if (item.image) {
            const img = document.createElement('img');

            img.src = item.image;
            img.alt = '';
            img.loading = 'lazy';
            thumb.appendChild(img);
        }

        const text = document.createElement('span');

        text.className = 'ep-pool-text';

        const title = document.createElement('span');

        title.className = 'ep-pool-title';
        title.textContent = item.title;

        const meta = document.createElement('span');

        meta.className = 'ep-pool-meta';
        meta.textContent = [item.category, item.date].filter(Boolean).join(' · ');

        text.append(title, meta);
        row.append(thumb, text);

        return row;
    }

    function addToColumn(item, col) {
        const target = col || lastColumn || columns()[0];

        if (!target) {
            return;
        }

        target.querySelector('.ep-drop').appendChild(buildBlock(item));
        refresh();
    }

    let page = 1;
    let term = '';
    let loading = false;

    async function loadPool(reset) {
        if (loading) {
            return;
        }

        loading = true;
        more.disabled = true;

        try {
            const response = await fetch(newsUrl + '?page=' + page + '&q=' + encodeURIComponent(term), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();

            if (reset) {
                pool.innerHTML = '';
            }

            data.items.forEach((item) => pool.appendChild(poolRow(item)));

            page = data.page;
            more.hidden = data.page >= data.lastPage;

            if (!data.items.length) {
                pool.innerHTML = '';

                const note = document.createElement('p');

                note.className = 'ep-pool-note';
                note.textContent = 'এই নামে কোনো প্রকাশিত সংবাদ নেই।';
                pool.appendChild(note);
            }

            refresh();
        } catch (error) {
            pool.innerHTML = '';

            const note = document.createElement('p');

            note.className = 'ep-pool-note';
            note.textContent = 'সংবাদের তালিকা আনা যায়নি। পাতা রিলোড করে আবার চেষ্টা করুন।';
            pool.appendChild(note);
        } finally {
            loading = false;
            more.disabled = false;
        }
    }

    pool.addEventListener('click', (event) => {
        if (skipClick) {
            return;
        }

        const row = event.target.closest('.ep-pool-item');

        if (row) {
            addToColumn(rowData(row));
        }
    });

    pool.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const row = event.target.closest('.ep-pool-item');

        if (row) {
            event.preventDefault();
            addToColumn(rowData(row));
        }
    });

    pool.addEventListener('pointerdown', (event) => {
        const row = event.target.closest('.ep-pool-item');

        if (!row || event.button !== 0) {
            return;
        }

        dragFrom(event, row, buildBlock(rowData(row)), null);
    });

    function rowData(row) {
        return {
            id: num(row.dataset.id, 0),
            title: row.dataset.title,
            category: row.dataset.category,
            date: row.dataset.date,
            image: row.dataset.image,
        };
    }

    let searchTimer = null;

    search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            term = search.value.trim();
            page = 1;
            loadPool(true);
        }, 300);
    });

    more.addEventListener('click', () => {
        page += 1;
        loadPool(false);
    });

    form.addEventListener('submit', serialize);

    refresh();
    loadPool(true);
})();
