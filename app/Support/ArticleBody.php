<?php

namespace App\Support;

/**
 * Renders an article body: sanitizes the HTML first, then expands the data shortcodes
 * ([chart ...], [stats ...]) into visuals. See ArticleVisuals for the syntax.
 */
final class ArticleBody
{
    public static function render(?string $description): string
    {
        return ArticleVisuals::expand(HtmlSanitizer::clean((string) $description));
    }
}
