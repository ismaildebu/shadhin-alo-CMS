(function (global) {
    'use strict';

    const PC = global.PhotoCard;

    if (!PC || !PC.h) { return; }

    const K = PC.h;
    const FONT = PC.FONT;
    const T = PC.templates;
    const INK = '#0b1220';
    const SERIF = 'Georgia, "Times New Roman", serif';

    const SOCIAL = {
        facebook: { color: '#1877f2', glyph: 'f' },
        instagram: { color: '#d62976', glyph: 'i' },
        youtube: { color: '#ff0000', glyph: '' },
        x: { color: '#111827', glyph: 'X' }
    };

    function mixWhite(hex, amount) {
        const m = /^#([0-9a-f]{6})$/i.exec(hex || '');

        if (!m) { return [241, 245, 249]; }

        const n = parseInt(m[1], 16);
        const mix = function (v) { return Math.round(v + (255 - v) * amount); };

        return [mix((n >> 16) & 255), mix((n >> 8) & 255), mix(n & 255)];
    }

    function rgb(a) { return 'rgb(' + a[0] + ',' + a[1] + ',' + a[2] + ')'; }

    function drawContain(ctx, img, x, y, w, h) {
        const s = Math.min(w / img.width, h / img.height);
        const dw = img.width * s;
        const dh = img.height * s;

        ctx.drawImage(img, x + (w - dw) / 2, y + (h - dh) / 2, dw, dh);
    }

    // পিল: রং, গ্রেডিয়েন্ট বা কাঁচের মতো হালকা পটভূমি
    function pill(ctx, text, x, y, size, fill, color, stroke) {
        ctx.font = '700 ' + size + 'px ' + FONT;

        const padX = Math.round(size * 0.95);
        const w = Math.ceil(ctx.measureText(text).width + padX * 2);
        const h = Math.round(size * 2.1);

        ctx.save();
        K.softShadow(ctx, size * 0.7, size * 0.2, 0.18);
        ctx.fillStyle = typeof fill === 'function' ? fill(w, h) : fill;
        K.roundRect(ctx, x, y, w, h, h / 2);
        ctx.fill();
        ctx.restore();

        if (stroke) {
            ctx.strokeStyle = stroke;
            ctx.lineWidth = Math.max(1.2, size * 0.06);
            K.roundRect(ctx, x, y, w, h, h / 2);
            ctx.stroke();
        }

        ctx.fillStyle = color;
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'left';
        ctx.fillText(text, x + padX, y + h / 2 + 1);

        return { w: w, h: h };
    }

    // সাদা টাইলে লোগো, ডান দিক থেকে বসে
    function drawLogoBadge(ctx, c, rightX, y, logoH) {
        if (!c.o.logo || !c.logo) { return null; }

        let h = logoH;
        let w = c.logo.width * (h / c.logo.height);
        const maxW = c.W * 0.3;

        if (w > maxW) {
            h = h * (maxW / w);
            w = maxW;
        }

        const padX = logoH * 0.3;
        const padY = logoH * 0.19;
        const bw = w + padX * 2;
        const bh = h + padY * 2;

        ctx.save();
        K.softShadow(ctx, logoH * 0.65, logoH * 0.18, 0.2);
        ctx.fillStyle = '#ffffff';
        K.roundRect(ctx, rightX - bw, y, bw, bh, bh * 0.3);
        ctx.fill();
        ctx.restore();

        ctx.drawImage(c.logo, rightX - bw + padX, y + padY, w, h);

        return { w: bw, h: bh };
    }

    function brandText(ctx, c, x, cy, size, color, align) {
        ctx.font = '700 ' + size + 'px ' + FONT;
        ctx.fillStyle = color || c.accent;
        ctx.textAlign = align === 'right' ? 'right' : 'left';
        ctx.textBaseline = 'middle';
        ctx.fillText(c.d.siteName || '', x, cy + 1);
        ctx.textAlign = 'left';

        return ctx.measureText(c.d.siteName || '').width;
    }

    // শিরোনামে "|" থাকলে সেখান থেকে রং বদলায়; নইলে "দুই রংয়ে" টিক থাকলে অর্ধেক লাইন করে।
    function layoutHeadline(ctx, c, colors, maxW, maxH, maxSize, minSize, maxLines, forceTwoTone) {
        const raw = String(c.d.title || '');
        const manual = raw.indexOf('|') !== -1;
        const parts = manual
            ? raw.split('|').map(function (s) { return s.trim(); }).filter(Boolean)
            : [raw.trim()];
        const fit = K.fitHeadline(ctx, parts.join(' '), maxW, maxH, maxSize, minSize, maxLines);
        let lines;

        if (manual && parts.length > 1) {
            ctx.font = '700 ' + fit.size + 'px ' + FONT;

            lines = K.wrapLines(ctx, parts[0], maxW)
                .map(function (t) { return { text: t, color: colors[0] }; })
                .concat(K.wrapLines(ctx, parts.slice(1).join(' '), maxW)
                    .map(function (t) { return { text: t, color: colors[1] }; }));
        } else {
            const twoTone = (c.o.twoTone || forceTwoTone) && fit.lines.length > 1;
            const half = Math.ceil(fit.lines.length / 2);

            lines = fit.lines.map(function (t, i) {
                return { text: t, color: twoTone && i >= half ? colors[1] : colors[0] };
            });
        }

        const cap = Math.max(1, Math.min(maxLines, Math.floor(maxH / fit.lh)));

        if (lines.length > cap) {
            lines = lines.slice(0, cap);
            lines[cap - 1].text = lines[cap - 1].text.replace(/\s+$/, '') + '…';
        }

        return { size: fit.size, lh: fit.lh, lines: lines };
    }

    function drawColoredLines(ctx, lay, x, top, align) {
        ctx.font = '700 ' + lay.size + 'px ' + FONT;
        ctx.textBaseline = 'top';
        ctx.textAlign = align || 'left';

        lay.lines.forEach(function (line, i) {
            ctx.fillStyle = line.color;
            ctx.fillText(line.text, x, top + i * lay.lh);
        });

        ctx.textAlign = 'left';
    }

    // গোল প্রতীক: ফেভিকন, না থাকলে লোগো, তাও না থাকলে সাইটের নামের প্রথম অক্ষর
    function drawEmblem(ctx, c, cx, cy, r, ring) {
        ctx.save();
        K.softShadow(ctx, r * 0.35, r * 0.1, 0.26);
        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.arc(cx, cy, r, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();

        if (ring) {
            ctx.strokeStyle = ring;
            ctx.lineWidth = Math.max(3, r * 0.07);
            ctx.beginPath();
            ctx.arc(cx, cy, r - ctx.lineWidth * 0.8, 0, Math.PI * 2);
            ctx.stroke();
        }

        if (c.emblem) {
            drawContain(ctx, c.emblem, cx - r * 0.6, cy - r * 0.6, r * 1.2, r * 1.2);
        } else if (c.logo) {
            drawContain(ctx, c.logo, cx - r * 0.75, cy - r * 0.4, r * 1.5, r * 0.8);
        } else {
            ctx.fillStyle = c.accent;
            ctx.font = '700 ' + Math.round(r * 1.0) + 'px ' + FONT;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(String(c.d.siteName || '•').trim().charAt(0), cx, cy + 2);
            ctx.textAlign = 'left';
        }
    }

    // ডান পাশের ছবি বাঁ ও ওপরের দিকে হালকা হয়ে পটভূমিতে মেশে
    function maskedPhoto(ctx, c, x, y, w, h) {
        if (!c.photo || w < 2 || h < 2) { return; }

        const off = document.createElement('canvas');

        off.width = Math.max(2, Math.round(w));
        off.height = Math.max(2, Math.round(h));

        const octx = off.getContext('2d');

        K.drawCover(octx, c.photo, 0, 0, off.width, off.height);
        octx.globalCompositeOperation = 'destination-in';

        let g = octx.createLinearGradient(0, 0, off.width * 0.4, 0);

        g.addColorStop(0, 'rgba(0,0,0,0)');
        g.addColorStop(1, 'rgba(0,0,0,1)');
        octx.fillStyle = g;
        octx.fillRect(0, 0, off.width, off.height);

        g = octx.createLinearGradient(0, 0, 0, off.height * 0.16);
        g.addColorStop(0, 'rgba(0,0,0,0)');
        g.addColorStop(1, 'rgba(0,0,0,1)');
        octx.fillStyle = g;
        octx.fillRect(0, 0, off.width, off.height);

        ctx.drawImage(off, x, y, w, h);
    }

    function restText(c) {
        return [c.o.date ? c.d.date : '', c.d.footer].filter(Boolean).join('   ·   ');
    }

    // সরু জায়গাতেও ফুটার আঁটে: দরকার হলে লেখা ছোট হয়
    function footerLine(ctx, c, x1, x2, y, nameColor, restColor, ruleColor, size) {
        const rest = restText(c);
        const name = c.d.siteName || '';
        const maxW = x2 - x1;
        let s = size || Math.round(c.W * 0.023);

        function widths() {
            ctx.font = '700 ' + s + 'px ' + FONT;
            const nw = ctx.measureText(name).width;

            ctx.font = '400 ' + s + 'px ' + FONT;

            return { nw: nw, rw: rest ? ctx.measureText(rest).width : 0 };
        }

        let m = widths();
        const gap = s * 1.4;

        while (m.nw + m.rw + gap > maxW && s > 11) {
            s -= 1;
            m = widths();
        }

        if (ruleColor) {
            K.drawRule(ctx, x1, x2, y - s * 2.3, ruleColor, 1.5);
        }

        ctx.textBaseline = 'bottom';
        ctx.textAlign = 'left';
        ctx.fillStyle = nameColor;
        ctx.font = '700 ' + s + 'px ' + FONT;
        ctx.fillText(name, x1, y);

        if (rest) {
            ctx.textAlign = 'right';
            ctx.fillStyle = restColor;
            ctx.font = '400 ' + s + 'px ' + FONT;
            ctx.fillText(rest, x2, y);
        }

        ctx.textAlign = 'left';
    }

    // আধুনিক নিচের ফিতা: গ্রেডিয়েন্ট, একটানা লেখা ও গোল তীর
    function drawCtaBar(ctx, c, y, h) {
        const text = c.o.cta || '';

        ctx.fillStyle = K.accentGradient(ctx, 0, y, c.W, y + h, c.accent);
        ctx.fillRect(0, y, c.W, h);

        ctx.fillStyle = 'rgba(255,255,255,.28)';
        ctx.fillRect(0, y, c.W, Math.max(1.5, h * 0.03));

        let size = Math.round(h * 0.34);

        ctx.font = '600 ' + size + 'px ' + FONT;

        while (size > 12 && ctx.measureText(text).width + size * 3.2 > c.W * 0.92) {
            size -= 1;
            ctx.font = '600 ' + size + 'px ' + FONT;
        }

        const textW = ctx.measureText(text).width;
        const capR = Math.round(size * 0.66);
        const gap = Math.round(size * 0.6);
        const startX = Math.round((c.W - (textW + gap + capR * 2)) / 2);

        ctx.fillStyle = '#ffffff';
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'left';
        ctx.fillText(text, startX, y + h / 2 + 1);

        const acx = startX + textW + gap + capR;
        const acy = y + h / 2;

        ctx.beginPath();
        ctx.arc(acx, acy, capR, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(255,255,255,.18)';
        ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,.62)';
        ctx.lineWidth = Math.max(1.5, size * 0.06);
        ctx.stroke();

        ctx.beginPath();
        ctx.moveTo(acx - capR * 0.18, acy - capR * 0.32);
        ctx.lineTo(acx + capR * 0.22, acy);
        ctx.lineTo(acx - capR * 0.18, acy + capR * 0.32);
        ctx.stroke();
    }

    // সোশ্যাল হ্যান্ডেলের সারি: সাদা পিলে গোল আইকন ও নাম
    function drawSocial(ctx, c, cx, cy, maxW) {
        const items = (c.d.social || []).filter(function (i) { return i && i.text; });

        if (!items.length) { return; }

        let size = Math.round(c.W * 0.021);
        let widths = [];

        function measure() {
            ctx.font = '600 ' + size + 'px ' + FONT;
            widths = items.map(function (i) { return ctx.measureText(i.text).width; });

            return widths.reduce(function (a, w) { return a + w; }, 0)
                + items.length * (size * 1.8 + size * 1.2 + size * 0.5)
                + (items.length - 1) * size * 0.8;
        }

        let total = measure();

        while (total > maxW && size > 12) {
            size -= 1;
            total = measure();
        }

        const h = Math.round(size * 2.1);
        const padX = Math.round(size * 0.9);
        const iconR = Math.round(size * 0.6);
        const gap = Math.round(size * 0.8);
        const y = cy - h / 2;
        let x = cx - total / 2;

        items.forEach(function (item, i) {
            const w = widths[i] + padX * 2 + iconR * 2 + size * 0.5;

            ctx.save();
            K.softShadow(ctx, size * 0.8, size * 0.22, 0.14);
            ctx.fillStyle = '#ffffff';
            K.roundRect(ctx, x, y, w, h, h / 2);
            ctx.fill();
            ctx.restore();

            ctx.strokeStyle = 'rgba(11,18,32,.1)';
            ctx.lineWidth = 1.2;
            K.roundRect(ctx, x, y, w, h, h / 2);
            ctx.stroke();

            const meta = SOCIAL[item.type] || { color: '#475569', glyph: '•' };

            ctx.fillStyle = meta.color;
            ctx.beginPath();
            ctx.arc(x + padX + iconR, cy, iconR, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = '#ffffff';

            if (item.type === 'youtube') {
                ctx.beginPath();
                ctx.moveTo(x + padX + iconR * 0.65, cy - iconR * 0.45);
                ctx.lineTo(x + padX + iconR * 1.45, cy);
                ctx.lineTo(x + padX + iconR * 0.65, cy + iconR * 0.45);
                ctx.closePath();
                ctx.fill();
            } else {
                ctx.font = '700 ' + Math.round(iconR * 1.05) + 'px ' + FONT;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(meta.glyph, x + padX + iconR, cy + 1);
            }

            ctx.fillStyle = '#334155';
            ctx.font = '600 ' + size + 'px ' + FONT;
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            ctx.fillText(item.text, x + padX + iconR * 2 + size * 0.5, cy + 1);

            x += w + gap;
        });
    }

    // ------------------------------------------------------------------
    // ১) ওপরে শিরোনাম, নিচে গোল কোণার ছবি
    // ------------------------------------------------------------------
    T.top = function (ctx, c) {
        const W = c.W;
        const H = c.H;
        const pad = c.pad;

        const bg = ctx.createLinearGradient(0, 0, W * 0.35, H);

        bg.addColorStop(0, '#fbfaf6');
        bg.addColorStop(0.55, '#f3f0e9');
        bg.addColorStop(1, '#e9e5db');
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, W, H);

        const glow = ctx.createRadialGradient(W * 0.88, -H * 0.05, 0, W * 0.88, -H * 0.05, W * 0.9);

        glow.addColorStop(0, K.rgba(c.accent, 0.14));
        glow.addColorStop(1, K.rgba(c.accent, 0));
        ctx.fillStyle = glow;
        ctx.fillRect(0, 0, W, H);

        K.dotGrid(ctx, 0, H * 0.7, W, H * 0.3, Math.round(W * 0.045), INK, 0.05);

        const rowY = Math.round(pad * 0.55);
        const rowH = Math.round(W * 0.09);
        let x = Math.round(pad * 0.75);

        if (c.o.category && c.d.category) {
            const size = Math.round(W * 0.025);
            const p = pill(ctx, c.d.category, x, rowY + Math.round((rowH - size * 2.1) / 2), size,
                function (w, h) { return K.accentGradient(ctx, x, 0, x + w, 0, c.accent); }, '#ffffff');

            x += p.w + Math.round(W * 0.018);
        }

        if (c.o.date && c.d.date) {
            ctx.font = '500 ' + Math.round(W * 0.022) + 'px ' + FONT;
            ctx.fillStyle = '#5b6472';
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'left';
            ctx.fillText(c.d.date, x, rowY + rowH / 2 + 1);
        }

        const logoH = Math.round(W * 0.036);

        if (c.o.logo && c.logo) {
            drawLogoBadge(ctx, c, W - Math.round(pad * 0.75), rowY + Math.round((rowH - logoH * 1.38) / 2), logoH);
        } else {
            brandText(ctx, c, W - Math.round(pad * 0.75), rowY + rowH / 2, Math.round(W * 0.026), INK, 'right');
        }

        const top = rowY + rowH + Math.round(H * 0.026);
        const lay = layoutHeadline(ctx, c, [INK, c.accent], W - pad * 2.5,
            H * (c.o.size === 'square' ? 0.2 : 0.215), Math.round(W * 0.078), Math.round(W * 0.042), 4, false);

        drawColoredLines(ctx, lay, W / 2, top, 'center');

        let y = top + lay.lines.length * lay.lh + Math.round(H * 0.017);

        ctx.save();
        K.softShadow(ctx, W * 0.008, W * 0.002, 0.2);
        ctx.fillStyle = K.accentGradient(ctx, W / 2 - W * 0.06, 0, W / 2 + W * 0.06, 0, c.accent);
        K.roundRect(ctx, W / 2 - W * 0.06, y, W * 0.12, Math.max(5, W * 0.006), Math.max(2, W * 0.003));
        ctx.fill();
        ctx.restore();

        y += Math.max(5, W * 0.006) + Math.round(H * 0.016);

        if (c.o.cta) {
            let size = Math.round(W * 0.024);

            ctx.font = '600 ' + size + 'px ' + FONT;

            while (size > 13 && ctx.measureText(c.o.cta).width + size * 2.8 > W - pad * 2) {
                size -= 1;
                ctx.font = '600 ' + size + 'px ' + FONT;
            }

            const bw = ctx.measureText(c.o.cta).width + size * 2.8;
            const bh = Math.round(size * 2.1);
            const bx = W / 2 - bw / 2;

            ctx.save();
            K.softShadow(ctx, size * 0.7, size * 0.2, 0.14);
            ctx.fillStyle = '#ffffff';
            K.roundRect(ctx, bx, y, bw, bh, bh / 2);
            ctx.fill();
            ctx.restore();

            ctx.strokeStyle = K.rgba(c.accent, 0.3);
            ctx.lineWidth = 1.5;
            K.roundRect(ctx, bx, y, bw, bh, bh / 2);
            ctx.stroke();

            ctx.fillStyle = c.accent;
            ctx.font = '600 ' + size + 'px ' + FONT;
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'center';
            ctx.fillText(c.o.cta, W / 2, y + bh / 2 + 1);
            ctx.textAlign = 'left';

            y += bh + Math.round(H * 0.018);
        }

        const m = Math.round(W * 0.045);
        const photoTop = y + Math.round(H * 0.012);
        const cap = (c.o.caption && c.d.caption)
            ? K.fitParagraph(ctx, c.d.caption, W - m * 2.6, H * 0.085, Math.round(W * 0.02), 400)
            : null;
        const capH = cap && cap.lines.length ? cap.lines.length * cap.lh + 20 : 0;
        const photoH = Math.max(Math.round(H * 0.26), H - photoTop - capH - m);

        K.drawPhotoPanel(ctx, c, m, photoTop, W - m * 2, photoH, Math.round(W * 0.034));

        if (capH) {
            K.drawParagraph(ctx, cap, W / 2, photoTop + photoH + 12, '#5b6472', 'center', 400);
        }
    };

    // ------------------------------------------------------------------
    // ২) উদ্ধৃতি কার্ড: ফিতা, সাদা প্যানেলে বড় উদ্ধৃতি, ডানে ব্যক্তির ছবি
    // ------------------------------------------------------------------
    T.quote = function (ctx, c) {
        const W = c.W;
        const H = c.H;
        const pad = c.pad;
        const base = mixWhite(c.accent, 0.945);

        ctx.fillStyle = rgb(base);
        ctx.fillRect(0, 0, W, H);

        let g = ctx.createRadialGradient(W * 0.08, H * 0.95, 0, W * 0.08, H * 0.95, W * 0.95);

        g.addColorStop(0, K.rgba(c.accent, 0.16));
        g.addColorStop(1, K.rgba(c.accent, 0));
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);

        const barH = Math.round(H * 0.07);
        const panelX = Math.round(pad * 0.85);
        const panelY = barH + Math.round(H * 0.045);
        const panelW = Math.round(W * 0.565);
        const panelH = H - panelY - Math.round(H * 0.05);
        const inner = Math.round(panelW * 0.09);

        const photoX = Math.round(W * 0.53);
        const photoY = barH + Math.round(H * 0.11);

        if (c.photo) {
            maskedPhoto(ctx, c, photoX, photoY, W - photoX, H - photoY);
        } else {
            ctx.fillStyle = K.rgba(c.accent, 0.08);
            ctx.font = '700 ' + Math.round(W * 0.46) + 'px ' + SERIF;
            ctx.textAlign = 'right';
            ctx.textBaseline = 'bottom';
            ctx.fillText('\u201D', W - pad, H - pad);
            ctx.textAlign = 'left';
        }

        ctx.fillStyle = K.accentGradient(ctx, 0, 0, W, barH, c.accent);
        ctx.fillRect(0, 0, W, barH);

        ctx.fillStyle = 'rgba(255,255,255,.3)';
        ctx.fillRect(0, barH - Math.max(1, barH * 0.02), W, Math.max(1, barH * 0.02));

        const series = String(c.o.badge || 'কথা|অমৃত')
            .split('|')
            .map(function (s) { return s.trim(); })
            .filter(Boolean);
        const pillSize = Math.round(barH * 0.33);
        let lx = Math.round(pad * 0.8);

        if (series[0]) {
            const p = pill(ctx, series[0], lx, Math.round((barH - pillSize * 2.1) / 2), pillSize,
                'rgba(255,255,255,.18)', '#ffffff', 'rgba(255,255,255,.4)');

            lx += p.w + Math.round(barH * 0.16);
        }

        if (series[1]) {
            pill(ctx, series[1], lx, Math.round((barH - pillSize * 2.1) / 2), pillSize, '#ffffff', c.accent);
        }

        if (c.o.logo && c.logo) {
            const logoH = Math.round(barH * 0.5);

            drawLogoBadge(ctx, c, W - Math.round(pad * 0.8), Math.round((barH - logoH * 1.38) / 2), logoH);
        } else {
            brandText(ctx, c, W - Math.round(pad * 0.8), barH / 2, Math.round(barH * 0.32), '#ffffff', 'right');
        }

        ctx.save();
        K.softShadow(ctx, W * 0.045, W * 0.016, 0.2);
        ctx.fillStyle = '#ffffff';
        K.roundRect(ctx, panelX, panelY, panelW, panelH, Math.round(W * 0.035));
        ctx.fill();
        ctx.restore();

        const textW = panelW - inner * 2;
        const lay = layoutHeadline(ctx, c, [INK, INK], textW, panelH * 0.42,
            Math.round(W * 0.062), Math.round(W * 0.04), 7, false);
        const who = c.o.kicker || c.d.byline || '';
        const whoSize = Math.round(W * 0.031);
        const footerY = panelY + panelH - Math.round(W * 0.045);
        const footerSize = Math.round(W * 0.021);
        const ex = (c.o.excerpt && c.d.excerpt)
            ? K.fitParagraph(ctx, c.d.excerpt, textW, panelH * 0.17, Math.round(W * 0.024), 400)
            : null;
        const glyphSize = Math.round(W * 0.12);
        const glyphBlock = Math.round(glyphSize * 0.6);
        const ruleH = Math.max(4, Math.round(W * 0.005));
        const gapQuote = Math.round(W * 0.016);
        const gapRule = Math.round(H * 0.022);
        const gapWho = Math.round(H * 0.018);
        const gapEx = Math.round(H * 0.012);
        const exH = ex && ex.lines.length ? ex.lines.length * ex.lh : 0;
        const contentH = glyphBlock + gapQuote + lay.lines.length * lay.lh + gapRule + ruleH + gapWho
            + (who ? whoSize * 1.6 : 0) + (exH ? gapEx + exH : 0);
        const contentTop = panelY + inner * 0.8;
        const contentBottom = footerY - inner * 0.6;
        const start = contentTop + Math.max(0, (contentBottom - contentTop - contentH) / 2);

        ctx.fillStyle = K.rgba(c.accent, 0.05);
        ctx.font = '700 ' + Math.round(W * 0.2) + 'px ' + SERIF;
        ctx.textAlign = 'right';
        ctx.textBaseline = 'alphabetic';
        ctx.fillText('\u201D', panelX + panelW - inner, footerY - Math.round(W * 0.024));
        ctx.textAlign = 'left';

        ctx.fillStyle = K.rgba(c.accent, 0.17);
        ctx.font = '700 ' + glyphSize + 'px ' + SERIF;
        ctx.textBaseline = 'top';
        ctx.textAlign = 'left';
        ctx.fillText('\u201C', panelX + inner - Math.round(W * 0.006), start);

        const textTop = start + glyphBlock + gapQuote;

        drawColoredLines(ctx, lay, panelX + inner, textTop, 'left');

        let y = textTop + lay.lines.length * lay.lh + gapRule;

        ctx.fillStyle = K.accentGradient(ctx, panelX + inner, 0, panelX + inner + W * 0.12, 0, c.accent);
        K.roundRect(ctx, panelX + inner, y, Math.round(W * 0.12), ruleH, 3);
        ctx.fill();

        y += ruleH + gapWho;

        if (who) {
            ctx.font = '700 ' + whoSize + 'px ' + FONT;
            ctx.fillStyle = INK;
            ctx.textBaseline = 'top';
            ctx.fillText(who, panelX + inner, y);
            y += whoSize * 1.6;
        }

        if (exH) {
            K.drawParagraph(ctx, ex, panelX + inner, y + gapEx, '#48505e', 'left', 400);
        }

        footerLine(ctx, c, panelX + inner, panelX + panelW - inner, footerY,
            c.accent, '#6b7280', 'rgba(11,18,32,.1)', footerSize);
    };

    // ------------------------------------------------------------------
    // ৩) ক্রিম কাগজ: ওপরে বড় ছবি, সীমায় গোল প্রতীক, নিচে বাঁ-ঘেঁষা লেখা
    // ------------------------------------------------------------------
    T.cream = function (ctx, c) {
        const W = c.W;
        const H = c.H;
        const pad = c.pad;
        const m = Math.round(W * 0.045);

        const bg = ctx.createLinearGradient(0, 0, W * 0.35, H);

        bg.addColorStop(0, '#fbf8f1');
        bg.addColorStop(1, '#efe8d9');
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, W, H);

        const photoH = Math.round(H * (c.o.size === 'square' ? 0.46 : 0.48));
        const photoW = W - m * 2;

        K.drawPhotoPanel(ctx, c, m, m, photoW, photoH, Math.round(W * 0.035));

        if (c.o.category && c.d.category) {
            pill(ctx, c.d.category, m + 24, m + 24, Math.round(W * 0.023),
                'rgba(9,16,28,.38)', '#ffffff', 'rgba(255,255,255,.3)');
        }

        if (c.o.logo && c.logo) {
            drawLogoBadge(ctx, c, m + photoW - 24, m + 24, Math.round(W * 0.034));
        }

        const cap = (c.o.caption && c.d.caption)
            ? K.fitParagraph(ctx, c.d.caption, photoW * 0.5, H * 0.075, Math.round(W * 0.02), 400)
            : null;

        if (cap && cap.lines.length) {
            const gh = cap.lines.length * cap.lh + 64;
            const g = ctx.createLinearGradient(0, m + photoH - gh, 0, m + photoH);

            g.addColorStop(0, 'rgba(6,12,24,0)');
            g.addColorStop(1, 'rgba(6,12,24,.8)');

            ctx.save();
            K.roundRect(ctx, m, m, photoW, photoH, Math.round(W * 0.035));
            ctx.clip();
            ctx.fillStyle = g;
            ctx.fillRect(m, m + photoH - gh, photoW, gh);
            ctx.restore();

            ctx.save();
            ctx.shadowColor = 'rgba(0,0,0,.55)';
            ctx.shadowBlur = 10;
            ctx.shadowOffsetY = 2;
            K.drawParagraph(ctx, cap, m + photoW - 28,
                m + photoH - cap.lines.length * cap.lh - 24, '#ffffff', 'right', 400);
            ctx.restore();
        }

        const r = Math.round(W * 0.067);
        const cx = m + r + 8;
        const cy = m + photoH;

        ctx.save();
        K.softShadow(ctx, W * 0.03, W * 0.01, 0.24);
        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.arc(cx, cy, r + 12, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();

        drawEmblem(ctx, c, cx, cy, r, c.accent);

        const footerY = H - pad;
        const footerSize = Math.round(W * 0.022);
        const areaTop = cy + r + Math.round(H * 0.02);
        const areaBottom = footerY - footerSize * 2.4 - Math.round(H * 0.012);
        const lay = layoutHeadline(ctx, c, [INK, c.accent], W - m * 2, H * 0.16,
            Math.round(W * 0.072), Math.round(W * 0.038), 4, false);
        const layH = lay.lines.length * lay.lh;
        const ex = (c.o.excerpt && c.d.excerpt)
            ? K.fitParagraph(ctx, c.d.excerpt, W - m * 2, H * 0.1, Math.round(W * 0.025), 400)
            : null;
        const exH = ex && ex.lines.length ? ex.lines.length * ex.lh : 0;
        const contentH = layH + (exH ? Math.round(H * 0.02) + exH : 0);
        const top = areaTop + Math.max(0, (areaBottom - areaTop - contentH) / 2);

        drawColoredLines(ctx, lay, m, top, 'left');

        if (exH) {
            K.drawParagraph(ctx, ex, m, top + layH + Math.round(H * 0.02), '#4a5568', 'left', 400);
        }

        footerLine(ctx, c, m, W - m, footerY, INK, '#6b7280', 'rgba(11,18,32,.12)', footerSize);
    };

    // ------------------------------------------------------------------
    // ৪) কলাম কার্ড: গাঢ় পটভূমি, সীমায় লেখকের গোল ছবি
    // ------------------------------------------------------------------
    T.column = function (ctx, c) {
        const W = c.W;
        const H = c.H;
        const pad = c.pad;
        const land = c.o.size === 'landscape';

        const bg = ctx.createLinearGradient(0, 0, W * 0.4, H);

        bg.addColorStop(0, '#0a1524');
        bg.addColorStop(1, '#101f33');
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, W, H);

        const glow = ctx.createRadialGradient(W * 0.1, H * 0.95, 0, W * 0.1, H * 0.95, W * 0.9);

        glow.addColorStop(0, K.rgba(c.accent, 0.22));
        glow.addColorStop(1, K.rgba(c.accent, 0));
        ctx.fillStyle = glow;
        ctx.fillRect(0, 0, W, H);

        const photoH = Math.round(H * (land ? 0.36 : (c.o.size === 'square' ? 0.42 : 0.44)));
        const radius = Math.round(W * 0.06);

        ctx.save();
        K.roundBottomRect(ctx, 0, 0, W, photoH, radius);
        ctx.clip();

        if (c.photo) {
            K.drawCover(ctx, c.photo, 0, 0, W, photoH);
        } else {
            K.brandBackground(ctx, W, photoH, c.accent);
        }

        const fade = ctx.createLinearGradient(0, photoH * 0.6, 0, photoH);

        fade.addColorStop(0, 'rgba(10,21,36,0)');
        fade.addColorStop(1, 'rgba(10,21,36,.92)');
        ctx.fillStyle = fade;
        ctx.fillRect(0, photoH * 0.6, W, photoH * 0.4);
        ctx.restore();

        const r = Math.round(Math.min(W * 0.1, H * 0.14));
        const cy = photoH;
        const kicker = c.o.kicker || c.d.byline || '';

        ctx.save();
        K.softShadow(ctx, W * 0.03, W * 0.01, 0.4);
        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.arc(W / 2, cy, r + 10, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();

        ctx.strokeStyle = K.rgba(c.accent, 0.9);
        ctx.lineWidth = Math.max(3, W * 0.005);
        ctx.beginPath();
        ctx.arc(W / 2, cy, r + 4, 0, Math.PI * 2);
        ctx.stroke();

        ctx.save();
        ctx.beginPath();
        ctx.arc(W / 2, cy, r, 0, Math.PI * 2);
        ctx.clip();

        if (c.avatar) {
            K.drawCover(ctx, c.avatar, W / 2 - r, cy - r, r * 2, r * 2);
        } else {
            ctx.fillStyle = c.accent;
            ctx.fillRect(W / 2 - r, cy - r, r * 2, r * 2);
            ctx.fillStyle = K.textOn(c.accent);
            ctx.font = '700 ' + Math.round(r * 1.05) + 'px ' + FONT;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(String(kicker || c.d.siteName || '•').trim().charAt(0), W / 2, cy + 3);
            ctx.textAlign = 'left';
        }

        ctx.restore();

        const accentLight = rgb(mixWhite(c.accent, 0.45));
        let y = cy + r + Math.round(H * 0.022);

        if (kicker) {
            const size = Math.round(W * 0.026);

            ctx.font = '600 ' + size + 'px ' + FONT;
            ctx.fillStyle = accentLight;
            ctx.textBaseline = 'top';
            ctx.textAlign = 'center';
            ctx.fillText(kicker, W / 2, y);
            ctx.textAlign = 'left';

            const kw = ctx.measureText(kicker).width;
            const gap = size * 0.9;
            const lineLen = Math.round(W * 0.07);

            if (W / 2 - kw / 2 - gap - lineLen > pad * 0.8) {
                ctx.strokeStyle = 'rgba(255,255,255,.24)';
                ctx.lineWidth = 1.5;
                ctx.beginPath();
                ctx.moveTo(W / 2 - kw / 2 - gap - lineLen, y + size * 0.55);
                ctx.lineTo(W / 2 - kw / 2 - gap, y + size * 0.55);
                ctx.stroke();
                ctx.beginPath();
                ctx.moveTo(W / 2 + kw / 2 + gap, y + size * 0.55);
                ctx.lineTo(W / 2 + kw / 2 + gap + lineLen, y + size * 0.55);
                ctx.stroke();
            }

            y += size * 1.8;
        }

        const top = y + Math.round(H * 0.016);
        const lay = layoutHeadline(ctx, c, ['#ffffff', accentLight], W - pad * 2,
            H * (land ? 0.22 : 0.17), Math.round(W * 0.07), Math.round(W * 0.037), 4, false);

        drawColoredLines(ctx, lay, W / 2, top, 'center');

        y = top + lay.lines.length * lay.lh + Math.round(H * 0.02);

        const footerY = H - pad;
        const footerSize = Math.round(W * 0.023);

        if (c.o.excerpt && c.d.excerpt) {
            const avail = footerY - footerSize * 2.5 - Math.round(H * 0.015) - y;

            if (avail > footerSize * 1.7) {
                const p = K.fitParagraph(ctx, c.d.excerpt, W - pad * 2.4, avail, Math.round(W * 0.025), 400);

                K.drawParagraph(ctx, p, pad * 1.2, y, 'rgba(226,232,240,.72)', 'left', 400);
            }
        }

        footerLine(ctx, c, pad, W - pad, footerY, '#ffffff', 'rgba(255,255,255,.6)',
            'rgba(255,255,255,.16)', footerSize);
    };

    // ------------------------------------------------------------------
    // ৫) ব্র্যান্ড ফ্রেম: ফ্রেমে ছবি, সীমায় প্রতীক, সোশ্যাল হ্যান্ডেল, নিচে ফিতা
    // ------------------------------------------------------------------
    T.brand = function (ctx, c) {
        const W = c.W;
        const H = c.H;
        const pad = c.pad;
        const land = c.o.size === 'landscape';

        const bg = ctx.createLinearGradient(0, 0, W * 0.4, H);

        bg.addColorStop(0, '#f7f8fa');
        bg.addColorStop(1, '#e8ecf2');
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, W, H);

        const glow = ctx.createRadialGradient(W * 0.12, H * 0.05, 0, W * 0.12, H * 0.05, W * 0.8);

        glow.addColorStop(0, K.rgba(c.accent, 0.12));
        glow.addColorStop(1, K.rgba(c.accent, 0));
        ctx.fillStyle = glow;
        ctx.fillRect(0, 0, W, H);

        K.dotGrid(ctx, 0, H * 0.62, W, H * 0.38, Math.round(W * 0.05), INK, 0.045);

        const rowY = Math.round(pad * 0.55);
        const rowH = Math.round(W * (land ? 0.06 : 0.08));
        const cardX = Math.round(pad * 0.8);

        if (c.o.logo && c.logo) {
            let lh = Math.round(W * (land ? 0.034 : 0.04));
            let lw = c.logo.width * (lh / c.logo.height);
            const maxW = W * 0.28;

            if (lw > maxW) {
                lh = lh * (maxW / lw);
                lw = maxW;
            }

            ctx.drawImage(c.logo, cardX, rowY + (rowH - lh) / 2, lw, lh);
        } else {
            brandText(ctx, c, cardX, rowY + rowH / 2, Math.round(W * 0.026), INK, 'left');
        }

        if (c.o.date && c.d.date) {
            ctx.font = '500 ' + Math.round(W * 0.021) + 'px ' + FONT;
            ctx.fillStyle = '#64707f';
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'right';
            ctx.fillText(c.d.date, W - cardX, rowY + rowH / 2 + 1);
            ctx.textAlign = 'left';
        }

        const cardY = rowY + rowH + Math.round(H * 0.012);
        const cardW = W - cardX * 2;
        const cardH = Math.round(H * (land ? 0.34 : 0.42));

        ctx.save();
        K.softShadow(ctx, W * 0.05, W * 0.018, 0.26);
        ctx.fillStyle = '#ffffff';
        K.roundRect(ctx, cardX, cardY, cardW, cardH, Math.round(W * 0.03));
        ctx.fill();
        ctx.restore();

        const inset = Math.round(W * 0.012);
        const photoX = cardX + inset;
        const photoY = cardY + inset;
        const photoW = cardW - inset * 2;
        const photoH = cardH - inset * 2;

        ctx.save();
        K.roundRect(ctx, photoX, photoY, photoW, photoH, Math.round(W * 0.024));
        ctx.clip();

        if (c.photo) {
            K.drawCover(ctx, c.photo, photoX, photoY, photoW, photoH);
        } else {
            K.brandBackground(ctx, photoX + photoW, photoY + photoH, c.accent);
        }

        ctx.restore();

        if (c.o.category && c.d.category) {
            pill(ctx, c.d.category, photoX + 22, photoY + 22, Math.round(W * 0.023),
                'rgba(9,16,28,.38)', '#ffffff', 'rgba(255,255,255,.3)');
        }

        const er = Math.round(Math.min(H * 0.062, W * 0.07));
        const ecx = W / 2;
        const ecy = cardY + cardH;

        ctx.save();
        K.softShadow(ctx, W * 0.03, W * 0.01, 0.2);
        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.arc(ecx, ecy, er + 12, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();

        drawEmblem(ctx, c, ecx, ecy, er, c.accent);

        const barH = c.o.cta ? Math.round(H * (land ? 0.075 : 0.068)) : 0;
        const hasSocial = c.o.social && c.d.social && c.d.social.length;
        const socialH = hasSocial ? Math.round(H * 0.062) : 0;
        const footerH = barH ? 0 : Math.round(H * 0.07);
        const areaTop = ecy + er + Math.round(H * 0.022);
        const areaBottom = H - barH - socialH - footerH - Math.round(H * 0.012);

        const lay = layoutHeadline(ctx, c, [INK, c.accent], W - pad * 2.4, areaBottom - areaTop,
            Math.round(W * 0.078), Math.round(W * 0.038), 4, true);
        const total = lay.lines.length * lay.lh;
        const top = areaTop + Math.max(0, (areaBottom - areaTop - total) / 2);

        drawColoredLines(ctx, lay, W / 2, top, 'center');

        if (socialH) {
            drawSocial(ctx, c, W / 2, H - barH - footerH - socialH / 2 - Math.round(H * 0.006), W - pad * 1.6);
        }

        if (barH) {
            drawCtaBar(ctx, c, H - barH, barH);
        } else {
            footerLine(ctx, c, pad, W - pad, H - pad, INK, '#64707f',
                'rgba(11,18,32,.12)', Math.round(W * 0.022));
        }
    };
})(window);
