(function () {
    'use strict';

    const holder = document.getElementById('sa-autoload');

    // অটো-লোড বন্ধ থাকলে বা ঠিকানা না থাকলে কিছু করার নেই
    if (! holder || ! holder.dataset.view || ! ('IntersectionObserver' in window)) {
        return;
    }

    const template = holder.dataset.view;
    const counted = new Set();

    // "http://.../article/<slug>" থেকে slug বের করা
    function slugOf(item) {
        try {
            const path = new URL(item.dataset.url, window.location.href).pathname;
            const last = path.split('/').filter(Boolean).pop();

            return last ? decodeURIComponent(last) : '';
        } catch (e) {
            return '';
        }
    }

    // খবরের শিরোনাম স্ক্রিনের ওপরের ৭৫% অংশে এলে পাঠক ওই খবরে পৌঁছেছেন ধরা হয়
    const seen = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (! entry.isIntersecting) { return; }

            seen.unobserve(entry.target);

            const item = entry.target.closest('.sa-autoload-item');
            const slug = item ? slugOf(item) : '';

            if (! slug || counted.has(slug)) { return; }

            counted.add(slug);

            fetch(template.replace('__SLUG__', encodeURIComponent(slug)), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                keepalive: true
            }).catch(function () { /* গণনা ব্যর্থ হলে পাঠকের কিছু আসে-যায় না */ });
        });
    }, { threshold: 0, rootMargin: '0px 0px -25% 0px' });

    // শুধু অটো-লোড হওয়া খবর (is-loaded); মূল খবর সার্ভার আগেই গুনেছে
    function watch(node) {
        if (node.nodeType !== 1) { return; }

        const items = (node.matches && node.matches('.sa-autoload-item.is-loaded'))
            ? [node]
            : Array.prototype.slice.call(node.querySelectorAll('.sa-autoload-item.is-loaded'));

        items.forEach(function (item) {
            const title = item.querySelector('.sa-article-title');

            if (title) { seen.observe(title); }
        });
    }

    new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(watch);
        });
    }).observe(document.body, { childList: true, subtree: true });

    // পেজ খোলার সময়ই কিছু বসে থাকলে সেগুলোও
    watch(document.body);
})();