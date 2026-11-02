(function () {
    'use strict';

    if (!window.htmlToImage) {
        return;
    }

    document.body.classList.add('sa-visuals-ready');

    function fileName(figure) {
        var caption = figure.querySelector('.sa-html-chart-title');
        var title = (caption ? caption.textContent : '') || figure.getAttribute('aria-label') || 'chart';
        var now = new Date();
        var stamp = now.getFullYear().toString() +
            String(now.getMonth() + 1).padStart(2, '0') +
            String(now.getDate()).padStart(2, '0') + '-' +
            String(now.getHours()).padStart(2, '0') +
            String(now.getMinutes()).padStart(2, '0');

        title = title.replace(/[\\/:*?"<>|\r\n]+/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 60);

        return (title || 'chart') + '-' + stamp + '.png';
    }

    // Wide charts scroll sideways on phones; the saved image should contain every column.
    function expandScroll(figure) {
        var width = figure.clientWidth;
        var restores = [];

        figure.querySelectorAll('.sa-scroll').forEach(function (box) {
            if (box.scrollWidth > box.clientWidth + 1) {
                restores.push([box, box.style.overflowX]);
                box.style.overflowX = 'visible';
                width = Math.max(width, figure.clientWidth + (box.scrollWidth - box.clientWidth));
            }
        });

        return { width: width, restores: restores };
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.sa-download');

        if (!button) {
            return;
        }

        var figure = button.closest('.sa-visual');

        if (!figure || button.disabled) {
            return;
        }

        event.preventDefault();

        var label = button.querySelector('span');
        var original = label ? label.textContent : '';
        var expanded = expandScroll(figure);

        button.disabled = true;
        figure.classList.add('sa-exporting');

        if (label) {
            label.textContent = 'তৈরি হচ্ছে…';
        }

        window.htmlToImage.toPng(figure, {
            backgroundColor: '#ffffff',
            pixelRatio: 2,
            width: expanded.width,
            height: figure.clientHeight,
            cacheBust: false,
            filter: function (node) {
                return !(node instanceof Element && node.classList.contains('sa-download'));
            }
        }).then(function (dataUrl) {
            var link = document.createElement('a');

            link.download = fileName(figure);
            link.href = dataUrl;
            document.body.appendChild(link);
            link.click();
            link.remove();

            if (label) {
                label.textContent = 'ডাউনলোড হয়েছে';
                window.setTimeout(function () { label.textContent = original; }, 2000);
            }
        }).catch(function (error) {
            console.error('Chart download failed', error);

            if (label) {
                label.textContent = 'ব্যর্থ হয়েছে';
                window.setTimeout(function () { label.textContent = original; }, 2000);
            }
        }).finally(function () {
            expanded.restores.forEach(function (pair) {
                pair[0].style.overflowX = pair[1];
            });

            button.disabled = false;
            figure.classList.remove('sa-exporting');
        });
    });
})();
