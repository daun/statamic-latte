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
 * A field value that is already rendered HTML.
 *
 * Latte escapes every printed value, which is exactly right for a title or an
 * excerpt but wrong for a markdown or bard field: those hold markup Statamic
 * generated itself, so printing them escaped shows literal `<p>` tags. Marking
 * them as {@see HtmlStringable} tells Latte's escaper the value is already safe
 * — `{$entry->content}` prints markup, no `|noescape` needed.
 *
 * The decision is made from the *fieldtype*, never from the value: a title that
 * happens to contain `<a>` is still a text field and stays escaped. Only fields
 * whose fieldtype renders HTML on augmentation qualify, listed in
 * {@see static::$fieldtypes}.
 *
 * Being pre-escaped only applies to HTML text context. Latte still escapes the
 * value when it lands in an attribute, a URL, or a script — the marker opts out
 * of one escaper, not out of context-aware escaping.
 */
final class HtmlValue implements Boolable, Countable, Htmlable, HtmlStringable, JsonSerializable, Stringable
{
    /**
     * Fieldtypes whose augmented value is HTML rendered by Statamic itself.
     *
     * Add your own (`HtmlValue::$fieldtypes[] = 'my_editor'`) if an addon
     * fieldtype augments to trusted markup, or empty the list to escape
     * everything and go back to explicit `|noescape`.
     *
     * @var list<string>
     */
    public static array $fieldtypes = ['bard', 'markdown', 'redactor'];

    public function __construct(private string $html) {}

    /**
     * Does this fieldtype hand us rendered HTML?
     */
    public static function isHtmlFieldtype(?Fieldtype $fieldtype): bool
    {
        return $fieldtype && in_array($fieldtype::handle(), self::$fieldtypes, true);
    }

    /**
     * Mark a value as rendered HTML, if it is a string worth marking.
     *
     * Empty and non-string values are handed back untouched: an object is
     * always truthy, so an empty field has to stay a plain falsy value for
     * `{if $entry->content}` to keep meaning "has content".
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
     * Length of the markup, matching what `|length` reported back when the
     * field was a plain string.
     *
     * Latte's `|length` accepts `array|Countable|Traversable|string`, and
     * compiled templates run under `declare(strict_types=1)`, so without
     * Countable an object would be a TypeError there. Filters typed
     * `string|Stringable` (upper, truncate, ...) accept us as they are.
     */
    public function count(): int
    {
        return mb_strlen($this->html, 'UTF-8');
    }

    /**
     * Return the plain string, for the boundaries that predate this wrapper:
     * Statamic modifiers, Antlers, n:attr normalization.
     */
    public function scalar(): string
    {
        return $this->html;
    }

    public function jsonSerialize(): mixed
    {
        return $this->html;
    }
}
