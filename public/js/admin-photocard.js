(function (global) {
    'use strict';

    const SIZES = {
        landscape: [1200, 630],
        square: [1080, 1080],
        portrait: [1080, 1350]
    };

    const FONT = '"Noto Sans Bengali","Nirmala UI","Shonar Bangla","Vrinda",sans-serif';
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

    function brandBackground(ctx, w, h, accent) {
        const g = ctx.createLinearGradient(0, 0, w, h);

        g.addColorStop(0, accent);
        g.addColorStop(1, '#0b1220');
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
            const lh = size * 1.38;

            if (lines.length <= maxLines && lines.length * lh <= maxH) {
                return { size: size, lines: lines, lh: lh };
            }
        }

        ctx.font = '700 ' + minSize + 'px ' + FONT;

        let lines = wrapLines(ctx, text, maxW);
        const lh = minSize * 1.38;
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

    function chipSize(c) { return Math.round(c.W * 0.026); }
    function logoHeight(c) { return Math.round(c.W * 0.045); }
    function footerHeight(c) { return Math.round(c.W * 0.04); }

    function drawChip(ctx, text, x, y, color, size) {
        ctx.font = '700 ' + size + 'px ' + FONT;

        const padX = size * 0.8;
        const w = ctx.measureText(text).width + padX * 2;
        const h = size * 1.9;

        ctx.fillStyle = color;
        roundRect(ctx, x, y, w, h, h / 2);
        ctx.fill();

        ctx.fillStyle = textOn(color);
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'left';
        ctx.fillText(text, x + padX, y + h / 2 + 1);

        return { w: w, h: h };
    }

    function drawLogoPill(ctx, logo, rightX, y, maxH) {
        const w = logo.width * (maxH / logo.height);
        const padX = maxH * 0.28;
        const padY = maxH * 0.18;
        const bw = w + padX * 2;
        const bh = maxH + padY * 2;

        ctx.fillStyle = 'rgba(255,255,255,.94)';
        roundRect(ctx, rightX - bw, y, bw, bh, bh * 0.28);
        ctx.fill();
        ctx.drawImage(logo, rightX - bw + padX, y + padY, w, maxH);

        return { w: bw, h: bh };
    }

    // বামে ক্যাটাগরির ফিতা, ডানে লোগো
    function topBar(ctx, c, opts) {
        const o = opts || {};

        if (c.o.category && c.d.category) {
            drawChip(ctx, c.d.category, c.pad, c.pad, o.chipColor || c.accent, chipSize(c));
        }

        if (c.o.logo && c.logo) {
            drawLogoPill(ctx, c.logo, c.W - c.pad, c.pad, logoHeight(c));
        }
    }

    function restText(c) {
        return [c.o.date ? c.d.date : '', c.d.footer].filter(Boolean).join('   |   ');
    }

    // বামে সাইটের নাম, ডানে তারিখ ও ঠিকানা
    function drawFooter(ctx, c, y, left, right, nameColor, textColor) {
        const size = Math.round(c.W * 0.025);
        const rest = restText(c);

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
    function drawFooterCenter(ctx, c, y, nameColor, textColor) {
        const size = Math.round(c.W * 0.025);
        const rest = restText(c);
        const name = c.d.siteName || '';

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

    const TEMPLATES = {

        // ছবির ওপর গাঢ় গ্রেডিয়েন্ট ও সাদা শিরোনাম
        overlay: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const pad = c.pad;

            photoOrBrand(ctx, c, 0, 0, W, H, c.accent);

            const g = ctx.createLinearGradient(0, H * 0.25, 0, H);

            g.addColorStop(0, 'rgba(0,0,0,0)');
            g.addColorStop(0.55, 'rgba(0,0,0,.72)');
            g.addColorStop(1, 'rgba(0,0,0,.94)');
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, W, H);

            topBar(ctx, c);

            const fh = footerHeight(c);
            const fit = fitHeadline(ctx, c.d.title, W - pad * 2 - 28, H * (c.o.size === 'landscape' ? 0.42 : 0.36),
                Math.round(W * 0.058), Math.round(W * 0.034), 6);
            const bottom = H - pad - fh - 16;
            const top = bottom - fit.lines.length * fit.lh;

            ctx.fillStyle = c.accent;
            ctx.fillRect(pad, top + 6, 8, fit.lines.length * fit.lh - 12);

            drawLines(ctx, fit, pad + 28, top, '#ffffff');
            drawFooter(ctx, c, H - pad, pad, W - pad, '#ffffff', 'rgba(255,255,255,.85)');
        },

        // ওপরে ছবি, নিচে গাঢ় পটভূমিতে শিরোনাম
        split: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const pad = c.pad;
            const photoH = Math.round(H * (c.o.size === 'landscape' ? 0.5 : 0.58));

            ctx.fillStyle = '#0f1b2d';
            ctx.fillRect(0, 0, W, H);

            photoOrBrand(ctx, c, 0, 0, W, photoH, c.accent);

            ctx.fillStyle = c.accent;
            ctx.fillRect(0, photoH, W, 10);

            topBar(ctx, c);

            const fh = footerHeight(c);
            const areaTop = photoH + 10 + Math.round(pad * 0.6);
            const areaBottom = H - pad - fh - 14;
            const fit = fitHeadline(ctx, c.d.title, W - pad * 2, areaBottom - areaTop,
                Math.round(W * 0.052), Math.round(W * 0.03), 5);

            drawLines(ctx, fit, pad, areaTop, '#ffffff');
            drawFooter(ctx, c, H - pad, pad, W - pad, '#ffffff', 'rgba(255,255,255,.7)');
        },

        // পুরো ছবি, নিচে সাদা ফলকে গাঢ় শিরোনাম
        bar: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const pad = c.pad;

            photoOrBrand(ctx, c, 0, 0, W, H, c.accent);

            const g = ctx.createLinearGradient(0, 0, 0, H * 0.3);

            g.addColorStop(0, 'rgba(0,0,0,.55)');
            g.addColorStop(1, 'rgba(0,0,0,0)');
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, W, H * 0.3);

            topBar(ctx, c);

            const inner = Math.round(W * 0.03);
            const fh = footerHeight(c);
            const cardX = pad;
            const cardW = W - pad * 2;
            const fit = fitHeadline(ctx, c.d.title, cardW - inner * 2 - 18, H * 0.32,
                Math.round(W * 0.05), Math.round(W * 0.03), 5);
            const cardH = inner * 2 + fit.lines.length * fit.lh + fh + 14;
            const cardY = H - pad - cardH;

            ctx.save();
            ctx.shadowColor = 'rgba(0,0,0,.35)';
            ctx.shadowBlur = 30;
            ctx.shadowOffsetY = 10;
            ctx.fillStyle = '#ffffff';
            roundRect(ctx, cardX, cardY, cardW, cardH, 22);
            ctx.fill();
            ctx.restore();

            ctx.fillStyle = c.accent;
            ctx.fillRect(cardX + inner * 0.6, cardY + inner, 8, fit.lines.length * fit.lh);

            drawLines(ctx, fit, cardX + inner + 18, cardY + inner, '#111827');
            drawFooter(ctx, c, cardY + cardH - inner * 0.55, cardX + inner, cardX + cardW - inner, c.accent, '#4b5563');
        },

        // ছবি গাঢ় করে মাঝখানে বড় লেখা (বক্তব্য, উদ্ধৃতির জন্য)
        center: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const pad = c.pad;

            photoOrBrand(ctx, c, 0, 0, W, H, c.accent);

            ctx.fillStyle = 'rgba(8,14,28,.68)';
            ctx.fillRect(0, 0, W, H);

            topBar(ctx, c);

            const fh = footerHeight(c);
            const topLimit = pad + Math.round(W * 0.07) + 30;
            const bottomLimit = H - pad - fh - 24;
            const areaH = bottomLimit - topLimit;
            const inset = Math.round(W * 0.06);
            const fit = fitHeadline(ctx, c.d.title, W - (pad + inset) * 2, areaH - 40,
                Math.round(W * 0.06), Math.round(W * 0.034), 6);
            const total = fit.lines.length * fit.lh;
            const top = topLimit + Math.max(0, (areaH - total) / 2) + 14;

            ctx.fillStyle = c.accent;
            ctx.fillRect(W / 2 - 60, top - 28, 120, 8);

            drawLines(ctx, fit, W / 2, top, '#ffffff', 'center');
            drawFooterCenter(ctx, c, H - pad, '#ffffff', 'rgba(255,255,255,.8)');
        },

        // হালকা ক্রিম পটভূমিতে গোল কোণার ছবি, নিচে গাঢ় লেখা
        frame: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const m = Math.round(W * 0.035);
            const photoH = Math.round(H * (c.o.size === 'landscape' ? 0.46 : 0.56));
            const cs = chipSize(c);

            ctx.fillStyle = '#f7f3ea';
            ctx.fillRect(0, 0, W, H);

            ctx.save();
            roundRect(ctx, m, m, W - m * 2, photoH, 20);
            ctx.clip();
            photoOrBrand(ctx, c, m, m, W - m * 2, photoH, c.accent);
            ctx.restore();

            if (c.o.logo && c.logo) {
                drawLogoPill(ctx, c.logo, W - m - 18, m + 18, logoHeight(c));
            }

            let chipH = 0;

            if (c.o.category && c.d.category) {
                chipH = cs * 1.9;
                drawChip(ctx, c.d.category, m + 24, m + photoH - chipH / 2, c.accent, cs);
            }

            const fh = footerHeight(c);
            const top = m + photoH + chipH / 2 + 22;
            const bottom = H - m - fh - 26;
            const fit = fitHeadline(ctx, c.d.title, W - m * 2 - 24, bottom - top,
                Math.round(W * 0.05), Math.round(W * 0.03), 5);

            drawLines(ctx, fit, m + 12, top, '#111827');

            ctx.fillStyle = c.accent;
            ctx.fillRect(m + 12, H - m - fh - 14, 90, 6);

            drawFooter(ctx, c, H - m - 6, m + 12, W - m - 12, textOn(c.accent) === '#ffffff' ? c.accent : '#374151', '#4b5563');
        },

        // বামে তেরছা কোণের রঙিন প্যানেলে লেখা, ডানে ছবি
        side: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const pad = c.pad;
            const panelW = Math.round(W * (c.o.size === 'landscape' ? 0.54 : 0.6));
            const slant = Math.round(W * 0.04);
            const ink = textOn(c.accent);

            photoOrBrand(ctx, c, 0, 0, W, H, '#334155');

            ctx.fillStyle = c.accent;
            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.lineTo(panelW + slant, 0);
            ctx.lineTo(panelW - slant, H);
            ctx.lineTo(0, H);
            ctx.closePath();
            ctx.fill();

            topBar(ctx, c, { chipColor: 'rgba(0,0,0,.35)' });

            const top = pad + chipSize(c) * 1.9 + 28;
            const bottom = H - pad - Math.round(c.W * 0.024) * 3.4;
            const fit = fitHeadline(ctx, c.d.title, panelW - slant - pad - Math.round(W * 0.04), bottom - top,
                Math.round(W * 0.05), Math.round(W * 0.028), 9);

            drawLines(ctx, fit, pad, top, ink);
            drawFooterStack(ctx, c, pad, H - pad, ink, ink);
        },

        // ছবির ওপর লাল "ব্রেকিং" ফিতা, নিচে বড় লেখা
        breaking: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const pad = c.pad;
            const label = c.o.badge || 'ব্রেকিং নিউজ';
            const red = '#d60000';

            photoOrBrand(ctx, c, 0, 0, W, H, '#7f1d1d');

            const g = ctx.createLinearGradient(0, H * 0.2, 0, H);

            g.addColorStop(0, 'rgba(0,0,0,0)');
            g.addColorStop(0.5, 'rgba(0,0,0,.75)');
            g.addColorStop(1, 'rgba(0,0,0,.95)');
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, W, H);

            const size = Math.round(W * 0.034);
            const dot = size * 0.5;

            ctx.font = '700 ' + size + 'px ' + FONT;

            const bw = ctx.measureText(label).width + size * 1.9 + dot;
            const bh = size * 1.9;

            ctx.fillStyle = red;
            roundRect(ctx, pad, pad, bw, bh, 10);
            ctx.fill();

            ctx.fillStyle = '#ffffff';
            ctx.beginPath();
            ctx.arc(pad + size * 0.8 + dot / 2, pad + bh / 2, dot / 2, 0, Math.PI * 2);
            ctx.fill();

            ctx.textBaseline = 'middle';
            ctx.textAlign = 'left';
            ctx.fillText(label, pad + size * 0.8 + dot + size * 0.4, pad + bh / 2 + 1);

            if (c.o.category && c.d.category) {
                const cs = chipSize(c);

                drawChip(ctx, c.d.category, pad + bw + 16, pad + (bh - cs * 1.9) / 2, 'rgba(0,0,0,.55)', cs);
            }

            if (c.o.logo && c.logo) {
                drawLogoPill(ctx, c.logo, W - pad, pad, logoHeight(c));
            }

            const fh = footerHeight(c);
            const fit = fitHeadline(ctx, c.d.title, W - pad * 2 - 28, H * (c.o.size === 'landscape' ? 0.44 : 0.38),
                Math.round(W * 0.062), Math.round(W * 0.036), 6);
            const bottom = H - pad - fh - 16;
            const top = bottom - fit.lines.length * fit.lh;

            ctx.fillStyle = red;
            ctx.fillRect(pad, top + 6, 8, fit.lines.length * fit.lh - 12);

            drawLines(ctx, fit, pad + 28, top, '#ffffff');
            drawFooter(ctx, c, H - pad, pad, W - pad, '#ffffff', 'rgba(255,255,255,.85)');
        },

        // নিচে তেরছা রঙিন ব্যান্ডে লেখা
        slant: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const pad = c.pad;
            const ink = textOn(c.accent);
            const yLeft = Math.round(H * (c.o.size === 'landscape' ? 0.54 : 0.6));
            const yRight = yLeft - Math.round(H * 0.08);

            photoOrBrand(ctx, c, 0, 0, W, H, c.accent);

            const t = ctx.createLinearGradient(0, 0, 0, H * 0.25);

            t.addColorStop(0, 'rgba(0,0,0,.5)');
            t.addColorStop(1, 'rgba(0,0,0,0)');
            ctx.fillStyle = t;
            ctx.fillRect(0, 0, W, H * 0.25);

            topBar(ctx, c);

            ctx.fillStyle = c.accent;
            ctx.beginPath();
            ctx.moveTo(0, yLeft);
            ctx.lineTo(W, yRight);
            ctx.lineTo(W, H);
            ctx.lineTo(0, H);
            ctx.closePath();
            ctx.fill();

            ctx.strokeStyle = 'rgba(255,255,255,.85)';
            ctx.lineWidth = 6;
            ctx.beginPath();
            ctx.moveTo(0, yLeft - 8);
            ctx.lineTo(W, yRight - 8);
            ctx.stroke();

            const fh = footerHeight(c);
            const top = yLeft + Math.round(pad * 0.5);
            const bottom = H - pad - fh - 14;
            const fit = fitHeadline(ctx, c.d.title, W - pad * 2, bottom - top,
                Math.round(W * 0.052), Math.round(W * 0.03), 5);

            drawLines(ctx, fit, pad, top, ink);
            drawFooter(ctx, c, H - pad, pad, W - pad, ink, ink);
        },

        // ছবি ছাড়া: রঙিন পটভূমিতে বড় উদ্ধৃতি-চিহ্ন ও লেখা
        text: function (ctx, c) {
            const W = c.W;
            const H = c.H;
            const pad = c.pad;

            brandBackground(ctx, W, H, darken(c.accent, 0.25));

            ctx.save();
            ctx.globalAlpha = 0.14;
            ctx.fillStyle = '#ffffff';
            ctx.font = '700 ' + Math.round(W * 0.55) + 'px Georgia, "Times New Roman", serif';
            ctx.textBaseline = 'top';
            ctx.textAlign = 'left';
            ctx.fillText('\u201C', W * 0.03, -H * 0.12);
            ctx.restore();

            topBar(ctx, c);

            const fh = footerHeight(c);
            const top = pad + chipSize(c) * 1.9 + 40;
            const bottom = H - pad - fh - 30;
            const fit = fitHeadline(ctx, c.d.title, W - pad * 2, bottom - top,
                Math.round(W * 0.07), Math.round(W * 0.036), 8);
            const total = fit.lines.length * fit.lh;

            drawLines(ctx, fit, pad, top + Math.max(0, (bottom - top - total) / 2), '#ffffff');
            drawFooter(ctx, c, H - pad, pad, W - pad, '#ffffff', 'rgba(255,255,255,.8)');
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
            drawCover: drawCover,
            textOn: textOn,
            darken: darken,
            brandBackground: brandBackground,
            wrapLines: wrapLines,
            fitHeadline: fitHeadline
        }
    };
})(window);