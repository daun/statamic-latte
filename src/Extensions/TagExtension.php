<?php

namespace Daun\StatamicLatte\Extensions;

use Latte\Extension;
use Statamic\Statamic;

/**
 * Latte extension for using Antlers tags in Latte templates.
 */
class TagExtension extends Extension
{
    public function getFunctions(): array
    {
        return [
            'statamic' => [$this, 'statamic'],
            's' => [$this, 'statamic'],
        ];
    }

    /**
     * Fetch the output of a Statamic tag.
     *
     * @param  string  $name  The tag name
     * @param mixed ...$args The tag parameters
     * @return mixed The tag output
     */
    public function statamic(string $name, ...$args): mixed
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
