<?php

namespace Daun\StatamicLatte\Data;

use Countable;
use Illuminate\Contracts\Support\Htmlable;
use JsonSerializable;
use Latte\Runtime\HtmlStringable;
use Statamic\Contracts\Support\Boolable;
use Statamic\Fields\Fieldtype;
use Stringable;

/**
 * A field value that is already rendered HTML, so Latte prints it unescaped —
 * no `|noescape` needed. Decided by fieldtype, never by content, and only in
 * HTML text context: attributes, URLs and scripts are still escaped.
 */
final class HtmlValue implements Boolable, Countable, Htmlable, HtmlStringable, JsonSerializable, Stringable
{
    /**
     * Fieldtypes that augment to trusted markup. Extendable by addons
     * (`HtmlValue::$fieldtypes[] = 'my_editor'`).
     *
     * @var list<string>
     */
    public static array $fieldtypes = ['bard', 'markdown', 'redactor'];

    public function __construct(private string $html) {}

    public static function isHtmlFieldtype(?Fieldtype $fieldtype): bool
    {
        return $fieldtype && in_array($fieldtype::handle(), self::$fieldtypes, true);
    }

    /**
     * Mark a value as rendered HTML. Empty/non-string values pass through
     * untouched so an empty field stays falsy for {if $entry->content}.
     */
    public static function mark(mixed $value): mixed
    {
        return is_string($value) && $value !== '' ? new self($value) : $value;
    }

    public function __toString(): string
    {
        return $this->html;
    }

    public function toHtml(): string
    {
        return $this->html;
    }

    public function toBool(): bool
    {
        return $this->html !== '';
    }

    /**
     * Countable exists for Latte's `|length`, which is strict-typed to
     * array|Countable|Traversable|string and would TypeError on a bare object.
     */
    public function count(): int
    {
        return mb_strlen($this->html, 'UTF-8');
    }

    /** Plain string for boundaries that predate this wrapper (modifiers, Antlers, n:attr). */
    public function scalar(): string
    {
        return $this->html;
    }

    public function jsonSerialize(): mixed
    {
        return $this->html;
    }
}
