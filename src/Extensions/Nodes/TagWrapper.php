<?php

namespace Daun\StatamicLatte\Extensions;

use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Statamic\Tags\FluentTag;

/**
 * Wrap Statamic/Antlers tag in simple object and auto-fetch.
 */
class StatamicTag extends FluentTag
{
    public static function make($name)
    {
        $instance = app(static::class);
        $instance->name = $name;

        return $instance;
    }

    /**
     * Recursively fetch result of a tag.
     */
    public function fetch(): mixed
    {
        $fetched = parent::fetch();

        return $this->unwrap($fetched);
    }

    /**
     * Recursively unwrap Value objects.
     */
    protected function unwrap(mixed $item)
    {
        if ($item instanceof Value) {
            $item = $item->value();
        }

        if ($item instanceof Values) {
            $item = $item->all();
        }

        if (is_iterable($item)) {
            foreach ($item as $key => $value) {
                $item[$key] = $this->unwrap($value);
            }
            if (is_array($item) && ! array_is_list($item)) {
                $item = (object) $item;
            }
        }

        return $item;
    }
}
