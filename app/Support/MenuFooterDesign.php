<?php

namespace App\Support;

/**
 * Dashboard-controlled look of the site menu (navbar) and footer.
 *
 * Values are stored through the existing Settings store (keys prefixed
 * "menu_" / "footer_"). Only whitelisted options, validated hex colors and
 * bounded sizes reach the generated <style> block, so printing it is safe.
 */
final class MenuFooterDesign
{
    public const MENU_SIZES = [
        'sm' => ['label' => 'ছোট (১৪ পিক্সেল)', 'px' => 14],
        'md' => ['label' => 'মাঝারি (১৫ পিক্সেল)', 'px' => 15],
        'lg' => ['label' => 'বড় (১৭ পিক্সেল)', 'px' => 17],
    ];

    public const MENU_ALIGNS = [
        'center' => 'কেন্দ্রে',
        'left' => 'বামে',
        'right' => 'ডানে',
    ];

    private const ALIGN_CSS = [
        'center' => 'center',
        'left' => 'flex-start',
        'right' => 'flex-end',
    ];

    public const FOOTER_SIZES = [
        'sm' => ['label' => 'ছোট (১৪ পিক্সেল)', 'px' => 14],
        'md' => ['label' => 'মাঝারি (১৫ পিক্সেল)', 'px' => 15],
        'lg' => ['label' => 'বড় (১৭ পিক্সেল)', 'px' => 17],
    ];

    /** Spread into Settings::DEFAULTS. The defaults reproduce the current site look. */
    public const DEFAULTS = [
        'menu_bg' => '#123b68',
        'menu_text' => '#ffffff',
        'menu_hover_bg' => '#0b2745',
        'menu_size' => 'md',
        'menu_align' => 'center',
        'menu_sticky' => '1',

        'footer_bg' => '#0b2745',
        'footer_text' => '#cfd8e3',
        'footer_heading' => '#ffffff',
        'footer_accent' => '#d60000',
        'footer_hover' => '#ffffff',
        'footer_size' => 'md',
    ];

    /**
     * Normalized, safe design values plus the CSS for the public site.
     *
     * @return array{opt: array<string, string>, css: string}
     */
    public static function all(): array
    {
        $s = Settings::all();

        $opt = [
            'menu_bg' => self::color($s['menu_bg'] ?? '', 'menu_bg'),
            'menu_text' => self::color($s['menu_text'] ?? '', 'menu_text'),
            'menu_hover_bg' => self::color($s['menu_hover_bg'] ?? '', 'menu_hover_bg'),
            'menu_size' => self::pick($s['menu_size'] ?? '', self::MENU_SIZES, 'md'),
            'menu_align' => self::pick($s['menu_align'] ?? '', self::MENU_ALIGNS, 'center'),
            'menu_sticky' => ($s['menu_sticky'] ?? '1') === '1' ? '1' : '0',

            'footer_bg' => self::color($s['footer_bg'] ?? '', 'footer_bg'),
            'footer_text' => self::color($s['footer_text'] ?? '', 'footer_text'),
            'footer_heading' => self::color($s['footer_heading'] ?? '', 'footer_heading'),
            'footer_accent' => self::color($s['footer_accent'] ?? '', 'footer_accent'),
            'footer_hover' => self::color($s['footer_hover'] ?? '', 'footer_hover'),
            'footer_size' => self::pick($s['footer_size'] ?? '', self::FOOTER_SIZES, 'md'),
        ];

        return ['opt' => $opt, 'css' => self::css($opt)];
    }

    /** @param array<string, string> $opt */
    private static function css(array $opt): string
    {
        $align = self::ALIGN_CSS[$opt['menu_align']] ?? 'center';
        $menuPx = self::MENU_SIZES[$opt['menu_size']]['px'];
        $footerPx = self::FOOTER_SIZES[$opt['footer_size']]['px'];

        // A light menu background needs a dark toggler; the default white icon would vanish.
        $dark = Colors::isDark($opt['menu_bg']);
        $toggle = $dark ? '#ffffff' : '#1b1f24';
        $toggleIcon = $dark ? 'brightness(0) invert(1)' : 'none';
        $toggleBg = $dark ? 'rgba(255,255,255,.10)' : 'rgba(0,0,0,.05)';

        // The bar is a soft gradient around the chosen colour, with the site accent as a
        // thin top line. Dropdowns stay light, so their text is the menu colour itself.
        $top = self::shade($opt['menu_bg'], 8);
        $bottom = self::shade($opt['menu_bg'], -18);
        $accent = $opt['footer_accent'];
        $dropText = $dark ? self::shade($opt['menu_bg'], -6) : '#1f2937';
        $dropHover = $dark ? self::shade($opt['menu_bg'], 90) : '#eef2f7';
        $dropPx = max(13, $menuPx - 1);

        $css = '.navbar-custom{background:linear-gradient(180deg,'.$top.' 0%,'.$opt['menu_bg'].' 55%,'.$bottom.' 100%);'
            .'border-top:3px solid '.$accent.';'
            .'box-shadow:0 10px 26px rgba(8,20,35,.16);';

        if ($opt['menu_sticky'] !== '1') {
            $css .= 'position:static;';
        }

        $css .= '}';
        $css .= '.navbar-custom .navbar-nav{justify-content:'.$align.' !important;gap:2px;padding:6px 0;}';
        $css .= '.navbar-custom .nav-link{color:'.$opt['menu_text'].' !important;font-size:'.$menuPx.'px;font-weight:600;'
            .'letter-spacing:.15px;padding:9px 15px;margin:5px 1px;border-radius:9px;line-height:1.35;'
            .'transition:background .18s ease,color .18s ease,box-shadow .18s ease;}';
        $css .= '.navbar-custom .nav-link:hover,.navbar-custom .nav-link:focus-visible{background:'.$opt['menu_hover_bg'].';color:'.$opt['menu_text'].' !important;}';
        $css .= '.navbar-custom .nav-link.active{background:'.$opt['menu_hover_bg'].';box-shadow:inset 0 -2px 0 '.$accent.';}';
        $css .= '.navbar-custom .nav-link:focus-visible{outline:2px solid '.$accent.';outline-offset:-2px;}';
        $css .= '.navbar-custom .navbar-toggler{border:1px solid '.$toggle.';border-radius:10px;padding:6px 10px;margin:8px 0;background:'.$toggleBg.';}';
        $css .= '.navbar-custom .navbar-toggler:focus{box-shadow:0 0 0 3px '.self::rgba($accent, 0.22).';}';
        $css .= '.navbar-custom .navbar-toggler-icon{filter:'.$toggleIcon.';}';

        $css .= '@media (min-width:992px){'
            .'.navbar-custom .dropdown-menu{background:#fff;border:0;border-radius:12px;padding:7px;margin-top:4px;min-width:230px;box-shadow:0 18px 44px rgba(8,20,35,.22);}'
            .'.navbar-custom .dropdown-item{border-radius:8px;padding:8px 14px;color:'.$dropText.';font-size:'.$dropPx.'px;font-weight:500;white-space:normal;}'
            .'.navbar-custom .dropdown-item:hover,.navbar-custom .dropdown-item:focus{background:'.$dropHover.';color:'.$dropText.';}'
            .'.navbar-custom .dropdown-item.is-current{background:'.$dropHover.';color:'.$accent.';font-weight:600;}'
            .'}';

        $css .= '@media (max-width:991.98px){'
            .'.navbar-custom .navbar-collapse{padding:4px 0 12px;}'
            .'.navbar-custom .nav-link{margin:2px 0;padding:10px 14px;}'
            .'.navbar-custom .nav-link.active{box-shadow:inset 3px 0 0 '.$accent.';}'
            .'.navbar-custom .nav-item.dropdown > .dropdown-menu{position:static;float:none;background:rgba(0,0,0,.16);border:0;border-radius:10px;box-shadow:none;padding:6px;margin:4px 0 8px 12px;}'
            .'.navbar-custom .dropdown-item{color:'.$opt['menu_text'].';font-size:'.$dropPx.'px;padding:8px 12px;border-radius:8px;}'
            .'.navbar-custom .dropdown-item:hover,.navbar-custom .dropdown-item:focus{background:'.$opt['menu_hover_bg'].';color:'.$opt['menu_text'].';}'
            .'.navbar-custom .dropdown-item.is-current{color:'.$accent.';font-weight:600;}'
            .'}';

        $css .= '.sa-footer{background:'.$opt['footer_bg'].';color:'.$opt['footer_text'].';border-top-color:'.$opt['footer_accent'].';font-size:'.$footerPx.'px;}';
        $css .= '.sa-footer a{color:'.$opt['footer_text'].';}';
        $css .= '.sa-footer a:hover{color:'.$opt['footer_hover'].';}';
        $css .= '.sa-footer-title{color:'.$opt['footer_heading'].';border-bottom-color:'.$opt['footer_accent'].';}';
        $css .= '.sa-social:hover{background:'.$opt['footer_accent'].';border-color:'.$opt['footer_accent'].';}';

        return $css;
    }

    /** @param array<string, array{label: string}> $allowed */
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
