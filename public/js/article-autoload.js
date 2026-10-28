/*
 * Shadhin Alo - auto-load the next article of the same category.
 * Loaded with "defer" from the article page. Configuration comes from data-*
 * attributes on #sa-autoload (see resources/views/frontend/news.blade.php).
 */
(function () {
    'use strict';

    var root = document.getElementById('sa-autoload');

    if (!root || !('IntersectionObserver' in window) || !window.fetch) {
        return;
    }

    var endpoint = root.dataset.endpoint;
    var max = parseInt(root.dataset.max, 10) || 0;
    var lastSlug = root.dataset.slug;
    var status = root.querySelector('[data-status]');

    var loaded = 0;
    var busy = false;
    var finished = false;

    function setStatus(text) {
        status.textContent = text;
    }

    function nearEnd() {
        return root.getBoundingClientRect().top < window.innerHeight + 600;
    }

    function finish() {
        finished = true;
        setStatus('');
        endObserver.disconnect();
    }

    function showRetry() {
        setStatus(root.dataset.error + ' ');

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'sa-autoload-retry';
        button.textContent = root.dataset.retry;
        button.addEventListener('click', loadNext);

        status.appendChild(button);
    }

    // The address bar and tab title follow the article currently in view.
    var urlObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) {
                return;
            }

            var url = entry.target.dataset.url;
            var title = entry.target.dataset.title;

            if (url && url !== window.location.href) {
                try {
                    history.replaceState(null, '', url);
                } catch (error) {
                    return; // different origin (for example a wrong APP_URL): leave the address alone
                }

                if (title) {
                    document.title = title;
                }
            }
        });
    }, { rootMargin: '-40% 0px -55% 0px' });

    document.querySelectorAll('.sa-autoload-item').forEach(function (item) {
        urlObserver.observe(item);
    });

    function loadNext() {
        if (busy || finished) {
            return;
        }

        if (max > 0 && loaded >= max) {
            finish();
            return;
        }

        busy = true;
        setStatus(root.dataset.loading);

        fetch(endpoint.replace('__SLUG__', encodeURIComponent(lastSlug)), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (response.status === 204) {
                    return null;
                }

                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                return response.json();
            })
            .then(function (data) {
                busy = false;

                if (!data || !data.html) {
                    finish();
                    return;
                }

                root.insertAdjacentHTML('beforebegin', data.html);

                var inserted = root.previousElementSibling;

                if (inserted && inserted.classList.contains('sa-autoload-item')) {
                    urlObserver.observe(inserted);
                }

                lastSlug = data.slug;
                loaded += 1;
                setStatus('');

                if (max > 0 && loaded >= max) {
                    finish();
                    return;
                }

                // A short article may leave the end marker on screen: check again.
                window.requestAnimationFrame(function () {
                    if (nearEnd()) {
                        loadNext();
                    }
                });
            })
            .catch(function () {
                busy = false;
                showRetry();
            });
    }

    var endObserver = new IntersectionObserver(function (entries) {
        if (entries.some(function (entry) { return entry.isIntersecting; })) {
            loadNext();
        }
    }, { rootMargin: '600px 0px' });

    endObserver.observe(root);
})();
