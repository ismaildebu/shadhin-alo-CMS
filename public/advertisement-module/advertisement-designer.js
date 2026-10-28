
(() => {
    'use strict';

    const $ = (id) => document.getElementById(id);
    const canvas = $('adCanvas');
    const form = $('adForm');

    if (!canvas || !form) return;

    const ctx = canvas.getContext('2d');
    const stage = document.querySelector('.ad-stage');
    const designInput = $('designData');
    const exportInput = $('exportImage');

    const fields = {
        text: $('propText'),
        color: $('propColor'),
        font: $('propFont'),
        width: $('propWidth'),
        height: $('propHeight'),
        rotate: $('propRotate')
    };

    const sizes = {
        '1200x628': [1200, 628],
        '970x250': [970, 250],
        '300x250': [300, 250],
        '728x90': [728, 90],
        '1080x1080': [1080, 1080]
    };

    const templates = {
        eid: {
            background: '#064e3b',
            items: [
                { type: 'circle', x: 45, y: 40, width: 100, height: 100, color: '#d6a83e' },
                { type: 'circle', x: 1055, y: 40, width: 100, height: 100, color: '#d6a83e' },
                { type: 'text', x: 130, y: 120, width: 940, height: 95, text: 'ঈদ মোবারক', color: '#ffffff', fontSize: 76 },
                { type: 'text', x: 150, y: 245, width: 900, height: 75, text: 'বিশেষ অফার · ৫০% পর্যন্ত ছাড়', color: '#ffe5a1', fontSize: 40 },
                { type: 'rect', x: 390, y: 400, width: 420, height: 90, color: '#d6a83e' },
                { type: 'text', x: 415, y: 410, width: 370, height: 65, text: 'এখনই কিনুন', color: '#064e3b', fontSize: 40 }
            ]
        },
        sale: {
            background: '#fff1e8',
            items: [
                { type: 'rect', x: 25, y: 25, width: 1150, height: 578, color: '#b91c1c' },
                { type: 'text', x: 80, y: 90, width: 1040, height: 115, text: 'BIG SALE', color: '#ffffff', fontSize: 94 },
                { type: 'text', x: 80, y: 245, width: 1040, height: 95, text: '৫০% পর্যন্ত ছাড়', color: '#ffe66d', fontSize: 64 },
                { type: 'text', x: 100, y: 425, width: 1000, height: 65, text: 'আজই অর্ডার করুন', color: '#ffffff', fontSize: 42 }
            ]
        },
        tech: {
            background: '#dbeafe',
            items: [
                { type: 'text', x: 55, y: 90, width: 680, height: 100, text: 'নতুন প্রযুক্তি', color: '#123b70', fontSize: 68 },
                { type: 'text', x: 60, y: 220, width: 670, height: 100, text: 'আপনার পছন্দের পণ্য', color: '#234c80', fontSize: 40 },
                { type: 'rect', x: 60, y: 400, width: 360, height: 90, color: '#075de8' },
                { type: 'text', x: 75, y: 410, width: 330, height: 65, text: 'এখনই দেখুন', color: '#ffffff', fontSize: 36 },
                { type: 'circle', x: 810, y: 145, width: 250, height: 250, color: '#93c5fd' },
                { type: 'rect', x: 860, y: 225, width: 150, height: 90, color: '#2563eb' }
            ]
        },
        food: {
            background: '#422006',
            items: [
                { type: 'text', x: 60, y: 85, width: 1080, height: 105, text: 'স্পেশাল বার্গার', color: '#fbbf24', fontSize: 68 },
                { type: 'text', x: 70, y: 220, width: 1050, height: 85, text: 'সুস্বাদু খাবার, সাশ্রয়ী দাম', color: '#ffffff', fontSize: 40 },
                { type: 'circle', x: 790, y: 330, width: 210, height: 210, color: '#b45309' },
                { type: 'rect', x: 70, y: 405, width: 350, height: 90, color: '#dc2626' },
                { type: 'text', x: 85, y: 415, width: 320, height: 65, text: 'অর্ডার করুন', color: '#ffffff', fontSize: 36 }
            ]
        }
    };

    let elements = [];
    let selectedId = null;
    let nextId = 1;
    let background = '#ffffff';
    let zoom = 1;
    let drag = null;

    const clamp = (n, min, max) => Math.max(min, Math.min(max, n));
    const selected = () => elements.find((e) => e.id === selectedId) || null;

    function addElement(type, options = {}) {
        const item = {
            id: 'el-' + nextId++,
            type,
            x: 40,
            y: 40,
            width: type === 'text' ? 320 : 150,
            height: type === 'text' ? 70 : 100,
            text: type === 'text' ? 'এখানে লেখা লিখুন' : '',
            color: '#ff6b35',
            fontSize: 42,
            rotation: 0,
            src: '',
            ...options
        };

        delete item.imageObject;

        item.width = clamp(Number(item.width) || 100, 10, canvas.width);
        item.height = clamp(Number(item.height) || 70, 10, canvas.height);
        item.x = clamp(Number(item.x) || 0, 0, canvas.width - item.width);
        item.y = clamp(Number(item.y) || 0, 0, canvas.height - item.height);

        if (options.imageObject) item.imageObject = options.imageObject;

        elements.push(item);
        selectedId = item.id;
        render();
        syncProperties();
        saveDesign();
    }

    function drawItem(item) {
        ctx.save();

        ctx.translate(item.x + item.width / 2, item.y + item.height / 2);
        ctx.rotate((Number(item.rotation) || 0) * Math.PI / 180);

        if (item.type === 'rect') {
            ctx.fillStyle = item.color;
            ctx.fillRect(-item.width / 2, -item.height / 2, item.width, item.height);
        }

        if (item.type === 'circle') {
            ctx.fillStyle = item.color;
            ctx.beginPath();
            ctx.ellipse(0, 0, item.width / 2, item.height / 2, 0, 0, Math.PI * 2);
            ctx.fill();
        }

        if (item.type === 'text') {
            ctx.fillStyle = item.color;
            ctx.font = `${item.fontSize || 42}px sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            const lines = String(item.text || '').split('\n');
            const lineHeight = (item.fontSize || 42) * 1.25;
            const startY = -(lines.length - 1) * lineHeight / 2;

            lines.forEach((line, index) => {
                ctx.fillText(line, 0, startY + index * lineHeight, Math.max(1, item.width - 8));
            });
        }

        if (item.type === 'image' && item.imageObject?.complete) {
            ctx.drawImage(item.imageObject, -item.width / 2, -item.height / 2, item.width, item.height);
        }

        ctx.restore();
    }

    function drawSelection(item) {
        ctx.save();
        ctx.translate(item.x + item.width / 2, item.y + item.height / 2);
        ctx.rotate((Number(item.rotation) || 0) * Math.PI / 180);

        ctx.strokeStyle = '#1473e6';
        ctx.lineWidth = 2;
        ctx.setLineDash([5, 3]);
        ctx.strokeRect(-item.width / 2, -item.height / 2, item.width, item.height);
        ctx.setLineDash([]);

        const handles = [
            [-item.width / 2, -item.height / 2], [0, -item.height / 2],
            [item.width / 2, -item.height / 2], [item.width / 2, 0],
            [item.width / 2, item.height / 2], [0, item.height / 2],
            [-item.width / 2, item.height / 2], [-item.width / 2, 0]
        ];

        handles.forEach(([x, y]) => {
            ctx.beginPath();
            ctx.arc(x, y, 6, 0, Math.PI * 2);
            ctx.fillStyle = '#ffffff';
            ctx.fill();
            ctx.stroke();
        });

        ctx.restore();
    }

    function render() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = background;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        elements.forEach(drawItem);

        const item = selected();
        if (item) drawSelection(item);
    }

    function syncProperties() {
        const item = selected();

        Object.values(fields).forEach((field) => {
            if (field) field.disabled = !item;
        });

        if (!item) {
            Object.values(fields).forEach((field) => {
                if (field) field.value = '';
            });
            return;
        }

        fields.text.value = item.text || '';
        fields.text.disabled = item.type !== 'text';
        fields.color.value = /^#[0-9a-f]{6}$/i.test(item.color) ? item.color : '#ff6b35';
        fields.color.disabled = item.type === 'image';
        fields.font.value = item.fontSize || 42;
        fields.font.disabled = item.type !== 'text';
        fields.width.value = Math.round(item.width);
        fields.height.value = Math.round(item.height);
        fields.rotate.value = Math.round(item.rotation || 0);
    }

    function saveDesign() {
        if (!designInput) return;

        const cleanElements = elements.map((item) => {
            const copy = { ...item };
            delete copy.imageObject;
            return copy;
        });

        designInput.value = JSON.stringify({
            version: 1,
            width: canvas.width,
            height: canvas.height,
            background,
            elements: cleanElements
        });
    }

    function loadDesign(raw) {
        if (!raw) return false;

        try {
            const data = typeof raw === 'string' ? JSON.parse(raw) : raw;
            if (!data || !Array.isArray(data.elements)) return false;

            if (data.width && data.height) {
                canvas.width = Number(data.width);
                canvas.height = Number(data.height);
            }

            background = data.background || '#ffffff';
            elements = [];
            nextId = 1;

            data.elements.forEach((entry) => {
                const item = { ...entry, id: entry.id || 'el-' + nextId++ };

                if (item.type === 'image' && item.src) {
                    const img = new Image();
                    img.onload = render;
                    img.src = item.src;
                    item.imageObject = img;
                }

                elements.push(item);
                nextId++;
            });

            selectedId = elements.length ? elements[elements.length - 1].id : null;
            return true;
        } catch (error) {
            console.warn('ডিজাইন লোড করা যায়নি।', error);
            return false;
        }
    }

    function pointFromEvent(event) {
        const rect = canvas.getBoundingClientRect();
        return {
            x: (event.clientX - rect.left) * canvas.width / rect.width,
            y: (event.clientY - rect.top) * canvas.height / rect.height
        };
    }

    function handleAt(point, item) {
        const positions = [
            ['nw', item.x, item.y],
            ['n', item.x + item.width / 2, item.y],
            ['ne', item.x + item.width, item.y],
            ['e', item.x + item.width, item.y + item.height / 2],
            ['se', item.x + item.width, item.y + item.height],
            ['s', item.x + item.width / 2, item.y + item.height],
            ['sw', item.x, item.y + item.height],
            ['w', item.x, item.y + item.height / 2]
        ];

        for (const [name, x, y] of positions) {
            if (Math.abs(point.x - x) < 11 && Math.abs(point.y - y) < 11) return name;
        }

        return null;
    }

    function hitAt(point, item) {
        return point.x >= item.x && point.x <= item.x + item.width &&
               point.y >= item.y && point.y <= item.y + item.height;
    }

    canvas.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) return;

        const point = pointFromEvent(event);
        const current = selected();
        const handle = current ? handleAt(point, current) : null;

        let item = handle ? current : null;
        if (!item) item = [...elements].reverse().find((e) => hitAt(point, e));

        if (!item) {
            selectedId = null;
            drag = null;
            render();
            syncProperties();
            return;
        }

        selectedId = item.id;
        drag = {
            item,
            handle: handle || null,
            startX: point.x,
            startY: point.y,
            x: item.x,
            y: item.y,
            width: item.width,
            height: item.height
        };

        canvas.setPointerCapture(event.pointerId);
        render();
        syncProperties();
        event.preventDefault();
    });

    canvas.addEventListener('pointermove', (event) => {
        if (!drag) return;

        const point = pointFromEvent(event);
        const dx = point.x - drag.startX;
        const dy = point.y - drag.startY;
        const item = drag.item;

        if (!drag.handle) {
            item.x = clamp(drag.x + dx, 0, canvas.width - item.width);
            item.y = clamp(drag.y + dy, 0, canvas.height - item.height);
        } else {
            let left = drag.x;
            let top = drag.y;
            let right = drag.x + drag.width;
            let bottom = drag.y + drag.height;

            if (drag.handle.includes('w')) left += dx;
            if (drag.handle.includes('e')) right += dx;
            if (drag.handle.includes('n')) top += dy;
            if (drag.handle.includes('s')) bottom += dy;

            left = clamp(left, 0, canvas.width - 10);
            top = clamp(top, 0, canvas.height - 10);
            right = clamp(right, left + 10, canvas.width);
            bottom = clamp(bottom, top + 10, canvas.height);

            item.x = left;
            item.y = top;
            item.width = right - left;
            item.height = bottom - top;
        }

        render();
        syncProperties();
        saveDesign();
    });

    function stopDrag() {
        if (drag) saveDesign();
        drag = null;
    }

    canvas.addEventListener('pointerup', stopDrag);
    canvas.addEventListener('pointercancel', stopDrag);

    document.querySelectorAll('[data-add]').forEach((button) => {
        button.addEventListener('click', () => addElement(button.dataset.add));
    });

    function bindField(field, property, convert = (v) => v) {
        if (!field) return;

        field.addEventListener('input', () => {
            const item = selected();
            if (!item) return;

            item[property] = convert(field.value);
            render();
            saveDesign();
        });
    }

    bindField(fields.text, 'text');
    bindField(fields.color, 'color');
    bindField(fields.font, 'fontSize', (v) => clamp(Number(v) || 8, 8, 160));
    bindField(fields.width, 'width', (v) => clamp(Number(v) || 10, 10, canvas.width));
    bindField(fields.height, 'height', (v) => clamp(Number(v) || 10, 10, canvas.height));
    bindField(fields.rotate, 'rotation', (v) => clamp(Number(v) || 0, -180, 180));

    $('imageUpload')?.addEventListener('change', (event) => {
        const file = event.target.files?.[0];
        if (!file) return;

        if (!file.type.startsWith('image/')) {
            alert('একটি বৈধ ছবি নির্বাচন করো।');
            event.target.value = '';
            return;
        }

        const reader = new FileReader();

        reader.onload = () => {
            const img = new Image();

            img.onload = () => {
                const scale = Math.min(1, 300 / img.width, 250 / img.height);

                addElement('image', {
                    x: 30,
                    y: 30,
                    width: Math.max(10, img.width * scale),
                    height: Math.max(10, img.height * scale),
                    src: reader.result,
                    imageObject: img
                });
            };

            img.src = reader.result;
        };

        reader.readAsDataURL(file);
        event.target.value = '';
    });

    function applyTemplate(name) {
        const template = templates[name];
        if (!template) return;

        if (elements.length && !confirm('বর্তমান ডিজাইন বদলে নতুন টেমপ্লেট বসবে। এগোবে?')) return;

        background = template.background;
        elements = [];
        selectedId = null;
        nextId = 1;

        template.items.forEach((item) => {
            elements.push({ id: 'el-' + nextId++, ...item });
        });

        selectedId = elements.length ? elements[elements.length - 1].id : null;

        const sizeSelect = $('canvasSize');
        if (sizeSelect) sizeSelect.value = '1200x628';

        canvas.width = 1200;
        canvas.height = 628;
        setZoom(zoom);
        render();
        syncProperties();
        saveDesign();
    }

    document.querySelectorAll('[data-template]').forEach((button) => {
        button.addEventListener('click', () => applyTemplate(button.dataset.template));
    });

    document.querySelectorAll('[data-template-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            const filter = button.dataset.templateFilter;

            document.querySelectorAll('[data-template-filter]').forEach((tab) => {
                tab.classList.toggle('active', tab === button);
            });

            document.querySelectorAll('[data-template]').forEach((card) => {
                card.hidden = filter !== 'all' && card.dataset.category !== filter;
            });
        });
    });

    $('clearTemplate')?.addEventListener('click', () => {
        if (elements.length && !confirm('ক্যানভাসের সব উপাদান মুছে ফেলবে?')) return;

        elements = [];
        selectedId = null;
        background = '#ffffff';
        render();
        syncProperties();
        saveDesign();
    });

    $('deleteElement')?.addEventListener('click', () => {
        if (!selectedId) return;

        elements = elements.filter((item) => item.id !== selectedId);
        selectedId = null;
        render();
        syncProperties();
        saveDesign();
    });

    function setZoom(value) {
        zoom = clamp(value, 0.1, 2);
        canvas.style.width = `${canvas.width * zoom}px`;
        canvas.style.height = `${canvas.height * zoom}px`;

        const label = $('zoomLabel');
        if (label) label.textContent = `${Math.round(zoom * 100)}%`;
    }

    $('zoomIn')?.addEventListener('click', () => setZoom(zoom + 0.1));
    $('zoomOut')?.addEventListener('click', () => setZoom(zoom - 0.1));

    $('zoomFit')?.addEventListener('click', () => {
        const available = Math.max(100, (stage?.clientWidth || canvas.width) - 40);
        setZoom(Math.min(1, available / canvas.width));
    });

    $('canvasSize')?.addEventListener('change', (event) => {
        const size = sizes[event.target.value];
        if (!size) return;

        canvas.width = size[0];
        canvas.height = size[1];

        elements.forEach((item) => {
            item.x = clamp(item.x, 0, canvas.width - 10);
            item.y = clamp(item.y, 0, canvas.height - 10);
            item.width = Math.min(item.width, canvas.width);
            item.height = Math.min(item.height, canvas.height);
        });

        setZoom(zoom);
        render();
        saveDesign();
    });

    function exportImage(type) {
        render();

        const output = document.createElement('canvas');
        output.width = canvas.width;
        output.height = canvas.height;

        const out = output.getContext('2d');

        if (type === 'jpg') {
            out.fillStyle = '#ffffff';
            out.fillRect(0, 0, output.width, output.height);
        }

        out.drawImage(canvas, 0, 0);

        const mime = type === 'jpg' ? 'image/jpeg' : 'image/png';

        output.toBlob((blob) => {
            if (!blob) {
                alert('ছবি তৈরি করা যায়নি। আবার চেষ্টা করো।');
                return;
            }

            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');

            link.href = url;
            link.download = `shadhinalo-advertisement.${type}`;
            link.click();

            setTimeout(() => URL.revokeObjectURL(url), 1000);
        }, mime, 0.92);
    }

    $('exportPng')?.addEventListener('click', () => exportImage('png'));
    $('downloadPng')?.addEventListener('click', () => exportImage('png'));
    $('downloadJpg')?.addEventListener('click', () => exportImage('jpg'));

    form.addEventListener('submit', () => {
        render();
        saveDesign();
    });

    canvas.width = 1200;
    canvas.height = 628;

    const initial = window.advertisementInitial || designInput?.value || null;

    if (!loadDesign(initial)) {
        background = '#ffffff';
        elements = [];
        selectedId = null;
    }

    setZoom(0.7);
    render();
    syncProperties();
    saveDesign();
})();
