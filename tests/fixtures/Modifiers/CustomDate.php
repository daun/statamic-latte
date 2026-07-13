<?php

namespace Tests\Fixtures\Modifiers;

use Statamic\Modifiers\Modifier;

class CustomDate extends Modifier
{
    protected static $handle = 'date';

    public function index($value, $params, $context)
    {
        return 'custom-date-modifier';
    }
}
