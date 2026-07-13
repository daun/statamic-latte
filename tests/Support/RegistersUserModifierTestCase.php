<?php

namespace Tests\Support;

use Statamic\Modifiers\Modifier;
use Tests\Fixtures\Modifiers\CustomDate;
use Tests\TestCase;

class RegistersUserModifierTestCase extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['statamic.extensions'][Modifier::class]['date'] = CustomDate::class;
    }
}
