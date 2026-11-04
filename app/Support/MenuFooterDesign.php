<?php

namespace App\Support;

/**
 * Dashboard-controlled look of the site menu (navbar), its dropdowns and the footer.
 *
 * Values are stored through the existing Settings store (keys prefixed
 * "menu_" / "drop_" / "footer_"). Only whitelisted choices, validated hex colors and
 * bounded sizes reach the generated <style> block, so printing it is safe.
 *
 * Every default reproduces the look the site had before these options existed, so a
 * database without the new keys renders exactly as before.
 */
final class MenuFooterDesign
{
    public const MENU_SIZES = [
        'sm' => ['label' => 'ছোট (১৪)', 'px' => 14],
        'md' => ['label' => 'মাঝারি (১৫)', 'px' => 15],
        'lg' => ['label' => 'বড় (১৭)', 'px' => 17],
        'xl' => ['label' => 'খুব বড় (১৯)', 'px' => 19],
    ];

    public const MENU_ALIGNS = [
        'center' => 'কেন্দ্রে',
        'left' => 'বামে',
        'right' => 'ডানে',
        'between' => 'দুই পাশে ছড়িয়ে',
    ];

    public const MENU_GAPS = [
        'tight' => ['label' => 'লেপ্টে (২)', 'px' => 2],
        'normal' => ['label' => 'স্বাভাবিক (৮)', 'px' => 8],
        'wide' => ['label' => 'খোলা (১৬)', 'px' => 16],
    ];

    public const MENU_RADII = [
        'square' => ['label' => 'বর্গ (০)', 'px' => 0],
        'soft' => ['label' => 'হালকা (৯)', 'px' => 9],
        'round' => ['label' => 'গোল (১৪)', 'px' => 14],
        'pill' => ['label' => 'পিল (৯৯৯)', 'px' => 999],
    ];

    public const MENU_WEIGHTS = [
        '500' => 'সরল (৫০০)',
        '600' => 'স্বাভাবিক (৬০০)',
        '700' => 'মোটা (৭০০)',
    ];

    public const MENU_HOVERS = [
        'fill' => 'রঙিন বক্স',
        'underline' => 'নিচে রেখা',
        'soft' => 'হালকা বক্স ও ছায়া',
        'none' => 'কিছু বদলাবে না',
    ];

    public const MENU_HEIGHTS = [
        'slim' => ['label' => 'চাপড়া (২)', 'px' => 2],
        'normal' => ['label' => 'স্বাভাবিক (৬)', 'px' => 6],
        'tall' => ['label' => 'উঁচু (১২)', 'px' => 12],
    ];

    /** Top line of the menu bar and the line above the footer. */
    public const LINE_WIDTHS = [
        'none' => ['label' => 'নেই', 'px' => 0],
        'hair' => ['label' => 'অতি সরু (২)', 'px' => 2],
        'thin' => ['label' => 'সরু (৩)', 'px' => 3],
        'normal' => ['label' => 'স্বাভাবিক (৪)', 'px' => 4],
        'bold' => ['label' => 'মোটা (৬)', 'px' => 6],
    ];

    public const MENU_SHADOWS = [
        'none' => ['label' => 'নেই', 'css' => 'none'],
        'soft' => ['label' => 'হালকা', 'css' => '0 10px 26px rgba(8,20,35,.16)'],
        'strong' => ['label' => 'গাঢ়', 'css' => '0 14px 38px rgba(8,20,35,.28)'],
    ];

    public const DROP_RADII = [
        'square' => ['label' => 'বর্গ (০)', 'px' => 0],
        'soft' => ['label' => 'হালকা (১২)', 'px' => 12],
        'round' => ['label' => 'গোল (১৮)', 'px' => 18],
    ];

    public const DROP_WIDTHS = [
        'narrow' => ['label' => 'সরু (২০০)', 'px' => 200],
        'normal' => ['label' => 'স্বাভাবিক (২৩০)', 'px' => 230],
        'wide' => ['label' => 'চওড়া (২৮০)', 'px' => 280],
    ];

    public const FOOTER_SIZES = [
        'sm' => ['label' => 'ছোট (১৪)', 'px' => 14],
        'md' => ['label' => 'মাঝারি (১৫)', 'px' => 15],
        'lg' => ['label' => 'বড় (১৭)', 'px' => 17],
        'xl' => ['label' => 'খুব বড় (১৯)', 'px' => 19],
    ];

    public const FOOTER_PADS = [
        'slim' => ['label' => 'কম (২৪)', 'px' => 24],
        'normal' => ['label' => 'স্বাভাবিক (৪৮)', 'px' => 48],
        'tall' => ['label' => 'বেশি (৭২)', 'px' => 72],
    ];

    public const FOOTER_GAPS = [
        'tight' => ['label' => 'কম (৮)', 'px' => 8],
        'normal' => ['label' => 'স্বাভাবিক (১৪)', 'px' => 14],
        'wide' => ['label' => 'খোলা (২০)', 'px' => 20],
    ];

    public const SOCIAL_SHAPES = [
        'pill' => ['label' => 'ডিমাকৃতি', 'px' => 999],
        'soft' => ['label' => 'হালকা কোণা', 'px' => 10],
        'square' => ['label' => 'বর্গ', 'px' => 0],
    ];

    /** One-click colour starting points; only colour keys, sizes and shapes stay untouched. */
    public const PRESETS = [
        'current' => ['label' => 'নীল–লাল (বর্তমান)', 'values' => [
            'menu_bg' => '#123b68', 'menu_text' => '#ffffff', 'menu_hover_bg' => '#0b2745', 'menu_accent' => '#d60000',
            'drop_bg' => '#ffffff', 'drop_text' => '#1f2937', 'drop_hover_bg' => '#eef2f7',
            'footer_bg' => '#0b2745', 'footer_text' => '#cfd8e3', 'footer_heading' => '#ffffff',
            'footer_accent' => '#d60000', 'footer_hover' => '#ffffff', 'footer_bottom_bg' => '#081c33',
        ]],
        'night' => ['label' => 'গাঢ় রাত', 'values' => [
            'menu_bg' => '#0f172a', 'menu_text' => '#e2e8f0', 'menu_hover_bg' => '#1e293b', 'menu_accent' => '#22d3ee',
            'drop_bg' => '#111827', 'drop_text' => '#dbeafe', 'drop_hover_bg' => '#1f2937',
            'footer_bg' => '#0b1120', 'footer_text' => '#94a3b8', 'footer_heading' => '#f1f5f9',
            'footer_accent' => '#22d3ee', 'footer_hover' => '#e2e8f0', 'footer_bottom_bg' => '#060a14',
        ]],
        'forest' => ['label' => 'সবুজ প্রান্তর', 'values' => [
            'menu_bg' => '#14532d', 'menu_text' => '#f0fdf4', 'menu_hover_bg' => '#0b3b20', 'menu_accent' => '#a3e635',
            'drop_bg' => '#ffffff', 'drop_text' => '#1f2937', 'drop_hover_bg' => '#ecfdf5',
            'footer_bg' => '#0b3b20', 'footer_text' => '#d1fae5', 'footer_heading' => '#ffffff',
            'footer_accent' => '#a3e635', 'footer_hover' => '#ffffff', 'footer_bottom_bg' => '#072918',
        ]],
        'royal' => ['label' => 'রয়্যাল বেগুনি', 'values' => [
            'menu_bg' => '#312e81', 'menu_text' => '#eef2ff', 'menu_hover_bg' => '#232063', 'menu_accent' => '#f472b6',
            'drop_bg' => '#ffffff', 'drop_text' => '#312e5c', 'drop_hover_bg' => '#f5f3ff',
            'footer_bg' => '#232063', 'footer_text' => '#c7d2fe', 'footer_heading' => '#ffffff',
            'footer_accent' => '#f472b6', 'footer_hover' => '#ffffff', 'footer_bottom_bg' => '#1a1750',
        ]],
        'paper' => ['label' => 'সাদা মিনিমাল', 'values' => [
            'menu_bg' => '#ffffff', 'menu_text' => '#111827', 'menu_hover_bg' => '#f1f5f9', 'menu_accent' => '#0f172a',
            'drop_bg' => '#ffffff', 'drop_text' => '#1f2937', 'drop_hover_bg' => '#f1f5f9',
            'footer_bg' => '#f8fafc', 'footer_text' => '#475569', 'footer_heading' => '#0f172a',
            'footer_accent' => '#0f172a', 'footer_hover' => '#0f172a', 'footer_bottom_bg' => '#e2e8f0',
        ]],
    ];

    /** Spread into Settings::DEFAULTS. */
    public const DEFAULTS = [
        'menu_bg' => '#123b68',
        'menu_text' => '#ffffff',
        'menu_hover_bg' => '#0b2745',
        'menu_accent' => '#d60000',
        'menu_size' => 'md',
        'menu_align' => 'center',
        'menu_sticky' => '1',
        'menu_gap' => 'tight',
        'menu_radius' => 'soft',
        'menu_weight' => '600',
        'menu_hover_style' => 'fill',
        'menu_height' => 'normal',
        'menu_top_line' => 'thin',
        'menu_shadow' => 'soft',

        'drop_bg' => '#ffffff',
        'drop_text' => '#1f2937',
        'drop_hover_bg' => '#eef2f7',
        'drop_radius' => 'soft',
        'drop_width' => 'normal',

        'footer_bg' => '#0b2745',
        'footer_text' => '#cfd8e3',
        'footer_heading' => '#ffffff',
        'footer_accent' => '#d60000',
        'footer_hover' => '#ffffff',
        'footer_bottom_bg' => '#081c33',
        'footer_size' => 'md',
        'footer_pad' => 'normal',
        'footer_gap' => 'tight',
        'footer_top_line' => 'normal',
        'social_shape' => 'pill',
    ];

    /** Select-type options: setting key => the constant holding its allowed choices. */
    public const CHOICES = [
        'menu_size' => self::MENU_SIZES,
        'menu_align' => self::MENU_ALIGNS,
        'menu_gap' => self::MENU_GAPS,
        'menu_radius' => self::MENU_RADII,
        'menu_weight' => self::MENU_WEIGHTS,
        'menu_hover_style' => self::MENU_HOVERS,
        'menu_height' => self::MENU_HEIGHTS,
        'menu_top_line' => self::LINE_WIDTHS,
        'menu_shadow' => self::MENU_SHADOWS,
        'drop_radius' => self::DROP_RADII,
        'drop_width' => self::DROP_WIDTHS,
        'footer_size' => self::FOOTER_SIZES,
        'footer_pad' => self::FOOTER_PADS,
        'footer_gap' => self::FOOTER_GAPS,
        'footer_top_line' => self::LINE_WIDTHS,
        'social_shape' => self::SOCIAL_SHAPES,
    ];

    public const COLORS = [
        'menu_bg', 'menu_text', 'menu_hover_bg', 'menu_accent',
        'drop_bg', 'drop_text', 'drop_hover_bg',
        'footer_bg', 'footer_text', 'footer_heading', 'footer_accent', 'footer_hover', 'footer_bottom_bg',
    ];

    /** Every field the design page posts, with the Bengali name used in labels and errors. */
    public const LABELS = [
        'menu_bg' => 'মেনুর ব্যাকগ্রাউন্ড রং',
        'menu_text' => 'মেনুর লেখার রং',
        'menu_hover_bg' => 'হোভার করলে ব্যাকগ্রাউন্ড',
        'menu_accent' => 'মেনুর অ্যাকসেন্ট রং',
        'menu_size' => 'লেখার আকার',
        'menu_align' => 'আইটেমের অবস্থান',
        'menu_gap' => 'আইটেমের ফাঁক',
        'menu_radius' => 'আইটেমের কোণা',
        'menu_weight' => 'লেখার মোটা',
        'menu_hover_style' => 'হোভারের ধরন',
        'menu_height' => 'বারের উঁচু',
        'menu_top_line' => 'উপরের রেখার মোটা',
        'menu_shadow' => 'বারের ছায়া',
        'menu_sticky' => 'স্ক্রল করলে',

        'drop_bg' => 'ড্রপডাউনের ব্যাকগ্রাউন্ড',
        'drop_text' => 'ড্রপডাউনের লেখার রং',
        'drop_hover_bg' => 'ড্রপডাউনের হোভার রং',
        'drop_radius' => 'ড্রপডাউনের কোণা',
        'drop_width' => 'ড্রপডাউনের চওড়া',

        'footer_bg' => 'ফুটারের ব্যাকগ্রাউন্ড রং',
        'footer_text' => 'ফুটারের লেখার রং',
        'footer_heading' => 'শিরোনামের রং',
        'footer_accent' => 'ফুটারের অ্যাকসেন্ট রং',
        'footer_hover' => 'লিংকে হোভার করলে রং',
        'footer_bottom_bg' => 'কপিরাইট বারের রং',
        'footer_size' => 'লেখার আকার',
        'footer_pad' => 'ওঠানামার জায়গা',
        'footer_gap' => 'লিংকের ফাঁক',
        'footer_top_line' => 'উপরের রেখার মোটা',
        'social_shape' => 'সোশ্যাল বাটনের আকৃতি',
    ];

    private const ALIGN_CSS = [
        'center' => 'center',
        'left' => 'flex-start',
        'right' => 'flex-end',
        'between' => 'space-between',
    ];

    /**
     * Normalized, safe design values plus the CSS for the public site.
     *
     * @return array{opt: array<string, string>, css: string}
     */
    public static function all(): array
    {
        $s = Settings::all();

        $opt = [];

        foreach (self::COLORS as $key) {
            $opt[$key] = self::color($s[$key] ?? '', $key);
        }

        foreach (self::CHOICES as $key => $allowed) {
            $opt[$key] = self::pick($s[$key] ?? '', $allowed, self::DEFAULTS[$key]);
        }

        $opt['menu_sticky'] = ($s['menu_sticky'] ?? '1') === '1' ? '1' : '0';

        return ['opt' => $opt, 'css' => self::css($opt)];
    }

    /** @param array<string, string> $opt */
    private static function css(array $opt): string
    {
        $align = self::ALIGN_CSS[$opt['menu_align']] ?? 'center';
        $menuPx = self::MENU_SIZES[$opt['menu_size']]['px'];
        $footerPx = self::FOOTER_SIZES[$opt['footer_size']]['px'];
        $gap = self::MENU_GAPS[$opt['menu_gap']]['px'];
        $radius = self::MENU_RADII[$opt['menu_radius']]['px'];
        $barPad = self::MENU_HEIGHTS[$opt['menu_height']]['px'];
        $topLine = self::LINE_WIDTHS[$opt['menu_top_line']]['px'];
        $footerLine = self::LINE_WIDTHS[$opt['footer_top_line']]['px'];
        $dropPx = max(13, $menuPx - 1);
        $dropRadius = self::DROP_RADII[$opt['drop_radius']]['px'];
        $dropWidth = self::DROP_WIDTHS[$opt['drop_width']]['px'];
        $footerPad = self::FOOTER_PADS[$opt['footer_pad']]['px'];
        $footerGap = self::FOOTER_GAPS[$opt['footer_gap']]['px'];
        $socialRadius = self::SOCIAL_SHAPES[$opt['social_shape']]['px'];

        $accent = $opt['menu_accent'];
        $footerAccent = $opt['footer_accent'];

        // A light menu background needs a dark toggler; the default white icon would vanish.
        $dark = Colors::isDark($opt['menu_bg']);
        $toggle = $dark ? '#ffffff' : '#1b1f24';
        $toggleIcon = $dark ? 'brightness(0) invert(1)' : 'none';
        $toggleBg = $dark ? 'rgba(255,255,255,.10)' : 'rgba(0,0,0,.05)';

        // The bar is a soft gradient around the chosen colour.
        $top = self::shade($opt['menu_bg'], 8);
        $bottom = self::shade($opt['menu_bg'], -18);

        $css = '.navbar-custom{background:linear-gradient(180deg,'.$top.' 0%,'.$opt['menu_bg'].' 55%,'.$bottom.' 100%);'
            .'border-top:'.$topLine.'px solid '.$accent.';'
            .'box-shadow:'.self::MENU_SHADOWS[$opt['menu_shadow']]['css'].';';

        if ($opt['menu_sticky'] !== '1') {
            $css .= 'position:static;';
        }

        $css .= '}';
        $css .= '.navbar-custom .navbar-nav{justify-content:'.$align.' !important;gap:'.$gap.'px;padding:'.$barPad.'px 0;}';
        $css .= '.navbar-custom .nav-link{color:'.$opt['menu_text'].' !important;font-size:'.$menuPx.'px;font-weight:'.$opt['menu_weight'].';'
            .'letter-spacing:.15px;padding:9px 15px;margin:5px 1px;border-radius:'.$radius.'px;line-height:1.35;'
            .'transition:background .18s ease,color .18s ease,box-shadow .18s ease;}';

        $css .= self::hoverCss($opt, $accent);

        $css .= '.navbar-custom .nav-link.active{background:'.$opt['menu_hover_bg'].';box-shadow:inset 0 -2px 0 '.$accent.';}';
        $css .= '.navbar-custom .nav-link:focus-visible{outline:2px solid '.$accent.';outline-offset:-2px;}';
        $css .= '.navbar-custom .navbar-toggler{border:1px solid '.$toggle.';border-radius:10px;padding:6px 10px;margin:8px 0;background:'.$toggleBg.';}';
        $css .= '.navbar-custom .navbar-toggler:focus{box-shadow:0 0 0 3px '.self::rgba($accent, 0.22).';}';
        $css .= '.navbar-custom .navbar-toggler-icon{filter:'.$toggleIcon.';}';

        // খোঁজ বক্স বারের রঙের সঙ্গে খাপ খায়: গাঢ় বারে হালকা কাচ, হালকা বারে গাঢ় কাচ।
        $searchBg = $dark ? 'rgba(255,255,255,.13)' : 'rgba(0,0,0,.06)';
        $searchBorder = self::rgba($opt['menu_text'], .35);
        $accentText = Colors::isDark($accent) ? '#ffffff' : '#101010';
        $searchRadius = $radius > 0 ? min($radius, 999) : 8;

        $css .= '.navbar-custom .sa-search-bar .sa-search-input{padding:8px 13px;font-size:'.$dropPx.'px;'
            .'color:'.$opt['menu_text'].';background:'.$searchBg.';border:1px solid '.$searchBorder.';border-radius:'.$searchRadius.'px;}';
        $css .= '.navbar-custom .sa-search-bar .sa-search-input::placeholder{color:'.self::rgba($opt['menu_text'], .7).';}';
        $css .= '.navbar-custom .sa-search-bar .sa-search-input:focus{background:'.($dark ? 'rgba(255,255,255,.2)' : '#ffffff')
            .';border-color:'.$accent.';outline:2px solid '.self::rgba($accent, .55).';outline-offset:1px;}';
        // ছোট স্ক্রিনে ভাঁজ খুললে বক্সটি মেনুর উপর বসে, তাই খোলা অবস্থায় অস্বচ্ছ রং — পেছনের লেখা ভেদ দেখাবে না।
        $css .= '.navbar-custom .sa-search-bar:focus-within .sa-search-input,.navbar-custom .sa-search-bar.sa-search-filled .sa-search-input{background:'
            .($dark ? self::shade($opt['menu_bg'], 14) : '#ffffff').';border-color:'.$accent.';}';
        $css .= '.navbar-custom .sa-search-bar .sa-search-btn{padding:8px 15px;font-size:'.$dropPx.'px;font-weight:'.$opt['menu_weight'].';'
            .'color:'.$accentText.';background:'.$accent.';border-radius:'.$searchRadius.'px;}';
        $css .= '.navbar-custom .sa-search-bar .sa-search-open{padding:8px 11px;'
            .'color:'.$accentText.';background:'.$accent.';border-radius:'.$searchRadius.'px;}';
        $css .= '.navbar-custom .sa-search-bar .sa-search-btn:hover,.navbar-custom .sa-search-bar .sa-search-open:hover{background:'.self::shade($accent, $dark ? 16 : -14).';}';
        $css .= '.navbar-custom .sa-search-bar .sa-search-btn:focus-visible,.navbar-custom .sa-search-bar .sa-search-open:focus-visible{outline:2px solid '.$opt['menu_text'].';outline-offset:2px;}';

        // মেনুর দুই আইটেমের মাঝের রেখা, মেগা মেনুর শিরোনাম ও ড্রপডাউনের অ্যাকসেন্ট ছোপ — সবই নির্বাচিত রং থেকে।
        $navLine = self::rgba($opt['menu_text'], .22);
        $dropLine = self::rgba($opt['drop_text'], .14);

        $css .= '.navbar-custom .navbar-nav>.nav-item+.nav-item::after{background:'.$navLine.';}';
        $css .= '.sa-mega-title{color:'.$accent.';border-bottom-color:'.$dropLine.';}';
        $css .= '.navbar-custom .dropdown-item::before{content:"";position:absolute;left:3px;top:50%;transform:translateY(-50%);'
            .'width:3px;height:0;border-radius:2px;background:'.$accent.';transition:height .16s ease;}';
        $css .= '.navbar-custom .dropdown-item:hover::before,.navbar-custom .dropdown-item:focus::before,'
            .'.navbar-custom .dropdown-item.is-current::before{height:58%;}';

        $css .= '@media (min-width:992px){'
            .'.navbar-custom .dropdown-menu{background:'.$opt['drop_bg'].';border:0;border-radius:'.$dropRadius.'px;'
            .'padding:7px;margin-top:4px;min-width:'.$dropWidth.'px;'
            .'box-shadow:0 18px 44px '.self::rgba($opt['drop_text'], .22).';}'
            .'.navbar-custom .dropdown-item{border-radius:8px;padding:8px 14px;color:'.$opt['drop_text'].';font-size:'.$dropPx.'px;font-weight:500;white-space:normal;}'
            .'.navbar-custom .dropdown-item:hover,.navbar-custom .dropdown-item:focus{background:'.$opt['drop_hover_bg'].';color:'.$opt['drop_text'].';}'
            .'.navbar-custom .dropdown-item.is-current{background:'.$opt['drop_hover_bg'].';color:'.$accent.';font-weight:600;}'
            .'}';

        // মোবাইলে ড্রপডাউন বারের রঙের ভেতরেই বসে, তাই রং ব্যাকগ্রাউন্ডের গাঢ়তা থেকে আসে।
        $mobDropBg = $dark ? 'rgba(0,0,0,.16)' : self::shade($opt['menu_bg'], -6);
        $mobDropText = $dark ? $opt['menu_text'] : '#1f2937';

        $css .= '@media (max-width:991.98px){'
            .'.navbar-custom .navbar-collapse{padding:4px 0 12px;}'
            .'.navbar-custom .nav-link{margin:2px 0;padding:10px 14px;}'
            .'.navbar-custom .nav-link.active{box-shadow:inset 3px 0 0 '.$accent.';}'
            .'.navbar-custom .nav-item.dropdown > .dropdown-menu{position:static;float:none;background:'.$mobDropBg.';border:0;'
            .'border-radius:10px;box-shadow:none;padding:6px;margin:4px 0 8px 12px;}'
            .'.navbar-custom .dropdown-item{color:'.$mobDropText.';font-size:'.$dropPx.'px;padding:8px 12px;border-radius:8px;}'
            .'.navbar-custom .dropdown-item:hover,.navbar-custom .dropdown-item:focus{background:'.$opt['menu_hover_bg'].';color:'.$mobDropText.';}'
            .'.navbar-custom .dropdown-item.is-current{color:'.$accent.';font-weight:600;}'
            .'}';

        // The social button and the copyright bar need their own contrast, not the author's guess.
        $socialText = Colors::isDark($footerAccent) ? '#ffffff' : '#101010';
        $bottomDark = Colors::isDark($opt['footer_bottom_bg']);
        $bottomText = $bottomDark ? $opt['footer_text'] : '#111827';
        $bottomStrong = $bottomDark ? '#ffffff' : '#0f172a';

        $css .= '.sa-footer{background:'.$opt['footer_bg'].';color:'.$opt['footer_text'].';'
            .'border-top:'.$footerLine.'px solid '.$footerAccent.';font-size:'.$footerPx.'px;}';

        // Bootstrap's py-5 is !important, so the padding override has to be too.
        $css .= '.sa-footer .container{padding-top:'.$footerPad.'px !important;padding-bottom:'.$footerPad.'px !important;}';

        $css .= '.sa-footer a{color:'.$opt['footer_text'].';}';
        $css .= '.sa-footer a:hover{color:'.$opt['footer_hover'].';}';
        $css .= '.sa-footer-title{color:'.$opt['footer_heading'].';border-bottom-color:'.$footerAccent.';}';
        $css .= '.sa-footer-links{gap:'.$footerGap.'px 16px;}';
        $css .= '.sa-social{border-radius:'.$socialRadius.'px;}';
        $css .= '.sa-social:hover{background:'.$footerAccent.';border-color:'.$footerAccent.';color:'.$socialText.';}';
        $css .= '.sa-footer-bottom{background:'.$opt['footer_bottom_bg'].';color:'.$bottomText.';}';
        $css .= '.sa-footer-bottom .text-white{color:'.$bottomStrong.' !important;}';

        return $css;
    }

    /** How a menu item reacts to the pointer — the four looks share one selector set. */
    private static function hoverCss(array $opt, string $accent): string
    {
        $hover = $opt['menu_hover_bg'];

        return match ($opt['menu_hover_style']) {
            'underline' => '.navbar-custom .nav-link:hover,.navbar-custom .nav-link:focus-visible{box-shadow:inset 0 -2px 0 '.$accent.';color:'.$opt['menu_text'].' !important;}',
            'soft' => '.navbar-custom .nav-link:hover,.navbar-custom .nav-link:focus-visible{background:'.self::rgba($hover, .55)
                .';box-shadow:0 4px 14px rgba(8,20,35,.16);color:'.$opt['menu_text'].' !important;}',
            'none' => '',
            default => '.navbar-custom .nav-link:hover,.navbar-custom .nav-link:focus-visible{background:'.$hover.';color:'.$opt['menu_text'].' !important;}',
        };
    }

    /** @param array<string, array{label: string}|string> $allowed */
    private static function pick(mixed $value, array $allowed, string $fallback): string
    {
        return is_string($value) && array_key_exists($value, $allowed) ? $value : $fallback;
    }

    private static function color(mixed $value, string $key): string
    {
        return Colors::isHex($value) ? strtolower($value) : self::DEFAULTS[$key];
    }

    /** Move a hex colour towards white (positive) or black (negative) by a percentage. */
    private static function shade(string $hex, int $percent): string
    {
        $n = hexdec(ltrim($hex, '#'));
        $channels = [($n >> 16) & 255, ($n >> 8) & 255, $n & 255];
        $target = $percent >= 0 ? 255 : 0;
        $ratio = abs($percent) / 100;

        $channels = array_map(
            fn (int $c) => (int) round($c + ($target - $c) * $ratio),
            $channels
        );

        return sprintf('#%02x%02x%02x', $channels[0], $channels[1], $channels[2]);
    }

    private static function rgba(string $hex, float $alpha): string
    {
        $n = hexdec(ltrim($hex, '#'));

        return 'rgba('.(($n >> 16) & 255).','.(($n >> 8) & 255).','.($n & 255).','.$alpha.')';
    }
}
