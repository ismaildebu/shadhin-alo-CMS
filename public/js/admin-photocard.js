(function (global) {
    'use strict';

    const SIZES = {
        landscape: [1200, 630],
        square: [1080, 1080],
        portrait: [1080, 1350]
    };

    const FONT = '"Noto Sans Bengali","Nirmala UI","Shonar Bangla","Vrinda",sans-serif';
    const INK = '#0b1220';
    const images = new Map();

    function loadImage(src) {
        if (!src) { return Promise.resolve(null); }

        if (!images.has(src)) {
            images.set(src, new Promise(function (resolve) {
                const img = new Image();
                img.onload = function () { resolve(img); };
                img.onerror = function () { resolve(null); };
                img.src = src;
            }));
        }

        return images.get(src);
    }

    // বাংলা ফন্ট লোড হওয়ার আগে আঁকলে ভুল ফন্টে বসে, তাই আগে অপেক্ষা।
    function fontsReady() {
        if (!document.fonts || !document.fonts.load) { return Promise.resolve(); }

        return Promise.all([
            document.fonts.load('400 32px "Noto Sans Bengali"', 'অআকখগ'),
            document.fonts.load('500 32px "Noto Sans Bengali"', 'অআকখগ'),
            document.fonts.load('700 32px "Noto Sans Bengali"', 'অআকখগ'),
            document.fonts.ready
        ]).catch(function () {});
    }

    function roundRect(ctx, x, y, w, h, r) {
        const rr = Math.min(r, w / 2, h / 2);

        ctx.beginPath();
        ctx.moveTo(x + rr, y);
        ctx.arcTo(x + w, y, x + w, y + h, rr);
        ctx.arcTo(x + w, y + h, x, y + h, rr);
        ctx.arcTo(x, y + h, x, y, rr);
        ctx.arcTo(x, y, x + w, y, rr);
        ctx.closePath();
    }

    // focusY = 0 হলে ওপরের অংশ থেকে কাটা শুরু, ০.৫ = মাঝখান থেকে (ডিফল্ট)
    function drawCover(ctx, img, x, y, w, h, focusY) {
        const scale = Math.max(w / img.width, h / img.height);
        const sw = w / scale;
        const sh = h / scale;
        const fy = focusY === undefined ? 0.5 : focusY;

        ctx.drawImage(img, (img.width - sw) / 2, (img.height - sh) * fy, sw, sh, x, y, w, h);
    }

    // রংটি হালকা হলে গাঢ় লেখা, গাঢ় হলে সাদা লেখা
    function textOn(hex) {
        const m = /^#([0-9a-f]{6})$/i.exec(hex || '');

        if (!m) { return '#ffffff'; }

        const n = parseInt(m[1], 16);
        const r = (n >> 16) & 255;
        const g = (n >> 8) & 255;
        const b = n & 255;

        return (0.299 * r + 0.587 * g + 0.114 * b) > 170 ? '#111827' : '#ffffff';
    }

    function darken(hex, amount) {
        const m = /^#([0-9a-f]{6})$/i.exec(hex || '');

        if (!m) { return '#0b1220'; }

        const n = parseInt(m[1], 16);
        const f = 1 - amount;

        return 'rgb(' + Math.round(((n >> 16) & 255) * f) + ',' + Math.round(((n >> 8) & 255) * f) + ',' + Math.round((n & 255) * f) + ')';
    }

    function rgba(hex, alpha) {
        const m = /^#([0-9a-f]{6})$/i.exec(hex || '');

        if (!m) { return 'rgba(255,255,255,' + alpha + ')'; }

        const n = parseInt(m[1], 16);

        return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + alpha + ')';
    }

    function softShadow(ctx, blur, offsetY, alpha) {
        ctx.shadowColor = 'rgba(2,6,18,' + (alpha === undefined ? 0.34 : alpha) + ')';
        ctx.shadowBlur = blur;
        ctx.shadowOffsetY = offsetY;
    }

    function clearShadow(ctx) {
        ctx.shadowColor = 'rgba(0,0,0,0)';
        ctx.shadowBlur = 0;
        ctx.shadowOffsetY = 0;
    }

    function accentGradient(ctx, x0, y0, x1, y1, accent) {
        const g = ctx.createLinearGradient(x0, y0, x1, y1);

        g.addColorStop(0, accent);
        g.addColorStop(1, darken(accent, 0.38));

        return g;
    }

    function brandBackground(ctx, w, h, accent) {
        const g = ctx.createLinearGradient(0, 0, w, h);

        g.addColorStop(0, darken(accent, 0.35));
        g.addColorStop(0.55, darken(accent, 0.72));
        g.addColorStop(1, '#050b16');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, w, h);
    }

    function wrapLines(ctx, text, maxWidth) {
        const words = String(text).replace(/\s+/g, ' ').trim().split(' ');
        const lines = [];
        let line = '';

        words.forEach(function (word) {
            const test = line ? line + ' ' + word : word;

            if (!line || ctx.measureText(test).width <= maxWidth) {
                line = test;
            } else {
                lines.push(line);
                line = word;
            }
        });

        if (line) { lines.push(line); }

        return lines;
    }

    // শিরোনাম জায়গায় না ধরলে ফন্ট ছোট করে; তবুও না ধরলে শেষে "…" দিয়ে কেটে দেয়।
    function fitHeadline(ctx, text, maxW, maxH, maxSize, minSize, maxLines) {
        for (let size = maxSize; size >= minSize; size -= 2) {
            ctx.font = '700 ' + size + 'px ' + FONT;

            const lines = wrapLines(ctx, text, maxW);
            const lh = size * 1.32;

            if (lines.length <= maxLines && lines.length * lh <= maxH) {
                return { size: size, lines: lines, lh: lh };
            }
        }

        ctx.font = '700 ' + minSize + 'px ' + FONT;

        let lines = wrapLines(ctx, text, maxW);
        const lh = minSize * 1.32;
        const cap = Math.max(1, Math.min(maxLines, Math.floor(maxH / lh)));

        if (lines.length > cap) {
            lines = lines.slice(0, cap);

            let last = lines[cap - 1];

            while (last.length > 1 && ctx.measureText(last + '…').width > maxW) {
                last = last.slice(0, -1);
            }

            lines[cap - 1] = last.trimEnd() + '…';
        }

        return { size: minSize, lines: lines, lh: lh };
    }

    // ছোট অনুচ্ছেদ: নির্দিষ্ট উচ্চতায় যতটা ধরে, শেষে "…" দিয়ে কাটে।
    function fitParagraph(ctx, text, maxW, maxH, size, weight) {
        ctx.font = (weight || 400) + ' ' + size + 'px ' + FONT;

        const lh = Math.round(size * 1.5);
        const cap = Math.floor(maxH / lh);

        if (cap < 1 || !text) { return { lines: [], lh: lh, size: size }; }

        let lines = wrapLines(ctx, text, maxW);

        if (lines.length > cap) {
            lines = lines.slice(0, cap);

            let last = lines[cap - 1];

            while (last.length > 1 && ctx.measureText(last + '…').width > maxW) {
                last = last.slice(0, -1);
            }

            lines[cap - 1] = last.replace(/\s+$/, '') + '…';
        }

        return { lines: lines, lh: lh, size: size };
    }

    function drawParagraph(ctx, p, x, top, color, align, weight) {
        ctx.font = (weight || 400) + ' ' + p.size + 'px ' + FONT;
        ctx.fillStyle = color;
        ctx.textBaseline = 'top';
        ctx.textAlign = align || 'left';

        p.lines.forEach(function (line, i) {
            ctx.fillText(line, x, top + i * p.lh);
        });

        ctx.textAlign = 'left';
    }

    function chipSize(c) { return Math.round(c.W * 0.026); }
    function logoHeight(c) { return Math.round(c.W * 0.045); }
    function footerHeight(c) { return Math.round(c.W * 0.04); }

    function drawChip(ctx, text, x, y, color, size) {
        ctx.font = '700 ' + size + 'px ' + FONT;

        const padX = size * 0.9;
        const w = ctx.measureText(text).width + padX * 2;
        const h = Math.round(size * 2.05);

        ctx.save();
        softShadow(ctx, size * 0.9, size * 0.25, 0.24);
        ctx.fillStyle = accentGradient(ctx, x, y, x + w, y + h, color);
        roundRect(ctx, x, y, w, h, h / 2);
        ctx.fill();
        ctx.restore();

        ctx.fillStyle = textOn(color);
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'left';
        ctx.fillText(text, x + padX, y + h / 2 + 1);

        return { w: w, h: h };
    }

    // ছবির ওপরে কাঁচের মতো হালকা পিল
    function drawGlassPill(ctx, text, x, y, size) {
        ctx.font = '700 ' + size + 'px ' + FONT;

        const padX = size * 0.9;
        const w = ctx.measureText(text).width + padX * 2;
        const h = Math.round(size * 2.05);

        ctx.save();
        softShadow(ctx, size * 0.8, size * 0.2, 0.2);
        ctx.fillStyle = 'rgba(255,255,255,.16)';
        roundRect(ctx, x, y, w, h, h / 2);
        ctx.fill();
        clearShadow(ctx);
        ctx.strokeStyle = 'rgba(255,255,255,.36)';
        ctx.lineWidth = Math.max(1.5, size * 0.07);
        ctx.stroke();
        ctx.restore();

        ctx.fillStyle = '#ffffff';
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'left';
        ctx.fillText(text, x + padX, y + h / 2 + 1);

        return { w: w, h: h };
    }

    function drawLogoPill(ctx, logo, rightX, y, maxH) {
        let w = logo.width * (maxH / logo.height);
        let h = maxH;
        const maxW = rightX * 0.42;

        if (w > maxW) {
            h = h * (maxW / w);
            w = maxW;
        }

        const padX = maxH * 0.3;
        const padY = maxH * 0.2;
        const bw = w + padX * 2;
        const bh = h + padY * 2;

        ctx.save();
        softShadow(ctx, maxH * 0.7, maxH * 0.2, 0.22);
        ctx.fillStyle = 'rgba(255,255,255,.96)';
        roundRect(ctx, rightX - bw, y, bw, bh, bh * 0.3);
        ctx.fill();
        clearShadow(ctx);
        ctx.strokeStyle = 'rgba(15,23,42,.08)';
        ctx.lineWidth = 1.5;
        ctx.stroke();
        ctx.restore();

        ctx.drawImage(logo, rightX - bw + padX, y + padY, w, h);

        return { w: bw, h: bh };
    }

    // বামে ক্যাটাগরির ফিতা, ডানে লোগো
    function topBar(ctx, c, opts) {
        const o = opts || {};

        if (c.o.category && c.d.category) {
            if (o.glass) {
                drawGlassPill(ctx, c.d.category, c.pad, c.pad, chipSize(c));
            } else {
                drawChip(ctx, c.d.category, c.pad, c.pad, o.chipColor || c.accent, chipSize(c));
            }
        }

        if (c.o.logo && c.logo) {
            drawLogoPill(ctx, c.logo, c.W - c.pad, c.pad, logoHeight(c));
        }
    }

    function drawRule(ctx, x1, x2, y, color, width) {
        ctx.save();
        ctx.strokeStyle = color || 'rgba(255,255,255,.22)';
        ctx.lineWidth = width || 1.5;
        ctx.beginPath();
        ctx.moveTo(x1, y);
        ctx.lineTo(x2, y);
        ctx.stroke();
        ctx.restore();
    }

    // বামে সাইটের নাম, ডানে তারিখ ও ঠিকানা — মাঝের দাগটি আঁকা, ফন্টের '·' প্রায় দেখাই যায় না
    function drawFooter(ctx, c, y, left, right, nameColor, textColor, size, lineColor) {
        const fs = size || Math.round(c.W * 0.025);
        const name = c.d.siteName || '';
        const items = [];

        if (c.o.date) {
            const when = c.d.dateBn || c.d.date;

            if (when) { items.push(String(when)); }
        }

        if (c.d.footer) { items.push(String(c.d.footer)); }

        if (lineColor) {
            drawRule(ctx, left, right, y - fs * 2.2, lineColor, 1.5);
        }

        ctx.textBaseline = 'bottom';
        ctx.textAlign = 'left';
        ctx.fillStyle = nameColor;
        ctx.font = '700 ' + fs + 'px ' + FONT;
        ctx.fillText(name, left, y);

        if (!items.length) { return; }

        ctx.font = '400 ' + fs + 'px ' + FONT;

        const gap = fs * 0.8;
        const dot = Math.max(2.5, fs * 0.13);
        const total = function () {
            return items.reduce(function (sum, t) { return sum + ctx.measureText(t).width; }, 0)
                + (items.length - 1) * (gap * 2 + dot * 2);
        };

        // সরু কলামে দুই লেখা ধাক্কা খায় — জায়গা কম হলে শেষের লেখা কেটে দিতে হয়।
        const room = right - left - ctx.measureText(name).width - fs * 2.4;
        let cut = false;

        while (total() > room && items[items.length - 1].length > 1) {
            items[items.length - 1] = items[items.length - 1].slice(0, -1);
            cut = true;
        }

        if (cut) {
            items[items.length - 1] = items[items.length - 1].replace(/\s+$/, '') + '…';
        }

        let x = right - total();

        ctx.fillStyle = textColor;

        items.forEach(function (text, i) {
            if (i) {
                x += gap;

                ctx.beginPath();
                ctx.arc(x + dot, y - fs * 0.38, dot, 0, Math.PI * 2);
                ctx.fill();

                x += dot * 2 + gap;
            }

            ctx.fillText(text, x, y);
            x += ctx.measureText(text).width;
        });
    }

    function drawLines(ctx, fit, x, top, color, align) {
        ctx.fillStyle = color;
        ctx.font = '700 ' + fit.size + 'px ' + FONT;
        ctx.textBaseline = 'top';
        ctx.textAlign = align || 'left';

        fit.lines.forEach(function (line, i) {
            ctx.fillText(line, x, top + i * fit.lh);
        });

        ctx.textAlign = 'left';
    }

    // থালায় বসার মার্ক: ফেভিকন, না থাকলে লোগো, না থাকলে সাইটের নামের প্রথম অক্ষর
    function drawMark(ctx, c, cx, cy, r) {
        const img = c.emblem || c.logo;

        if (img) {
            const s = Math.min(r * 1.7 / img.width, r * 1.7 / img.height);

            ctx.drawImage(img, cx - (img.width * s) / 2, cy - (img.height * s) / 2, img.width * s, img.height * s);

            return;
        }

        const name = String(c.d.siteName || '').trim();

        if (!name) { return; }

        ctx.fillStyle = c.accent;
        ctx.font = '700 ' + Math.round(r * 1.2) + 'px ' + FONT;
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'center';
        ctx.fillText(name.charAt(0), cx, cy + 2);
        ctx.textAlign = 'left';
    }

    // রঙে সাদা মিশিয়ে কার্ডের হালকা পটভূমি
    function tint(hex, amount) {
        const m = /^#([0-9a-f]{6})$/i.exec(hex || '');

        if (!m) { return '#f2f3f5'; }

        const n = parseInt(m[1], 16);
        const part = function (shift) {
            const channel = (n >> shift) & 255;

            return Math.round(channel + (255 - channel) * amount);
        };

        return 'rgb(' + part(16) + ',' + part(8) + ',' + part(0) + ')';
    }

    // লোগো বাক্স ছাড়া সরাসরি ডানে সেট করা
    function drawLogoPlain(ctx, logo, rightX, y, maxH) {
        let w = logo.width * (maxH / logo.height);
        let h = maxH;
        const maxW = rightX * 0.42;

        if (w > maxW) {
            h = h * (maxW / w);
            w = maxW;
        }

        ctx.drawImage(logo, rightX - w, y, w, h);

        return { w: w, h: h };
    }

    // উপরের মেটা লাইন: বিভাগ | তারিখ
    function drawMetaLine(ctx, c, x, y, color, size) {
        const parts = [];

        if (c.o.category && c.d.category) { parts.push(c.d.category); }
        if (c.o.date) { parts.push(c.d.dateBn || c.d.date); }

        const text = parts.join('  |  ');

        if (!text) { return 0; }

        ctx.font = '400 ' + size + 'px ' + FONT;
        ctx.fillStyle = color;
        ctx.textBaseline = 'top';
        ctx.fillText(text, x, y);

        return Math.round(size * 1.4);
    }

    // পটভূমিতে বিন্দুর জাল
    function dotGrid(ctx, x, y, w, h, step, r, color) {
        ctx.save();
        ctx.fillStyle = color;

        for (let gy = y + step / 2; gy < y + h; gy += step) {
            for (let gx = x + step / 2; gx < x + w; gx += step) {
                ctx.beginPath();
                ctx.arc(gx, gy, r, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        ctx.restore();
    }

    // ছবি বসানো: বক্স ভরে ক্রপ, ওপরের দিকে ঝুঁকে — মাঝখান থেকে কাটলে সবার মাথা কেটে যায়
    function photoOrBrand(ctx, c, x, y, w, h, accent) {
        if (!c.photo) {
            ctx.save();
            ctx.beginPath();
            ctx.rect(x, y, w, h);
            ctx.clip();
            brandBackground(ctx, x + w, y + h, accent);
            ctx.restore();

            return;
        }

        drawCover(ctx, c.photo, x, y, w, h, 0.3);
    }

    const TEMPLATES = {

        // ওপরে ছবি, জোড়ে সাদা থালায় মার্ক, নিচে হালকা প্যানেলে কেন্দ্র করা শিরোনাম (প্রথম লাইন লাল)
        paper: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const land = c.o.size === 'landscape';
            const NAVY = '#16294a';
            const GRAY = '#64748b';
            const disc = Math.round(W * 0.07);
            const pad = Math.round(W * (land ? 0.045 : 0.06));
            const photoW = land ? Math.round(W * 0.46) : W;
            const hasEx = !!(c.o.excerpt && c.d.excerpt);
            const photoH = land ? H : Math.round(H * (hasEx ? (c.o.size === 'square' ? 0.44 : 0.48) : (c.o.size === 'square' ? 0.5 : 0.54)));
            const x0 = land ? photoW + Math.round(W * 0.05) : pad;
            const x1 = land ? W - Math.round(W * 0.05) : W - pad;
            const footSize = Math.round(W * (land ? 0.019 : 0.025));
            const footY = H - pad;
            const areaBottom = footY - footSize * 2.2 - 26;

            ctx.fillStyle = '#f3f4f6';
            ctx.fillRect(0, 0, W, H);

            ctx.save();
            ctx.beginPath();
            ctx.rect(0, 0, photoW, photoH);
            ctx.clip();
            photoOrBrand(ctx, c, 0, 0, photoW, photoH, c.accent);
            ctx.restore();

            const cs = Math.round(chipSize(c) * (land ? 0.9 : 1));
            const hasMark = c.o.logo && (c.emblem || c.logo || c.d.siteName);
            let top;

            if (land) {
                const markH = Math.round(logoHeight(c) * 0.8);
                let markBox = 0;

                if (c.o.category && c.d.category) {
                    drawChip(ctx, c.d.category, x0, pad, c.accent, cs);

                    markBox = Math.round(cs * 2.05);
                }

                if (c.o.logo && c.logo) {
                    drawLogoPill(ctx, c.logo, x1, pad, markH);

                    markBox = Math.max(markBox, Math.round(markH * 1.4));
                }

                top = pad + markBox + 30;
            } else {
                if (hasMark) {
                    ctx.save();
                    softShadow(ctx, disc * 0.45, disc * 0.1, 0.14);
                    ctx.fillStyle = '#ffffff';
                    ctx.beginPath();
                    ctx.arc(W / 2, photoH, disc, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.restore();

                    drawMark(ctx, c, W / 2, photoH, Math.round(disc * 0.56));
                }

                top = photoH + (hasMark ? disc : 0) + 18;

                if (c.o.category && c.d.category) {
                    ctx.font = '700 ' + cs + 'px ' + FONT;

                    const cw = ctx.measureText(c.d.category).width + cs * 1.8;

                    drawChip(ctx, c.d.category, W / 2 - cw / 2, top, c.accent, cs);

                    top += Math.round(cs * 2.05) + 22;
                }
            }

            // সারাংশ থাকলে শিরোনাম আর গাথনি মিলে পুরো প্যানেল ভরে রাখে — নাহলে নিচের অর্ধেক ফাঁকা থাকে।
            const exSize = Math.round(W * (land ? 0.024 : 0.033));
            const headH = Math.max(80, (areaBottom - top) * (hasEx ? (land ? 0.5 : 0.42) : 1));
            const fit = fitHeadline(ctx, c.d.title, x1 - x0, headH,
                Math.round(W * (land ? 0.042 : 0.062)), Math.round(W * 0.026), land ? 5 : 3);
            const startY = top + Math.max(0, (headH - fit.lines.length * fit.lh) * (hasEx ? 0.08 : (land ? 0.5 : 0.34)));

            ctx.font = '700 ' + fit.size + 'px ' + FONT;
            ctx.textBaseline = 'top';
            ctx.textAlign = land ? 'left' : 'center';

            fit.lines.forEach(function (line, i) {
                ctx.fillStyle = i === 0 ? c.accent : NAVY;
                ctx.fillText(line, land ? x0 : W / 2, startY + i * fit.lh);
            });

            ctx.textAlign = 'left';

            if (hasEx) {
                const gap = Math.round(fit.size * 0.75);
                const paraTop = startY + fit.lines.length * fit.lh + gap;
                const para = fitParagraph(ctx, c.d.excerpt, x1 - x0, areaBottom - paraTop, exSize, 400);

                drawParagraph(ctx, para, land ? x0 : W / 2, paraTop, '#1f2937', land ? 'left' : 'center');
            }

            drawFooter(ctx, c, footY, x0, x1, NAVY, GRAY, footSize, 'rgba(15,23,42,.14)');
        },

        // ছবির ওপর গাঢ় গ্রেডিয়েন্ট ও বড় সাদা শিরোনাম
        overlay: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const pad = c.pad;

            photoOrBrand(ctx, c, 0, 0, W, H, c.accent);

            let g = ctx.createLinearGradient(0, 0, 0, H * 0.3);
            g.addColorStop(0, 'rgba(3,8,18,.62)');
            g.addColorStop(1, 'rgba(3,8,18,0)');
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, W, H * 0.3);

            g = ctx.createLinearGradient(0, H * 0.24, 0, H);
            g.addColorStop(0, 'rgba(3,8,18,0)');
            g.addColorStop(0.5, 'rgba(3,8,18,.74)');
            g.addColorStop(1, 'rgba(3,8,18,.97)');
            ctx.fillStyle = g;
            ctx.fillRect(0, H * 0.24, W, H);

            const glow = ctx.createRadialGradient(pad, H * 0.96, 0, pad, H * 0.96, W * 0.6);
            glow.addColorStop(0, rgba(c.accent, 0.34));
            glow.addColorStop(1, rgba(c.accent, 0));
            ctx.fillStyle = glow;
            ctx.fillRect(0, H * 0.45, W, H * 0.55);

            topBar(ctx, c, { glass: true });

            const fh = footerHeight(c);
            const fit = fitHeadline(ctx, c.d.title, W - pad * 2 - 30,
                H * (c.o.size === 'landscape' ? 0.36 : 0.32),
                Math.round(W * 0.06), Math.round(W * 0.034), 5);
            const bottom = H - pad - fh - 24;
            const top = bottom - fit.lines.length * fit.lh;

            ctx.save();
            softShadow(ctx, W * 0.008, W * 0.003, 0.35);
            ctx.fillStyle = accentGradient(ctx, 0, top, 0, top + fit.lines.length * fit.lh, c.accent);
            roundRect(ctx, pad, top + 4, Math.max(5, Math.round(W * 0.006)), fit.lines.length * fit.lh - 10, Math.round(W * 0.003));
            ctx.fill();
            ctx.restore();

            ctx.save();
            softShadow(ctx, W * 0.014, W * 0.005, 0.3);
            drawLines(ctx, fit, pad + 26, top, '#ffffff');
            ctx.restore();

            drawFooter(ctx, c, H - pad, pad, W - pad, '#ffffff', 'rgba(255,255,255,.78)', Math.round(W * 0.025), 'rgba(255,255,255,.26)');
        },

        // হালকা রঙের পটভূমি, ওপরে মেটা লাইন ও লোগো, মাঝখানে উদ্ধৃতি-শিরোনাম, নিচে গোল কোণার ছবি
        split: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const land = c.o.size === 'landscape';
            const INK = '#1f2937';
            const GRAY = '#5b6472';
            const pad = Math.round(W * (land ? 0.05 : 0.056));
            const metaSize = Math.round(W * 0.026);
            const photoW = land ? Math.round(W * 0.44) : W - pad * 2;
            const photoX = land ? W - pad - photoW : pad;
            const photoH = land ? H - pad * 2 : Math.round(H * 0.44);
            const photoY = land ? pad : H - pad - photoH;
            const hasEx = !!(c.o.excerpt && c.d.excerpt);
            const title = String(c.d.title || '').trim();
            const quoted = /^[‘“"]/.test(title) ? title : '‘' + title + '’';

            ctx.fillStyle = tint(c.accent, 0.9);
            ctx.fillRect(0, 0, W, H);

            const glow = ctx.createRadialGradient(W * 0.92, 0, 0, W * 0.92, 0, W * 0.75);
            glow.addColorStop(0, rgba(c.accent, 0.1));
            glow.addColorStop(1, rgba(c.accent, 0));
            ctx.fillStyle = glow;
            ctx.fillRect(0, 0, W, H);

            drawMetaLine(ctx, c, pad, pad, INK, metaSize);

            if (c.o.logo && c.logo) {
                drawLogoPlain(ctx, c.logo, W - pad, pad, Math.round(logoHeight(c) * 0.85));
            }

            const x0 = pad;
            const x1 = land ? photoX - Math.round(W * 0.05) : W - pad;
            const top = pad + Math.round(metaSize * 1.4) + Math.round(H * (land ? 0.06 : 0.075));
            const areaBottom = (land ? H - pad : photoY) - Math.round(H * 0.03);
            const headH = Math.max(80, (areaBottom - top) * (hasEx ? 0.6 : 0.92));
            const fit = fitHeadline(ctx, quoted, x1 - x0, headH,
                Math.round(W * (land ? 0.048 : 0.062)), Math.round(W * 0.028), land ? 5 : 4);
            const startY = top + Math.max(0, (headH - fit.lines.length * fit.lh) * 0.12);

            ctx.font = '700 ' + fit.size + 'px ' + FONT;
            ctx.textBaseline = 'top';
            ctx.textAlign = land ? 'left' : 'center';
            ctx.fillStyle = darken(c.accent, 0.12);

            fit.lines.forEach(function (line, i) {
                ctx.fillText(line, land ? x0 : W / 2, startY + i * fit.lh);
            });

            if (hasEx) {
                const paraTop = startY + fit.lines.length * fit.lh + Math.round(fit.size * 0.7);
                const para = fitParagraph(ctx, c.d.excerpt, x1 - x0, areaBottom - paraTop,
                    Math.round(W * (land ? 0.023 : 0.03)), 400);

                drawParagraph(ctx, para, land ? x0 : W / 2, paraTop, GRAY, land ? 'left' : 'center');
            }

            ctx.textAlign = 'left';

            ctx.save();
            softShadow(ctx, W * 0.02, W * 0.008, 0.22);
            roundRect(ctx, photoX, photoY, photoW, photoH, Math.round(W * 0.032));
            ctx.clip();
            photoOrBrand(ctx, c, photoX, photoY, photoW, photoH, c.accent);
            ctx.restore();
        },

        // লেখার কার্ড: অ্যাকসেন্ট রঙের গাঢ় পট ও বিন্দুর জালে শিরোনাম ও সারাংশ
        text: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const land = c.o.size === 'landscape';
            const pad = Math.round(W * (land ? 0.045 : 0.055));
            const footSize = Math.round(W * (land ? 0.02 : 0.025));
            const footY = H - pad;
            const ruleY = footY - footSize * 2.2;
            const hasEx = !!(c.o.excerpt && c.d.excerpt);

            const g = ctx.createLinearGradient(0, 0, W, H);

            g.addColorStop(0, darken(c.accent, 0.22));
            g.addColorStop(0.55, darken(c.accent, 0.62));
            g.addColorStop(1, darken(c.accent, 0.88));

            ctx.fillStyle = g;
            ctx.fillRect(0, 0, W, H);

            dotGrid(ctx, 0, 0, W, H, Math.round(W * 0.045), Math.max(1.5, W * 0.0035), 'rgba(255,255,255,.055)');

            ctx.fillStyle = 'rgba(255,255,255,.05)';

            [0.085, 0.05].forEach(function (r, i) {
                ctx.beginPath();
                ctx.arc(Math.round(W * (i ? 0.33 : 0.2)), Math.round(H * 0.02), Math.round(W * r), 0, Math.PI * 2);
                ctx.fill();
            });

            topBar(ctx, c, { glass: true });

            const barW = Math.round(W * 0.075);
            const barH = Math.max(4, Math.round(W * 0.009));
            const bodyTop = pad + Math.round(chipSize(c) * 2.05) + Math.round(W * 0.07);

            drawRule(ctx, pad, pad + barW, bodyTop + barH / 2, darken(c.accent, 0.55), barH);

            const headTop = bodyTop + barH + Math.round(W * 0.035);
            const colW = land ? Math.round(W * 0.5) : W - pad * 2;
            const exX = land ? pad + colW + Math.round(W * 0.06) : pad;
            // লম্বা কার্ডে লেখা ছোট রাখলে নিচের এক-তৃতীয়াংশ ফাঁকা থাকে
            const tall = c.o.size === 'portrait';
            const fit = fitHeadline(ctx, c.d.title, colW,
                land ? ruleY - headTop : (ruleY - headTop) * (hasEx ? (tall ? 0.42 : 0.5) : 0.8),
                Math.round(W * (land ? 0.05 : (tall ? 0.084 : 0.072))), Math.round(W * 0.03),
                land ? 4 : (hasEx ? (tall ? 5 : 4) : 6));

            ctx.save();
            softShadow(ctx, W * 0.012, W * 0.004, 0.3);
            ctx.font = '700 ' + fit.size + 'px ' + FONT;
            ctx.textBaseline = 'top';

            // দুই লাইনে প্রথম লাইন রঙের হালকা ছায়া, বাকি সাদা — paper-এর নিয়মটাই এখানেও
            fit.lines.forEach(function (line, i) {
                ctx.fillStyle = i === 0 && fit.lines.length > 1 ? tint(c.accent, 0.42) : '#ffffff';
                ctx.fillText(line, pad, headTop + i * fit.lh);
            });

            ctx.restore();

            if (hasEx) {
                const paraTop = land ? headTop : headTop + fit.lines.length * fit.lh + Math.round(W * 0.05);
                const para = fitParagraph(ctx, c.d.excerpt, W - pad - exX,
                    land ? ruleY - headTop : ruleY - paraTop - Math.round(W * 0.04),
                    Math.round(W * (land ? 0.021 : (tall ? 0.037 : 0.031))), 400);

                drawParagraph(ctx, para, exX, paraTop, 'rgba(255,255,255,.82)');
            }

            drawFooter(ctx, c, footY, pad, W - pad, '#ffffff', 'rgba(255,255,255,.72)', footSize, 'rgba(255,255,255,.2)');
        }
    };

    /**
     * data: { title, category, date, dateBn, image, logo, emblem, siteName, footer, excerpt }
     * options: { template, size, logo, category, date, accent, excerpt }
     */
    async function render(canvas, data, options) {
        await fontsReady();

        const dims = SIZES[options.size] || SIZES.landscape;
        const W = dims[0];
        const H = dims[1];

        canvas.width = W;
        canvas.height = H;

        const ctx = canvas.getContext('2d');
        const photo = await loadImage(data.image);
        const logo = options.logo ? await loadImage(data.logo) : null;
        const emblem = await loadImage(data.emblem);

        const c = {
            W: W,
            H: H,
            pad: Math.round(W * 0.05),
            photo: photo,
            logo: logo,
            emblem: emblem,
            accent: options.accent || '#d60000',
            d: data,
            o: options
        };

        ctx.clearRect(0, 0, W, H);
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        (TEMPLATES[options.template] || TEMPLATES.paper)(ctx, c);

        return canvas;
    }

    function toBlob(canvas, type, quality) {
        return new Promise(function (resolve) {
            canvas.toBlob(resolve, type || 'image/png', quality);
        });
    }

    global.PhotoCard = {
        render: render,
        toBlob: toBlob,
        SIZES: SIZES,
        FONT: FONT,
        templates: TEMPLATES,
        h: {
            roundRect: roundRect,
            drawCover: drawCover,
            textOn: textOn,
            darken: darken,
            rgba: rgba,
            tint: tint,
            accentGradient: accentGradient,
            softShadow: softShadow,
            clearShadow: clearShadow,
            brandBackground: brandBackground,
            wrapLines: wrapLines,
            fitHeadline: fitHeadline,
            fitParagraph: fitParagraph,
            drawParagraph: drawParagraph,
            drawRule: drawRule
        }
    };
})(window);
