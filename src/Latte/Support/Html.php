<?php

namespace Daun\StatamicLatte\Latte\Support;

/** Helpers for inspecting rendered HTML. */
class Html
{
    /**
     * Elements that render on their own, whose mere presence counts as content.
     * Extend to change what {iftext} considers renderable.
     *
     * @var list<string>
     */
    public static array $renderingElements = [
        'img', 'picture', 'svg', 'video', 'audio', 'iframe', 'embed', 'object',
        'canvas', 'script', 'hr', 'table', 'form', 'input', 'button', 'select',
        'textarea', 'progress', 'meter',
    ];

    /** Whitespace plus the invisible characters WYSIWYG editors leave behind. */
    protected const Blank = '~[\s\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+~u';

    /**
     * Does this HTML render anything? True if it contains text outside of tags
     * or any element that renders on its own.
     */
    public static function hasText(?string $html): bool
    {
        if ($html === null || trim($html) === '') {
            return false;
        }

        // <style>/<template> contents would survive tag stripping as if visible.
        $html = preg_replace('~<(style|template)\b[^>]*>.*?</\1\s*>~is', '', $html) ?? $html;

        $text = strip_tags($html, static::$renderingElements);

        // Entities like &nbsp; are whitespace, not text
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace(static::Blank, '', $text) ?? $text;

        return trim($text) !== '';
    }
}
