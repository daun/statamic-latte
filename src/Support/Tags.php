<?php

namespace Daun\StatamicLatte\Support;

use Illuminate\Support\Str;
use Statamic\Statamic;

class Tags
{
    public const PREFIX = 's:';

    public static function prefix(string $name): string
    {
        return self::PREFIX . $name;
    }

    public static function unprefix(string $name): string
    {
        return Str::replaceStart(self::PREFIX, '', $name);
    }

    /**
     * Fetch the output of a Statamic tag.
     *
     * @param  string  $name  The tag name
     * @param  mixed  ...$args  The tag parameters
     * @return mixed The tag output
     */
    public static function fetch(string $name, ...$args)
    {
        $params = $args;

        // Allow passing in params as a single array argument
        foreach ($args as $key => $arg) {
            if (is_int($key) && is_array($arg) && ! array_is_list($arg)) {
                $params = array_merge($params, $arg);
                unset($params[$key]);
            }
        }

        return Statamic::tag($name)->params($params)->fetch();
    }
}
