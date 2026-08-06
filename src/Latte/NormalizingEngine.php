<?php

namespace Daun\StatamicLatte\Latte;

use Daun\StatamicLatte\Data\Content;
use Daun\StatamicLatte\Latte\Support\Sections;
use Miko\LaravelLatte\LatteEngine;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Fields\Value;

/**
 * Extends Miko\LaravelLatte\LatteEngine, inserting Statamic data normalization
 * (Content objects + plain arrays) at the render boundary.
 *
 * Everything else — deterministic Livewire keys, filters, nodes, config — is
 * inherited unchanged; we only reshape the data on the way in.
 */
class NormalizingEngine extends LatteEngine
{
    public function get($path, array $data = [])
    {
        // Substitute deferred {yield} placeholders once the whole template has
        // rendered, so sections defined anywhere resolve regardless of order.
        Sections::beginRender();

        try {
            $data = Content::wrapAll($this->stripPageFields($data));

            return Sections::resolve(parent::get($path, $data));
        } finally {
            Sections::endRender();
        }
    }

    /**
     * Drop the current page's fields from the view data, keeping `$page`.
     */
    protected function stripPageFields(array $data): array
    {
        $page = $data['page'] ?? null;

        if (config('statamic-latte.cascade', 'page') !== 'page' || ! $page instanceof Augmentable) {
            return $data;
        }

        return array_filter(
            $data,
            fn (mixed $value) => ! $value instanceof Value || ! static::belongsTo($value, $page),
        );
    }

    /**
     * Whether a value was augmented from the given item.
     */
    protected static function belongsTo(Value $value, Augmentable $page): bool
    {
        $augmentable = $value->augmentable();

        if ($augmentable === $page) {
            return true;
        }

        $id = $augmentable instanceof Augmentable ? static::id($augmentable) : null;

        return $id !== null && $id === static::id($page);
    }

    protected static function id(Augmentable $item): mixed
    {
        return method_exists($item, 'id') ? $item->id() : null;
    }
}
