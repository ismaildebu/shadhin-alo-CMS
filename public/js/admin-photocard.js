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

    // ছবির ওপরের কোণা সোজা, নিচের কোণা গোল — আধুনিক ম্যাগাজিন ধাঁচ।
    function roundBottomRect(ctx, x, y, w, h, r) {
        const rr = Math.min(r, w / 2, h / 2);

        ctx.beginPath();
        ctx.moveTo(x, y);
        ctx.lineTo(x + w, y);
        ctx.lineTo(x + w, y + h - rr);
        ctx.quadraticCurveTo(x + w, y + h, x + w - rr, y + h);
        ctx.lineTo(x + rr, y + h);
        ctx.quadraticCurveTo(x, y + h, x, y + h - rr);
        ctx.closePath();
    }

    function drawCover(ctx, img, x, y, w, h) {
        const scale = Math.max(w / img.width, h / img.height);
        const sw = w / scale;
        const sh = h / scale;

        ctx.drawImage(img, (img.width - sw) / 2, (img.height - sh) / 2, sw, sh, x, y, w, h);
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

    function restText(c) {
        return [c.o.date ? c.d.date : '', c.d.footer].filter(Boolean).join('   ·   ');
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

    // বামে সাইটের নাম, ডানে তারিখ ও ঠিকানা
    function drawFooter(ctx, c, y, left, right, nameColor, textColor, lineColor) {
        const size = Math.round(c.W * 0.025);
        const rest = restText(c);

        if (lineColor) {
            drawRule(ctx, left, right, y - size * 2.2, lineColor, 1.5);
        }

        ctx.textBaseline = 'bottom';
        ctx.textAlign = 'left';
        ctx.fillStyle = nameColor;
        ctx.font = '700 ' + size + 'px ' + FONT;
        ctx.fillText(c.d.siteName || '', left, y);

        if (rest) {
            ctx.textAlign = 'right';
            ctx.fillStyle = textColor;
            ctx.font = '400 ' + size + 'px ' + FONT;
            ctx.fillText(rest, right, y);
        }

        ctx.textAlign = 'left';
    }

    // মাঝখানে সাজানো ফুটার
    function drawFooterCenter(ctx, c, y, nameColor, textColor, lineColor) {
        const size = Math.round(c.W * 0.025);
        const rest = restText(c);
        const name = c.d.siteName || '';

        if (lineColor) {
            drawRule(ctx, c.W * 0.35, c.W * 0.65, y - size * 2.2, lineColor, 1.5);
        }

        ctx.textBaseline = 'bottom';
        ctx.textAlign = 'left';

        ctx.font = '700 ' + size + 'px ' + FONT;
        const nameW = ctx.measureText(name).width;

        ctx.font = '400 ' + size + 'px ' + FONT;
        const gap = rest ? size * 1.2 : 0;
        const restW = rest ? ctx.measureText(rest).width : 0;
        const x = (c.W - (nameW + gap + restW)) / 2;

        ctx.font = '700 ' + size + 'px ' + FONT;
        ctx.fillStyle = nameColor;
        ctx.fillText(name, x, y);

        if (rest) {
            ctx.font = '400 ' + size + 'px ' + FONT;
            ctx.fillStyle = textColor;
            ctx.fillText(rest, x + nameW + gap, y);
        }
    }

    // সরু প্যানেলের জন্য: নিচে নিচে দুই লাইন (ওপরে সাইটের নাম, নিচে তারিখ ও ঠিকানা)
    function drawFooterStack(ctx, c, x, y, nameColor, textColor) {
        const size = Math.round(c.W * 0.024);
        const rest = restText(c);
        let yy = y;

        ctx.textBaseline = 'bottom';
        ctx.textAlign = 'left';

        if (rest) {
            ctx.font = '400 ' + size + 'px ' + FONT;
            ctx.fillStyle = textColor;
            ctx.fillText(rest, x, yy);
            yy -= size * 1.5;
        }

        ctx.font = '700 ' + size + 'px ' + FONT;
        ctx.fillStyle = nameColor;
        ctx.fillText(c.d.siteName || '', x, yy);
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

    function photoOrBrand(ctx, c, x, y, w, h, accent) {
        if (c.photo) {
            drawCover(ctx, c.photo, x, y, w, h);
        } else {
            ctx.save();
            ctx.beginPath();
            ctx.rect(x, y, w, h);
            ctx.clip();
            brandBackground(ctx, x + w, y + h, accent);
            ctx.restore();
        }
    }

    // গোল কোণার ছবি, নরম ছায়া ও হালকা বর্ডারসহ
    function drawPhotoPanel(ctx, c, x, y, w, h, r) {
        ctx.save();
        softShadow(ctx, c.W * 0.032, c.W * 0.012, 0.38);
        ctx.fillStyle = INK;
        roundRect(ctx, x, y, w, h, r);
        ctx.fill();
        ctx.restore();

        ctx.save();
        roundRect(ctx, x, y, w, h, r);
        ctx.clip();
        photoOrBrand(ctx, c, x, y, w, h, c.accent);
        ctx.restore();

        ctx.save();
        ctx.strokeStyle = 'rgba(255,255,255,.16)';
        ctx.lineWidth = Math.max(2, c.W * 0.0022);
        roundRect(ctx, x, y, w, h, r);
        ctx.stroke();
        ctx.restore();
    }

    // হালকা ডট-গ্রিড: আধুনিক সম্পাদকীয় ব্যাকগ্রাউন্ড
    function dotGrid(ctx, x, y, w, h, step, color, alpha) {
        ctx.save();
        ctx.beginPath();
        ctx.rect(x, y, w, h);
        ctx.clip();
        ctx.fillStyle = color || 'rgba(255,255,255,.16)';
        ctx.globalAlpha = alpha === undefined ? 0.35 : alpha;

        for (let yy = y; yy < y + h; yy += step) {
            for (let xx = x; xx < x + w; xx += step) {
                ctx.beginPath();
                ctx.arc(xx, yy, Math.max(1, step * 0.055), 0, Math.PI * 2);
                ctx.fill();
            }
        }

        ctx.restore();
    }

    const TEMPLATES = {

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

            drawFooter(ctx, c, H - pad, pad, W - pad, '#ffffff', 'rgba(255,255,255,.78)', 'rgba(255,255,255,.26)');
        },

        // ওপরে গোল কোণার ছবি, নিচে গাঢ় প্যানেলে শিরোনাম
        split: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const pad = c.pad;
            const photoH = Math.round(H * (c.o.size === 'portrait' ? 0.52 : 0.5));

            const bg = ctx.createLinearGradient(0, 0, 0, H);
            bg.addColorStop(0, '#0d1726');
            bg.addColorStop(1, '#060c16');
            ctx.fillStyle = bg;
            ctx.fillRect(0, 0, W, H);

            ctx.save();
            roundBottomRect(ctx, 0, 0, W, photoH, Math.round(W * 0.045));
            ctx.clip();
            photoOrBrand(ctx, c, 0, 0, W, photoH, c.accent);
            ctx.restore();

            const shade = ctx.createLinearGradient(0, photoH * 0.55, 0, photoH);
            shade.addColorStop(0, 'rgba(3,8,18,0)');
            shade.addColorStop(1, 'rgba(3,8,18,.5)');
            ctx.fillStyle = shade;
            ctx.fillRect(0, photoH * 0.55, W, photoH * 0.45);

            topBar(ctx, c, { glass: true });

            ctx.save();
            ctx.fillStyle = accentGradient(ctx, pad, 0, pad + W * 0.09, 0, c.accent);
            roundRect(ctx, pad, photoH + Math.round(H * 0.03), Math.round(W * 0.09), Math.max(5, Math.round(H * 0.009)), Math.round(H * 0.0045));
            ctx.fill();
            ctx.restore();

            const fh = footerHeight(c);
            const top = photoH + Math.round(H * 0.065);
            const bottom = H - pad - fh - 26;
            const fit = fitHeadline(ctx, c.d.title, W - pad * 2, bottom - top,
                Math.round(W * 0.054), Math.round(W * 0.03), 4);

            drawLines(ctx, fit, pad, top, '#ffffff');
            drawFooter(ctx, c, H - pad, pad, W - pad, '#ffffff', 'rgba(255,255,255,.72)', 'rgba(255,255,255,.22)');
        },

        // পুরো ছবি, নিচে সাদা কার্ডে গাঢ় শিরোনাম
        bar: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const pad = c.pad;

            photoOrBrand(ctx, c, 0, 0, W, H, c.accent);

            let g = ctx.createLinearGradient(0, 0, 0, H * 0.32);
            g.addColorStop(0, 'rgba(3,8,18,.6)');
            g.addColorStop(1, 'rgba(3,8,18,0)');
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, W, H * 0.32);

            topBar(ctx, c, { glass: true });

            const inner = Math.round(W * 0.032);
            const fh = footerHeight(c);
            const cardX = pad;
            const cardW = W - pad * 2;
            const fit = fitHeadline(ctx, c.d.title, cardW - inner * 2 - 20, H * 0.3,
                Math.round(W * 0.052), Math.round(W * 0.03), 5);
            const cardH = inner * 2 + fit.lines.length * fit.lh + fh + 10;
            const cardY = H - pad - cardH;
            const radius = Math.round(W * 0.026);

            ctx.save();
            softShadow(ctx, W * 0.035, W * 0.012, 0.4);
            ctx.fillStyle = '#ffffff';
            roundRect(ctx, cardX, cardY, cardW, cardH, radius);
            ctx.fill();
            ctx.restore();

            ctx.save();
            roundRect(ctx, cardX, cardY, cardW, cardH, radius);
            ctx.clip();
            ctx.fillStyle = accentGradient(ctx, cardX, 0, cardX + cardW, 0, c.accent);
            ctx.fillRect(cardX, cardY, cardW, Math.max(6, Math.round(H * 0.011)));
            ctx.restore();

            drawLines(ctx, fit, cardX + inner, cardY + inner + Math.round(H * 0.012), '#111827');
            drawFooter(ctx, c, cardY + cardH - inner * 0.7, cardX + inner, cardX + cardW - inner,
                c.accent, '#64748b', 'rgba(15,23,42,.1)');
        },

        // ছবি গাঢ় করে মাঝখানে বড় লেখা (বক্তব্য, উদ্ধৃতির জন্য)
        center: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const pad = c.pad;

            photoOrBrand(ctx, c, 0, 0, W, H, c.accent);

            const g = ctx.createLinearGradient(0, 0, 0, H);
            g.addColorStop(0, 'rgba(4,9,20,.7)');
            g.addColorStop(0.5, 'rgba(4,9,20,.58)');
            g.addColorStop(1, 'rgba(4,9,20,.82)');
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, W, H);

            const glow = ctx.createRadialGradient(W / 2, H * 0.42, 0, W / 2, H * 0.42, W * 0.55);
            glow.addColorStop(0, rgba(c.accent, 0.28));
            glow.addColorStop(1, rgba(c.accent, 0));
            ctx.fillStyle = glow;
            ctx.fillRect(0, 0, W, H);

            topBar(ctx, c, { glass: true });

            const fh = footerHeight(c);
            const topLimit = pad + Math.round(W * 0.08) + 26;
            const bottomLimit = H - pad - fh - 28;
            const areaH = bottomLimit - topLimit;
            const fit = fitHeadline(ctx, c.d.title, W - pad * 3, areaH - 34,
                Math.round(W * 0.06), Math.round(W * 0.034), 6);
            const total = fit.lines.length * fit.lh;
            const top = topLimit + Math.max(0, (areaH - total) / 2) + 18;

            ctx.save();
            ctx.globalAlpha = 0.22;
            ctx.fillStyle = '#ffffff';
            ctx.font = '700 ' + Math.round(W * 0.22) + 'px Georgia, "Times New Roman", serif';
            ctx.textBaseline = 'top';
            ctx.textAlign = 'center';
            ctx.fillText('\u201C', W / 2, topLimit - W * 0.1);
            ctx.restore();

            ctx.save();
            ctx.fillStyle = accentGradient(ctx, W / 2 - 60, 0, W / 2 + 60, 0, c.accent);
            roundRect(ctx, W / 2 - Math.round(W * 0.05), top - 24, Math.round(W * 0.1), Math.max(5, Math.round(W * 0.006)), Math.round(W * 0.003));
            ctx.fill();
            ctx.restore();

            drawLines(ctx, fit, W / 2, top, '#ffffff', 'center');
            drawFooterCenter(ctx, c, H - pad, '#ffffff', 'rgba(255,255,255,.8)', 'rgba(255,255,255,.24)');
        },

        // হালকা ক্রিম পটভূমিতে গোল কোণার ছবি, নিচে গাঢ় লেখা
        frame: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const m = Math.round(W * 0.035);
            const photoH = Math.round(H * (c.o.size === 'portrait' ? 0.58 : c.o.size === 'square' ? 0.5 : 0.46));
            const cs = chipSize(c);

            const bg = ctx.createLinearGradient(0, 0, 0, H);
            bg.addColorStop(0, '#fbf8f2');
            bg.addColorStop(1, '#efe8db');
            ctx.fillStyle = bg;
            ctx.fillRect(0, 0, W, H);

            drawPhotoPanel(ctx, c, m, m, W - m * 2, photoH, Math.round(W * 0.026));

            if (c.o.logo && c.logo) {
                drawLogoPill(ctx, c.logo, W - m - 18, m + 18, logoHeight(c));
            }

            let chipH = 0;

            if (c.o.category && c.d.category) {
                chipH = Math.round(cs * 2.05);
                drawChip(ctx, c.d.category, m + 24, m + photoH - chipH / 2, c.accent, cs);
            }

            const fh = footerHeight(c);
            const top = m + photoH + chipH / 2 + 24;
            const bottom = H - m - fh - 30;
            const textW = W - m * 2 - 24;
            const gap = Math.round(H * 0.018);
            const hasEx = !!(c.o.excerpt && c.d.excerpt);
            const exSpace = hasEx ? Math.round((bottom - top) * 0.34) : 0;
            const fit = fitHeadline(ctx, c.d.title, textW, Math.max(60, bottom - top - exSpace - gap),
                Math.round(W * 0.05), Math.round(W * 0.03), 5);
            const ex = hasEx
                ? fitParagraph(ctx, c.d.excerpt, textW, exSpace, Math.round(W * 0.025), 400)
                : null;
            const exH = ex && ex.lines.length ? ex.lines.length * ex.lh : 0;
            const contentH = fit.lines.length * fit.lh + (exH ? gap + exH : 0);
            const start = top + Math.max(0, (bottom - top - contentH) / 2);

            drawLines(ctx, fit, m + 12, start, '#111827');

            if (exH) {
                drawParagraph(ctx, ex, m + 12, start + fit.lines.length * fit.lh + gap, '#4a5568', 'left', 400);
            }

            drawFooter(ctx, c, H - m - 8, m + 12, W - m - 12,
                textOn(c.accent) === '#111827' ? c.accent : '#111827', '#64748b', 'rgba(15,23,42,.12)');
        },

        // বামে তেরছা কোণের রঙিন প্যানেলে লেখা, ডানে ছবি
        side: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const pad = c.pad;
            const panelW = Math.round(W * (c.o.size === 'landscape' ? 0.55 : 0.61));
            const slant = Math.round(W * 0.04);
            const ink = textOn(c.accent);

            photoOrBrand(ctx, c, 0, 0, W, H, '#334155');

            ctx.save();
            softShadow(ctx, W * 0.04, 0, 0.45);
            ctx.fillStyle = accentGradient(ctx, 0, 0, panelW, H, c.accent);
            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.lineTo(panelW + slant, 0);
            ctx.lineTo(panelW - slant, H);
            ctx.lineTo(0, H);
            ctx.closePath();
            ctx.fill();
            ctx.restore();

            ctx.save();
            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.lineTo(panelW + slant, 0);
            ctx.lineTo(panelW - slant, H);
            ctx.lineTo(0, H);
            ctx.closePath();
            ctx.clip();
            dotGrid(ctx, 0, 0, panelW + slant, H, Math.round(W * 0.045), 'rgba(255,255,255,.5)', 0.16);
            ctx.restore();

            topBar(ctx, c, { glass: true });

            const top = pad + chipSize(c) * 2.05 + 30;
            const bottom = H - pad - Math.round(c.W * 0.024) * 3.4;
            const fit = fitHeadline(ctx, c.d.title, panelW - slant - pad - Math.round(W * 0.035), bottom - top,
                Math.round(W * 0.05), Math.round(W * 0.028), 9);

            drawLines(ctx, fit, pad, top, ink);
            drawFooterStack(ctx, c, pad, H - pad, ink, ink);
        },

        // ছবির ওপর রঙিন "ব্রেকিং" ফিতা, নিচে বড় লেখা
        breaking: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const pad = c.pad;
            const label = c.o.badge || 'ব্রেকিং নিউজ';
            const red = c.accent || '#d60000';

            photoOrBrand(ctx, c, 0, 0, W, H, '#7f1d1d');

            let g = ctx.createLinearGradient(0, H * 0.18, 0, H);
            g.addColorStop(0, 'rgba(3,8,18,0)');
            g.addColorStop(0.5, 'rgba(3,8,18,.76)');
            g.addColorStop(1, 'rgba(3,8,18,.97)');
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, W, H);

            ctx.save();
            ctx.fillStyle = accentGradient(ctx, 0, 0, W, 0, red);
            ctx.fillRect(0, 0, W, Math.max(6, Math.round(H * 0.012)));
            ctx.restore();

            const size = Math.round(W * 0.032);
            const dot = size * 0.55;

            ctx.font = '700 ' + size + 'px ' + FONT;

            const bw = ctx.measureText(label).width + size * 1.9 + dot;
            const bh = Math.round(size * 2.05);

            ctx.save();
            softShadow(ctx, size, size * 0.3, 0.35);
            ctx.fillStyle = accentGradient(ctx, pad, pad, pad + bw, pad + bh, red);
            roundRect(ctx, pad, pad, bw, bh, bh / 2);
            ctx.fill();
            ctx.restore();

            ctx.fillStyle = '#ffffff';
            ctx.beginPath();
            ctx.arc(pad + size * 0.8 + dot / 2, pad + bh / 2, dot / 2, 0, Math.PI * 2);
            ctx.fill();

            ctx.textBaseline = 'middle';
            ctx.textAlign = 'left';
            ctx.fillText(label, pad + size * 0.8 + dot + size * 0.4, pad + bh / 2 + 1);

            if (c.o.category && c.d.category) {
                const cs = chipSize(c);

                drawGlassPill(ctx, c.d.category, pad + bw + 14, pad + (bh - cs * 2.05) / 2, cs);
            }

            if (c.o.logo && c.logo) {
                drawLogoPill(ctx, c.logo, W - pad, pad, logoHeight(c));
            }

            const fh = footerHeight(c);
            const fit = fitHeadline(ctx, c.d.title, W - pad * 2 - 30,
                H * (c.o.size === 'landscape' ? 0.42 : 0.36),
                Math.round(W * 0.062), Math.round(W * 0.036), 6);
            const bottom = H - pad - fh - 24;
            const top = bottom - fit.lines.length * fit.lh;

            ctx.save();
            softShadow(ctx, W * 0.008, W * 0.003, 0.3);
            ctx.fillStyle = accentGradient(ctx, 0, top, 0, top + fit.lines.length * fit.lh, red);
            roundRect(ctx, pad, top + 4, Math.max(5, Math.round(W * 0.006)), fit.lines.length * fit.lh - 10, Math.round(W * 0.003));
            ctx.fill();
            ctx.restore();

            drawLines(ctx, fit, pad + 26, top, '#ffffff');
            drawFooter(ctx, c, H - pad, pad, W - pad, '#ffffff', 'rgba(255,255,255,.8)', 'rgba(255,255,255,.26)');
        },

        // নিচে তেরছা রঙিন ব্যান্ডে লেখা
        slant: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const pad = c.pad;
            const ink = textOn(c.accent);
            const yLeft = Math.round(H * (c.o.size === 'landscape' ? 0.55 : 0.61));
            const yRight = yLeft - Math.round(H * 0.075);

            photoOrBrand(ctx, c, 0, 0, W, H, c.accent);

            const t = ctx.createLinearGradient(0, 0, 0, H * 0.26);

            t.addColorStop(0, 'rgba(3,8,18,.58)');
            t.addColorStop(1, 'rgba(3,8,18,0)');
            ctx.fillStyle = t;
            ctx.fillRect(0, 0, W, H * 0.26);

            topBar(ctx, c, { glass: true });

            ctx.save();
            softShadow(ctx, W * 0.035, -W * 0.01, 0.35);
            ctx.fillStyle = accentGradient(ctx, 0, yLeft, 0, H, c.accent);
            ctx.beginPath();
            ctx.moveTo(0, yLeft);
            ctx.lineTo(W, yRight);
            ctx.lineTo(W, H);
            ctx.lineTo(0, H);
            ctx.closePath();
            ctx.fill();
            ctx.restore();

            ctx.strokeStyle = 'rgba(255,255,255,.55)';
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.moveTo(0, yLeft - 6);
            ctx.lineTo(W, yRight - 6);
            ctx.stroke();

            const fh = footerHeight(c);
            const top = yLeft + Math.round(pad * 0.55);
            const bottom = H - pad - fh - 18;
            const fit = fitHeadline(ctx, c.d.title, W - pad * 2, bottom - top,
                Math.round(W * 0.052), Math.round(W * 0.03), 5);

            drawLines(ctx, fit, pad, top, ink);
            drawFooter(ctx, c, H - pad, pad, W - pad, ink, ink, rgba(ink === '#ffffff' ? '#ffffff' : '#111827', 0.18));
        },

        // ছবি ছাড়া: গাঢ় গ্রেডিয়েন্টে বড় উদ্ধৃতি-চিহ্ন ও লেখা
        text: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const pad = c.pad;

            brandBackground(ctx, W, H, c.accent);

            const glow = ctx.createRadialGradient(W * 0.82, H * 0.16, 0, W * 0.82, H * 0.16, W * 0.7);
            glow.addColorStop(0, rgba(c.accent, 0.5));
            glow.addColorStop(1, rgba(c.accent, 0));
            ctx.fillStyle = glow;
            ctx.fillRect(0, 0, W, H);

            dotGrid(ctx, 0, 0, W, H, Math.round(W * 0.05), 'rgba(255,255,255,.4)', 0.2);

            ctx.save();
            ctx.globalAlpha = 0.12;
            ctx.fillStyle = '#ffffff';
            ctx.font = '700 ' + Math.round(W * 0.55) + 'px Georgia, "Times New Roman", serif';
            ctx.textBaseline = 'top';
            ctx.textAlign = 'left';
            ctx.fillText('\u201C', W * 0.03, -H * 0.12);
            ctx.restore();

            topBar(ctx, c, { glass: true });

            const fh = footerHeight(c);
            const top = pad + chipSize(c) * 2.05 + 40;
            const bottom = H - pad - fh - 30;
            const fit = fitHeadline(ctx, c.d.title, W - pad * 2, bottom - top,
                Math.round(W * 0.068), Math.round(W * 0.036), 8);
            const total = fit.lines.length * fit.lh;

            ctx.save();
            ctx.fillStyle = accentGradient(ctx, pad, 0, pad + W * 0.1, 0, c.accent);
            roundRect(ctx, pad, top - 24, Math.round(W * 0.1), Math.max(5, Math.round(W * 0.006)), Math.round(W * 0.003));
            ctx.fill();
            ctx.restore();

            drawLines(ctx, fit, pad, top + Math.max(0, (bottom - top - total) / 2), '#ffffff');
            drawFooter(ctx, c, H - pad, pad, W - pad, '#ffffff', 'rgba(255,255,255,.8)', 'rgba(255,255,255,.22)');
        }
    };

    /**
     * data: { title, category, date, image, logo, siteName, footer }
     * options: { template, size, logo, category, date, accent, badge }
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
        const avatar = await loadImage(data.avatar);

        const c = {
            W: W,
            H: H,
            pad: Math.round(W * 0.05),
            photo: photo,
            logo: logo,
            emblem: emblem,
            avatar: avatar,
            accent: options.accent || '#d60000',
            d: data,
            o: options
        };

        ctx.clearRect(0, 0, W, H);
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        (TEMPLATES[options.template] || TEMPLATES.overlay)(ctx, c);

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
            roundBottomRect: roundBottomRect,
            drawCover: drawCover,
            textOn: textOn,
            darken: darken,
            rgba: rgba,
            accentGradient: accentGradient,
            softShadow: softShadow,
            clearShadow: clearShadow,
            brandBackground: brandBackground,
            wrapLines: wrapLines,
            fitHeadline: fitHeadline,
            fitParagraph: fitParagraph,
            drawParagraph: drawParagraph,
            dotGrid: dotGrid,
            drawRule: drawRule,
            drawPhotoPanel: drawPhotoPanel
        }
    };
})(window);
