(function () {
    'use strict';

    // কার্ড আঁকার লাইব্রেরি (js/admin-photocard.js) না এলে বাটনটি নিষ্ক্রিয়ই থাকে।
    if (!window.PhotoCard) {
        return;
    }

    function fileName(data) {
        var title = (data.title || '').replace(/[\\/:*?"<>|\r\n]+/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 60);
        var now = new Date();
        var stamp = now.getFullYear().toString() +
            String(now.getMonth() + 1).padStart(2, '0') +
            String(now.getDate()).padStart(2, '0') + '-' +
            String(now.getHours()).padStart(2, '0') +
            String(now.getMinutes()).padStart(2, '0');

        return (title || 'photocard') + '-' + stamp + '.jpg';
    }

    function payload(button) {
        var scope = button.closest('.sa-autoload-item') || document;
        var holder = scope.querySelector('[data-sa-card-data]');

        if (!holder) {
            return null;
        }

        try {
            return JSON.parse(holder.textContent);
        } catch (error) {
            return null;
        }
    }

    function download(blob, name) {
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');

        link.download = name;
        link.href = url;
        document.body.appendChild(link);
        link.click();
        link.remove();

        window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-sa-card]');

        if (!button || button.disabled) {
            return;
        }

        event.preventDefault();

        var card = payload(button);

        if (!card || !card.data || !card.options) {
            button.textContent = 'কার্ডের তথ্য পাওয়া যায়নি';
            return;
        }

        var original = button.textContent;

        button.disabled = true;
        button.textContent = 'তৈরি হচ্ছে…';

        var canvas = document.createElement('canvas');

        window.PhotoCard.render(canvas, card.data, card.options)
            .then(function () { return window.PhotoCard.toBlob(canvas, 'image/jpeg', 0.92); })
            .then(function (blob) {
                if (!blob) {
                    throw new Error('card blob missing');
                }

                download(blob, fileName(card.data));

                button.textContent = 'ডাউনলোড হয়েছে';
            })
            .catch(function (error) {
                console.error('Photo card failed', error);

                button.textContent = 'ব্যর্থ হয়েছে';
            })
            .finally(function () {
                button.disabled = false;

                window.setTimeout(function () { button.textContent = original; }, 2000);
            });
    });
})();
