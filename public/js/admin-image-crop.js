(function () {
    'use strict';

    const modal = document.getElementById('crop-modal');
    const openBtn = document.getElementById('crop-open');
    const closeBtn = document.getElementById('crop-close');
    const applyBtn = document.getElementById('crop-apply');
    const resetBtn = document.getElementById('crop-reset');
    const image = document.getElementById('crop-image');
    const box = document.getElementById('crop-box');
    const handle = document.getElementById('crop-handle');
    const info = document.getElementById('crop-info');

    const preview = document.getElementById('image-preview');
    const fileInput = document.getElementById('image-input');
    const galleryInput = document.getElementById('gallery-image');
    const galleryNote = document.getElementById('gallery-picked-note');

    if (!modal || !openBtn || !preview || !fileInput || !image || !box || !handle) {
        return;
    }

    const MIN_SIDE = 60;          // minimum crop box, in the photo's own pixels

    // ছেঁটার বাক্স ছবির আসল পিক্সেলে মেপে রাখা হয়; পর্দায় শুধু স্কেল করে দেখানো হয়।
    let rect = { x: 0, y: 0, w: 0, h: 0 };
    let drag = null;
    let opener = null;
    let croppedUrl = '';

    function clamp(value, low, high) {
        return Math.max(low, Math.min(high, value));
    }

    function scale() {
        return image.naturalWidth ? image.clientWidth / image.naturalWidth : 1;
    }

    function paint() {
        const s = scale();

        box.style.left = (rect.x * s) + 'px';
        box.style.top = (rect.y * s) + 'px';
        box.style.width = (rect.w * s) + 'px';
        box.style.height = (rect.h * s) + 'px';

        if (info) {
            info.textContent = 'ছেঁটার আকার: '
                + Math.round(rect.w) + '×' + Math.round(rect.h)
                + ' (মূল ছবি ' + image.naturalWidth + '×' + image.naturalHeight + ')';
        }
    }

    function wholeImage() {
        rect = { x: 0, y: 0, w: image.naturalWidth, h: image.naturalHeight };
        paint();
    }

    function onPointerMove(e) {
        if (!drag) {
            return;
        }

        const s = scale() || 1;
        const dx = (e.clientX - drag.clientX) / s;
        const dy = (e.clientY - drag.clientY) / s;

        if (drag.mode === 'move') {
            rect.x = clamp(drag.x + dx, 0, image.naturalWidth - rect.w);
            rect.y = clamp(drag.y + dy, 0, image.naturalHeight - rect.h);
        } else {
            rect.w = clamp(drag.w + dx, Math.min(MIN_SIDE, image.naturalWidth), image.naturalWidth - rect.x);
            rect.h = clamp(drag.h + dy, Math.min(MIN_SIDE, image.naturalHeight), image.naturalHeight - rect.y);
        }

        paint();
    }

    function startDrag(mode) {
        return function (e) {
            e.preventDefault();
            e.stopPropagation();

            drag = {
                mode: mode,
                clientX: e.clientX,
                clientY: e.clientY,
                x: rect.x,
                y: rect.y,
                w: rect.w,
                h: rect.h
            };
        };
    }

    function endDrag() {
        drag = null;
    }

    box.addEventListener('pointerdown', startDrag('move'));
    handle.addEventListener('pointerdown', startDrag('resize'));
    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', endDrag);
    window.addEventListener('pointercancel', endDrag);
    window.addEventListener('resize', function () {
        if (!modal.hidden) { paint(); }
    });

    function openModal(from) {
        opener = from || null;
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        wholeImage();
        applyBtn.focus();
    }

    function closeModal(restoreFocus) {
        modal.hidden = true;
        document.body.style.overflow = '';
        drag = null;

        if (restoreFocus && opener) { opener.focus(); }
    }

    closeBtn.addEventListener('click', function () { closeModal(true); });
    resetBtn.addEventListener('click', wholeImage);

    modal.addEventListener('click', function (e) {
        if (e.target === modal) { closeModal(true); }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) { closeModal(true); }
    });

    // Which photo to crop: whatever the preview is showing right now
    // (a freshly chosen file, a gallery pick, or the article's saved image).
    openBtn.addEventListener('click', function () {
        const src = preview.getAttribute('src');

        if (preview.hidden || !src) {
            alert('আগে একটি ছবি বাছুন বা আপলোড করুন — তারপর সেটি ছেঁটে নেওয়া যাবে।');

            return;
        }

        image.onload = function () { openModal(openBtn); };
        image.onerror = function () { alert('ছবিটি খোলা যায়নি, তাই ছেঁটা যাচ্ছে না।'); };

        image.src = src;

        if (image.complete && image.naturalWidth > 0) {
            image.onload = null;
            openModal(openBtn);
        }
    });

    // The cropped result goes back into the file input, so it is uploaded and saved
    // like any other photo the editor picked. Nothing is cropped by itself.
    applyBtn.addEventListener('click', function () {
        if (!image.naturalWidth) {
            closeModal(true);

            return;
        }

        const x = Math.round(rect.x);
        const y = Math.round(rect.y);
        const w = Math.max(1, Math.round(rect.w));
        const h = Math.max(1, Math.round(rect.h));

        const canvas = document.createElement('canvas');

        canvas.width = w;
        canvas.height = h;

        const ctx = canvas.getContext('2d');

        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, w, h);
        ctx.drawImage(image, x, y, w, h, 0, 0, w, h);

        // A photo served from another site cannot be read back by the canvas.
        try {
            canvas.toBlob(function (blob) {
                if (!blob) {
                    if (info) { info.textContent = 'ছবি ছেঁটা যায়নি — আবার চেষ্টা করুন।'; }

                    return;
                }

                try {
                    const transfer = new DataTransfer();

                    transfer.items.add(new File([blob], 'crop.jpg', { type: 'image/jpeg' }));
                    fileInput.files = transfer.files;
                } catch (e) {
                    if (info) { info.textContent = 'এই ব্রাউজারে ছেঁটা ছবি বসানো যাচ্ছে না।'; }

                    return;
                }

                if (galleryInput) { galleryInput.value = ''; }
                if (galleryNote) { galleryNote.hidden = true; }

                if (croppedUrl) { URL.revokeObjectURL(croppedUrl); }

                croppedUrl = URL.createObjectURL(blob);
                preview.src = croppedUrl;
                preview.hidden = false;

                closeModal(true);
            }, 'image/jpeg', 0.92);
        } catch (e) {
            if (info) { info.textContent = 'ছবিটি অন্য সাইট থেকে আসায় এখানে ছেঁটা যাচ্ছে না; ছবিটি নামিয়ে ছেঁটে আবার আপলোড করুন।'; }
        }
    });
})();
