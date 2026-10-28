(function (global) {
    'use strict';

    const PC = global.PhotoCard;

    if (!PC || !PC.h) { return; }

    const K = PC.h;
    const FONT = PC.FONT;
    const T = PC.templates;

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
    function rgba(a, alpha) { return 'rgba(' + a[0] + ',' + a[1] + ',' + a[2] + ',' + alpha + ')'; }

    function drawContain(ctx, img, x, y, w, h) {
        const s = Math.min(w / img.width, h / img.height);
        const dw = img.width * s;
        const dh = img.height * s;

        ctx.drawImage(img, x + (w - dw) / 2, y + (h - dh) / 2, dw, dh);
    }

    function catDate(c) {
        return [c.o.category ? c.d.category : '', c.o.date ? c.d.date : ''].filter(Boolean).join(' | ');
    }

    // লোগো দেওয়া থাকলে লোগো, নইলে সাইটের নাম লেখা
    function drawBrand(ctx, c, x, y, h, align) {
        if (c.o.logo && c.logo) {
            let bh = h;
            let bw = c.logo.width * (bh / c.logo.height);
            const maxW = c.W * 0.4;

            if (bw > maxW) {
                bh = bh * (maxW / bw);
                bw = maxW;
            }

            ctx.drawImage(c.logo, align === 'right' ? x - bw : x, y + (h - bh) / 2, bw, bh);

            return bw;
        }

        const name = c.d.siteName || '';

        ctx.font = '700 ' + Math.round(h * 0.6) + 'px ' + FONT;
        ctx.fillStyle = c.accent;
        ctx.textBaseline = 'middle';
        ctx.textAlign = align === 'right' ? 'right' : 'left';
        ctx.fillText(name, x, y + h / 2 + 1);

        const w = ctx.measureText(name).width;

        ctx.textAlign = 'left';

        return w;
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

    // অনুচ্ছেদ: জায়গায় না ধরলে শেষ লাইনে "…" দিয়ে কাটে
    function paragraph(ctx, text, maxW, maxH, size, weight) {
        ctx.font = (weight || 400) + ' ' + size + 'px ' + FONT;

        const lh = size * 1.55;
        const cap = Math.floor(maxH / lh);

        if (cap < 1 || !text) { return { lines: [], lh: lh, size: size }; }

        let lines = K.wrapLines(ctx, text, maxW);

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

    // ছবি (না থাকলে রঙিন পটভূমি) নির্দিষ্ট আয়তক্ষেত্রে, চাইলে গোল কোণসহ
    function photoIn(ctx, c, x, y, w, h, radius) {
        ctx.save();

        if (radius) {
            K.roundRect(ctx, x, y, w, h, radius);
        } else {
            ctx.beginPath();
            ctx.rect(x, y, w, h);
        }

        ctx.clip();

        if (c.photo) {
            K.drawCover(ctx, c.photo, x, y, w, h);
        } else {
            K.brandBackground(ctx, x + w, y + h, c.accent);
        }

        ctx.restore();
    }

    // গোল প্রতীক: ফেভিকন, না থাকলে লোগো, তাও না থাকলে সাইটের নামের প্রথম অক্ষর
    function drawEmblem(ctx, c, cx, cy, r, ring) {
        ctx.save();
        ctx.shadowColor = 'rgba(0,0,0,.18)';
        ctx.shadowBlur = 14;
        ctx.shadowOffsetY = 4;
        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.arc(cx, cy, r, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();

        if (ring) {
            ctx.strokeStyle = ring;
            ctx.lineWidth = Math.max(3, r * 0.07);
            ctx.beginPath();
            ctx.arc(cx, cy, r - ctx.lineWidth, 0, Math.PI * 2);
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

    function drawWebIcon(ctx, x, y, r, color) {
        ctx.save();
        ctx.strokeStyle = color;
        ctx.lineWidth = Math.max(2, r * 0.14);
        ctx.beginPath();
        ctx.arc(x, y, r, 0, Math.PI * 2);
        ctx.stroke();
        ctx.beginPath();
        ctx.ellipse(x, y, r * 0.45, r, 0, 0, Math.PI * 2);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(x - r, y);
        ctx.lineTo(x + r, y);
        ctx.stroke();
        ctx.restore();
    }

    // নিচের রঙিন ফিতা: দুই পাশে বিন্দু, মাঝে লেখা
    function drawCtaBar(ctx, c, y, h, color, text) {
        ctx.fillStyle = color;
        ctx.fillRect(0, y, c.W, h);

        const size = Math.round(h * 0.46);

        ctx.font = '700 ' + size + 'px ' + FONT;
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'center';
        ctx.fillStyle = '#ffffff';
        ctx.fillText(text, c.W / 2, y + h / 2 + 1);

        const tw = ctx.measureText(text).width;
        const unit = h / 70;
        let offset = tw / 2 + size * 0.8;

        [9, 7, 5.5, 4, 3].forEach(function (radius) {
            const r = radius * unit;

            ctx.beginPath();
            ctx.arc(c.W / 2 - offset - r, y + h / 2, r, 0, Math.PI * 2);
            ctx.fill();
            ctx.beginPath();
            ctx.arc(c.W / 2 + offset + r, y + h / 2, r, 0, Math.PI * 2);
            ctx.fill();

            offset += r * 2 + unit * 6;
        });

        ctx.textAlign = 'left';
    }

    // সোশ্যাল হ্যান্ডেলের সারি: গোল আইকন ও নাম, জায়গা কম হলে লেখা ছোট হয়
    function drawSocial(ctx, c, cx, cy, maxW, textColor) {
        const items = (c.d.social || []).filter(function (i) { return i && i.text; });

        if (!items.length) { return; }

        let size = Math.round(c.W * 0.024);
        let widths = [];

        function measure() {
            ctx.font = '500 ' + size + 'px ' + FONT;
            widths = items.map(function (i) { return ctx.measureText(i.text).width; });

            return widths.reduce(function (a, w) { return a + w; }, 0)
                + items.length * size * 2
                + (items.length - 1) * size * 1.2;
        }

        let total = measure();

        while (total > maxW && size > 12) {
            size -= 1;
            total = measure();
        }

        let x = cx - total / 2;
        const r = size * 0.75;

        items.forEach(function (item, i) {
            const meta = SOCIAL[item.type] || { color: '#475569', glyph: '•' };

            ctx.fillStyle = meta.color;
            ctx.beginPath();
            ctx.arc(x + r, cy, r, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = '#ffffff';

            if (item.type === 'youtube') {
                ctx.beginPath();
                ctx.moveTo(x + r * 0.7, cy - r * 0.45);
                ctx.lineTo(x + r * 1.45, cy);
                ctx.lineTo(x + r * 0.7, cy + r * 0.45);
                ctx.closePath();
                ctx.fill();
            } else {
                ctx.font = '700 ' + Math.round(r * 1.1) + 'px ' + FONT;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(meta.glyph, x + r, cy + 1);
            }

            ctx.font = '500 ' + size + 'px ' + FONT;
            ctx.fillStyle = textColor;
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            ctx.fillText(item.text, x + r * 2 + size * 0.5, cy + 1);

            x += size * 2 + widths[i] + size * 1.2;
        });
    }

    // ------------------------------------------------------------------
    // ১) ওপরে শিরোনাম, নিচে গোল কোণার ছবি
    // ------------------------------------------------------------------
    T.top = function (ctx, c) {
        const W = c.W;
        const Hh = c.H;
        const pad = c.pad;

        ctx.fillStyle = '#eceeea';
        ctx.fillRect(0, 0, W, Hh);

        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.moveTo(0, 0);
        ctx.lineTo(W, 0);
        ctx.lineTo(W, Hh * 0.2);
        ctx.bezierCurveTo(W * 0.78, Hh * 0.3, W * 0.3, Hh * 0.2, 0, Hh * 0.27);
        ctx.closePath();
        ctx.fill();

        const rowY = Math.round(pad * 0.6);
        const rowH = Math.round(W * 0.07);
        const label = catDate(c);

        if (label) {
            ctx.font = '700 ' + Math.round(W * 0.03) + 'px ' + FONT;
            ctx.fillStyle = '#111827';
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'left';
            ctx.fillText(label, Math.round(pad * 0.7), rowY + rowH / 2 + 1);
        }

        drawBrand(ctx, c, W - Math.round(pad * 0.7), rowY, rowH, 'right');

        const top = rowY + rowH + Math.round(Hh * 0.035);
        const lay = layoutHeadline(ctx, c, [c.accent, c.o.color2 || '#123b68'], W - pad * 2,
            Hh * (c.o.size === 'square' ? 0.2 : 0.17), Math.round(W * 0.075), Math.round(W * 0.04), 4, false);

        drawColoredLines(ctx, lay, W / 2, top, 'center');

        let y = top + lay.lines.length * lay.lh;

        if (c.o.cta) {
            const size = Math.round(W * 0.034);

            y += Math.round(Hh * 0.012);
            ctx.font = '700 ' + size + 'px ' + FONT;
            ctx.fillStyle = '#1f2937';
            ctx.textBaseline = 'top';
            ctx.textAlign = 'center';
            ctx.fillText('« ' + c.o.cta + ' »', W / 2, y);
            ctx.textAlign = 'left';
            y += size * 1.5;
        }

        const m = Math.round(W * 0.025);
        const photoTop = y + Math.round(Hh * 0.02);
        const cap = (c.o.caption && c.d.caption)
            ? paragraph(ctx, c.d.caption, W - pad * 2, Hh * 0.09, Math.round(W * 0.022), 400)
            : null;
        const capH = cap && cap.lines.length ? cap.lines.length * cap.lh + 16 : 0;
        const photoH = Math.max(160, Hh - photoTop - capH - m);

        photoIn(ctx, c, m, photoTop, W - m * 2, photoH, Math.round(W * 0.03));

        if (capH) {
            drawParagraph(ctx, cap, W / 2, photoTop + photoH + 10, '#4b5563', 'center', 400);
        }
    };


    // ------------------------------------------------------------------
    // ১) ওপরে শিরোনাম, নিচে গোল কোণার ছবি
    // ------------------------------------------------------------------
    T.top = function (ctx, c) {
        const W = c.W;
        const Hh = c.H;
        const pad = c.pad;

        ctx.fillStyle = '#eceeea';
        ctx.fillRect(0, 0, W, Hh);

        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.moveTo(0, 0);
        ctx.lineTo(W, 0);
        ctx.lineTo(W, Hh * 0.2);
        ctx.bezierCurveTo(W * 0.78, Hh * 0.3, W * 0.3, Hh * 0.2, 0, Hh * 0.27);
        ctx.closePath();
        ctx.fill();

        const rowY = Math.round(pad * 0.6);
        const rowH = Math.round(W * 0.07);
        const label = catDate(c);

        if (label) {
            ctx.font = '700 ' + Math.round(W * 0.03) + 'px ' + FONT;
            ctx.fillStyle = '#111827';
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'left';
            ctx.fillText(label, Math.round(pad * 0.7), rowY + rowH / 2 + 1);
        }

        drawBrand(ctx, c, W - Math.round(pad * 0.7), rowY, rowH, 'right');

        const top = rowY + rowH + Math.round(Hh * 0.035);
        const lay = layoutHeadline(ctx, c, [c.accent, c.o.color2 || '#123b68'], W - pad * 2,
            Hh * (c.o.size === 'square' ? 0.2 : 0.17), Math.round(W * 0.075), Math.round(W * 0.04), 4, false);

        drawColoredLines(ctx, lay, W / 2, top, 'center');

        let y = top + lay.lines.length * lay.lh;

        if (c.o.cta) {
            const size = Math.round(W * 0.034);

            y += Math.round(Hh * 0.012);
            ctx.font = '700 ' + size + 'px ' + FONT;
            ctx.fillStyle = '#1f2937';
            ctx.textBaseline = 'top';
            ctx.textAlign = 'center';
            ctx.fillText('« ' + c.o.cta + ' »', W / 2, y);
            ctx.textAlign = 'left';
            y += size * 1.5;
        }

        const m = Math.round(W * 0.025);
        const photoTop = y + Math.round(Hh * 0.02);
        const cap = (c.o.caption && c.d.caption)
            ? paragraph(ctx, c.d.caption, W - pad * 2, Hh * 0.09, Math.round(W * 0.022), 400)
            : null;
        const capH = cap && cap.lines.length ? cap.lines.length * cap.lh + 16 : 0;
        const photoH = Math.max(160, Hh - photoTop - capH - m);

        photoIn(ctx, c, m, photoTop, W - m * 2, photoH, Math.round(W * 0.03));

        if (capH) {
            drawParagraph(ctx, cap, W / 2, photoTop + photoH + 10, '#4b5563', 'center', 400);
        }
    };


    // ------------------------------------------------------------------
    // ২) উদ্ধৃতি কার্ড: ফিতা, প্যানেলে বড় উদ্ধৃতি, ডানে ব্যক্তির ছবি
    // ------------------------------------------------------------------
    T.quote = function (ctx, c) {
        const W = c.W;
        const Hh = c.H;
        const pad = c.pad;
        const bgRgb = mixWhite(c.accent, 0.86);

        ctx.fillStyle = rgb(bgRgb);
        ctx.fillRect(0, 0, W, Hh);

        const bandY = Math.round(Hh * 0.04);
        const bandH = Math.round(Hh * 0.09);
        const series = String(c.o.badge || 'কথা|অমৃত')
            .split('|')
            .map(function (s) { return s.trim(); })
            .filter(Boolean);

        ctx.fillStyle = c.accent;
        ctx.fillRect(0, bandY, W, bandH);

        ctx.fillStyle = '#ffffff';
        ctx.font = '700 ' + Math.round(bandH * 1.7) + 'px Georgia, "Times New Roman", serif';
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'left';
        ctx.fillText('\u201C', Math.round(pad * 0.8), bandY + bandH * 0.68);

        const quoteW = ctx.measureText('\u201C').width;
        let lx = Math.round(pad * 0.8) + quoteW + 22;

        ctx.font = '700 ' + Math.round(bandH * 0.46) + 'px ' + FONT;

        if (series[0]) {
            ctx.fillStyle = '#ffffff';
            ctx.fillText(series[0], lx, bandY + bandH / 2 + 1);
            lx += ctx.measureText(series[0]).width + 20;
        }

        if (series[1]) {
            const tw = ctx.measureText(series[1]).width;

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(lx, bandY + bandH * 0.16, tw + 40, bandH * 0.68);
            ctx.fillStyle = '#d60000';
            ctx.fillText(series[1], lx + 20, bandY + bandH / 2 + 1);
        }

        const panelX = Math.round(W * 0.055);
        const panelY = bandY + bandH + Math.round(Hh * 0.04);
        const panelW = Math.round(W * 0.725);
        const panelH = Math.round(Hh * 0.66);
        const inner = Math.round(W * 0.05);

        ctx.fillStyle = 'rgba(255,255,255,.6)';
        K.roundRect(ctx, panelX, panelY, panelW, panelH, Math.round(W * 0.035));
        ctx.fill();

        ctx.fillStyle = c.accent;
        ctx.font = '700 ' + Math.round(W * 0.15) + 'px Georgia, "Times New Roman", serif';
        ctx.textBaseline = 'top';
        ctx.fillText('\u201C', panelX + inner, panelY + Math.round(W * 0.005));

        const textTop = panelY + Math.round(W * 0.13);
        const textW = Math.min(panelW - inner * 2, Math.round(W * 0.54));
        const lay = layoutHeadline(ctx, c, ['#111827', '#111827'], textW, panelH * 0.46,
            Math.round(W * 0.075), Math.round(W * 0.04), 6, false);

        drawColoredLines(ctx, lay, panelX + inner, textTop, 'left');

        let y = textTop + lay.lines.length * lay.lh + Math.round(Hh * 0.025);

        ctx.fillStyle = c.accent;
        ctx.fillRect(panelX + inner, y, Math.round(W * 0.16), 3);
        y += Math.round(Hh * 0.025);

        const who = c.o.kicker || c.d.byline || '';

        if (who) {
            const size = Math.round(W * 0.036);

            ctx.font = '700 ' + size + 'px ' + FONT;
            ctx.fillStyle = '#111827';
            ctx.textBaseline = 'top';
            ctx.fillText(who, panelX + inner, y);
            y += size * 1.5;
        }

        if (c.o.excerpt && c.d.excerpt) {
            const avail = panelY + panelH - inner * 0.6 - y;
            const p = paragraph(ctx, c.d.excerpt, textW, avail, Math.round(W * 0.03), 400);

            drawParagraph(ctx, p, panelX + inner, y, '#1f2937', 'left', 400);
        }

        // ডানে ছবি: বাঁ ও ওপরের দিকে মিলিয়ে যায় (আলাদা ক্যানভাসে মাস্ক করে)
        if (c.photo) {
            const px = Math.round(W * 0.5);
            const py = Math.round(Hh * 0.3);
            const pw = W - px;
            const ph = Hh - py;
            const off = document.createElement('canvas');

            off.width = pw;
            off.height = ph;

            const octx = off.getContext('2d');

            K.drawCover(octx, c.photo, 0, 0, pw, ph);
            octx.globalCompositeOperation = 'destination-in';

            const maskX = octx.createLinearGradient(0, 0, pw * 0.35, 0);

            maskX.addColorStop(0, 'rgba(0,0,0,0)');
            maskX.addColorStop(1, 'rgba(0,0,0,1)');
            octx.fillStyle = maskX;
            octx.fillRect(0, 0, pw, ph);

            const maskY = octx.createLinearGradient(0, 0, 0, ph * 0.18);

            maskY.addColorStop(0, 'rgba(0,0,0,0)');
            maskY.addColorStop(1, 'rgba(0,0,0,1)');
            octx.fillStyle = maskY;
            octx.fillRect(0, 0, pw, ph);

            ctx.drawImage(off, px, py);
        }

        const logoH = Math.round(W * 0.075);

        drawBrand(ctx, c, Math.round(pad * 0.8), Hh - Math.round(pad * 0.7) - logoH, logoH, 'left');
    };

    // ------------------------------------------------------------------
    // ৩) ক্রিম পটভূমিতে লেখা: ওপরে ছবি ও ক্যাপশন, সীমায় গোল প্রতীক
    // ------------------------------------------------------------------
    T.cream = function (ctx, c) {
        const W = c.W;
        const Hh = c.H;
        const pad = c.pad;
        const cream = '#fbf6e9';

        ctx.fillStyle = cream;
        ctx.fillRect(0, 0, W, Hh);

        const photoH = Math.round(Hh * (c.o.size === 'square' ? 0.5 : 0.54));

        photoIn(ctx, c, 0, 0, W, photoH, 0);

        if (c.o.caption && c.d.caption) {
            const p = paragraph(ctx, c.d.caption, W * 0.58, Hh * 0.08, Math.round(W * 0.021), 400);

            if (p.lines.length) {
                const gh = p.lines.length * p.lh + 50;
                const g = ctx.createLinearGradient(0, photoH - gh, 0, photoH);

                g.addColorStop(0, 'rgba(0,0,0,0)');
                g.addColorStop(1, 'rgba(0,0,0,.72)');
                ctx.fillStyle = g;
                ctx.fillRect(0, photoH - gh, W, gh);

                drawParagraph(ctx, p, Math.round(pad * 0.6), photoH - p.lines.length * p.lh - 14, '#ffffff', 'left', 400);
            }
        }

        const r = Math.round(W * 0.075);

        ctx.fillStyle = cream;
        ctx.beginPath();
        ctx.arc(W / 2, photoH, r + 12, 0, Math.PI * 2);
        ctx.fill();

        drawEmblem(ctx, c, W / 2, photoH, r, c.accent);

        const footH = Math.round(Hh * 0.075);
        const fy = Hh - footH;
        const top = photoH + r + Math.round(Hh * 0.02);
        const lay = layoutHeadline(ctx, c, [c.accent, c.o.color2 || '#123b68'], W - pad * 2,
            Hh * 0.17, Math.round(W * 0.066), Math.round(W * 0.036), 4, false);

        drawColoredLines(ctx, lay, W / 2, top, 'center');

        const y = top + lay.lines.length * lay.lh + Math.round(Hh * 0.02);

        if (c.o.excerpt && c.d.excerpt) {
            const p = paragraph(ctx, c.d.excerpt, W - Math.round(pad * 1.8), fy - y - Math.round(Hh * 0.025),
                Math.round(W * 0.031), 400);

            drawParagraph(ctx, p, W / 2, y, '#1f2937', 'center', 400);
        }

        ctx.fillStyle = 'rgba(0,0,0,.05)';
        ctx.fillRect(0, fy, W, footH);

        const fs = Math.round(W * 0.026);
        const label = catDate(c);

        ctx.textBaseline = 'middle';
        ctx.textAlign = 'left';

        if (label) {
            ctx.font = '700 ' + fs + 'px ' + FONT;
            ctx.fillStyle = '#1f2937';
            ctx.fillText(label, Math.round(pad * 0.8), fy + footH / 2 + 1);
        }

        if (c.d.footer) {
            const right = W - Math.round(pad * 0.8);

            ctx.font = '500 ' + fs + 'px ' + FONT;
            ctx.fillStyle = '#1f2937';
            ctx.textAlign = 'right';
            ctx.fillText(c.d.footer, right, fy + footH / 2 + 1);

            const tw = ctx.measureText(c.d.footer).width;

            ctx.textAlign = 'left';
            drawWebIcon(ctx, right - tw - fs * 0.9, fy + footH / 2, fs * 0.5, '#1f2937');
        }
    };

    // ------------------------------------------------------------------
    // ৪) কলাম কার্ড: সীমায় লেখকের গোল ছবি
    // ------------------------------------------------------------------
    T.column = function (ctx, c) {
        const W = c.W;
        const Hh = c.H;
        const pad = c.pad;

        ctx.fillStyle = '#ececec';
        ctx.fillRect(0, 0, W, Hh);

        const photoH = Math.round(Hh * 0.46);

        photoIn(ctx, c, 0, 0, W, photoH, 0);

        const r = Math.round(W * 0.115);
        const cy = photoH;
        const kicker = c.o.kicker || c.d.byline || '';

        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.arc(W / 2, cy, r + 9, 0, Math.PI * 2);
        ctx.fill();

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
            ctx.font = '700 ' + Math.round(r * 1.1) + 'px ' + FONT;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(String(kicker || c.d.siteName || '•').trim().charAt(0), W / 2, cy + 3);
            ctx.textAlign = 'left';
        }

        ctx.restore();

        let y = cy + r + Math.round(Hh * 0.02);

        if (kicker) {
            const size = Math.round(W * 0.036);

            ctx.font = '400 ' + size + 'px ' + FONT;
            ctx.fillStyle = '#374151';
            ctx.textBaseline = 'top';
            ctx.textAlign = 'center';
            ctx.fillText(kicker, W / 2, y);
            ctx.textAlign = 'left';
            y += size * 1.6;
        }

        const footH = Math.round(Hh * 0.095);
        const lay = layoutHeadline(ctx, c, [c.accent, c.o.color2 || '#123b68'], W - pad * 2,
            Hh * 0.17, Math.round(W * 0.062), Math.round(W * 0.034), 4, false);

        drawColoredLines(ctx, lay, W / 2, y, 'center');
        y += lay.lines.length * lay.lh + Math.round(Hh * 0.018);

        if (c.o.excerpt && c.d.excerpt) {
            const p = paragraph(ctx, c.d.excerpt, W - Math.round(pad * 1.6), Hh - footH - y - Math.round(Hh * 0.02),
                Math.round(W * 0.03), 400);

            drawParagraph(ctx, p, Math.round(pad * 0.8), y, '#222222', 'left', 400);
        }

        ctx.fillStyle = '#d6d6d6';
        ctx.fillRect(0, Hh - footH, W, footH);

        const label = catDate(c);

        if (label) {
            ctx.font = '700 ' + Math.round(W * 0.026) + 'px ' + FONT;
            ctx.fillStyle = '#1f2937';
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'left';
            ctx.fillText(label, Math.round(pad * 0.8), Hh - footH / 2 + 1);
        }

        const bh = Math.round(footH * 0.62);

        drawBrand(ctx, c, W - Math.round(pad * 0.8), Hh - footH + (footH - bh) / 2, bh, 'right');
    };

    // ------------------------------------------------------------------
    // ৫) ব্র্যান্ড ফ্রেম: ফ্রেমে ছবি, ব্র্যান্ড-ফিতা, সোশ্যাল হ্যান্ডেল, নিচে লাল ফিতা
    // ------------------------------------------------------------------
    T.brand = function (ctx, c) {
        const W = c.W;
        const Hh = c.H;
        const pad = c.pad;

        ctx.fillStyle = '#f4f5f7';
        ctx.fillRect(0, 0, W, Hh);

        const headH = Math.round(Hh * 0.085);
        const logoH = Math.round(headH * 0.72);
        const shift = Math.round(Hh * 0.008);

        drawBrand(ctx, c, Math.round(pad * 0.6), Math.round((headH - logoH) / 2) + shift, logoH, 'left');

        const label = catDate(c);

        if (label) {
            ctx.font = '700 ' + Math.round(W * 0.032) + 'px ' + FONT;
            ctx.fillStyle = '#1f2937';
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'right';
            ctx.fillText(label, W - Math.round(pad * 0.6), headH / 2 + shift);
            ctx.textAlign = 'left';
        }

        const fx = Math.round(pad * 0.55);
        const fy = headH + Math.round(Hh * 0.012);
        const fw = W - fx * 2;
        const fh = Math.round(Hh * 0.42);
        const stripH = Math.round(Hh * 0.075);

        ctx.save();
        ctx.fillStyle = '#ffffff';
        K.roundRect(ctx, fx, fy, fw, fh, 26);
        ctx.fill();
        ctx.lineWidth = 5;
        ctx.strokeStyle = '#cbd5e1';
        ctx.stroke();
        ctx.restore();

        photoIn(ctx, c, fx + 8, fy + 8, fw - 16, fh - 16 - stripH, 20);

        const sy = fy + fh - 8 - stripH;
        const stripText = c.d.tagline || c.d.siteName || '';

        ctx.fillStyle = '#e5e7eb';
        ctx.fillRect(fx + 8, sy, fw - 16, 2);

        ctx.font = '700 ' + Math.round(stripH * 0.38) + 'px ' + FONT;
        ctx.fillStyle = c.accent;
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'left';
        ctx.fillText(stripText, fx + 26, sy + stripH / 2 + 4);

        drawBrand(ctx, c, fx + fw - 26, sy + stripH * 0.14, stripH * 0.72, 'right');
        drawEmblem(ctx, c, W / 2, sy + 2, Math.round(stripH * 0.62), '#cbd5e1');

        const barH = c.o.cta ? Math.round(Hh * 0.075) : 0;
        const hasSocial = c.o.social && c.d.social && c.d.social.length;
        const socialH = hasSocial ? Math.round(Hh * 0.06) : 0;
        const top = fy + fh + Math.round(Hh * 0.03);
        const bottom = Hh - barH - socialH - Math.round(Hh * 0.02);
        const lay = layoutHeadline(ctx, c, ['#111827', c.accent], W - pad * 2, bottom - top,
            Math.round(W * 0.085), Math.round(W * 0.04), 4, true);

        drawColoredLines(ctx, lay, W / 2, top + Math.max(0, (bottom - top - lay.lines.length * lay.lh) / 2), 'center');

        if (socialH) {
            drawSocial(ctx, c, W / 2, Hh - barH - socialH / 2 - 4, W - pad, '#374151');
        }

        if (barH) {
            drawCtaBar(ctx, c, Hh - barH, barH, K.darken(c.accent, 0.35), c.o.cta);
        }
    };
})(window);