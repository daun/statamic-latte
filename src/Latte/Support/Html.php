<?php

namespace Daun\StatamicLatte\Latte\Support;

/**
 * Helpers for inspecting rendered HTML.
 */
class Html
{
    /**
     * Elements that render output on their own, without holding any text.
     *
     * They are kept when stripping tags, so their mere presence counts as
     * content. Extend or replace this list to change what {iftext} considers
     * renderable.
     *
     * @var list<string>
     */
    public static array $renderingElements = [
        'img', 'picture', 'svg', 'video', 'audio', 'iframe', 'embed', 'object',
        'canvas', 'script', 'hr', 'table', 'form', 'input', 'button', 'select',
        'textarea', 'progress', 'meter',
    ];

    /**
     * Whitespace that carries no visible text: regular whitespace plus the
     * invisible characters WYSIWYG editors leave behind (&nbsp;, zero-width
     * spaces, BOM).
     */
    protected const Blank = '~[\s\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+~u';

    /**
     * Does this HTML render anything? True if it contains text outside of tags,
     * or any element that renders on its own (an image, a form input, ...).
     */
    public static function hasText(?string $html): bool
    {
        if ($html === null || trim($html) === '') {
            return false;
        }

        // Drop <style> and <template> blocks wholesale: their contents are not
        // visible text, but would survive tag stripping as if they were.
        $html = preg_replace('~<(style|template)\b[^>]*>.*?</\1\s*>~is', '', $html) ?? $html;

        // Strip tags, keeping the ones that count as content on their own
        $text = strip_tags($html, static::$renderingElements);

        // Entities like &nbsp; are whitespace, not text
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace(static::Blank, '', $text) ?? $text;

        return trim($text) !== '';
    }
}
