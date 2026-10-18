<?php

namespace Daun\StatamicLatte\Latte\Support;

use Statamic\Tags\FluentTag as StatamicFluentTag;

/**
 * Fluent tag that keeps explicit null params, like Antlers does for `:param="missing"`.
 * Statamic's version turns them into `true`, which breaks tags checking `has()`.
 */
class FluentTag extends StatamicFluentTag
{
    /**
     * @param  string  $name
     * @return static
     */
    public static function make($name)
    {
        $instance = app(static::class);

        $instance->name = $name;

        return $instance;
    }

    /**
     * @param  string  $param
     * @param  mixed  $value
     * @return $this
     */
    public function param($param, $value = true)
    {
        $this->params[$param] = $value;

        return $this;
    }
}
