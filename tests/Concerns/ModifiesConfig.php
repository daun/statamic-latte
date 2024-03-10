<?php

namespace Tests\Concerns;

use Illuminate\Config\Repository;

trait ModifiesConfig
{
    /**
     * Update a config value, passing in a value or a callback to be executed on the current value.
     */
    public function modifyConfig(string $key, mixed $data)
    {
        tap($this->app['config'], function (Repository $config) use ($key, $data) {
            $data = is_callable($data) ? $data($config->get($key)) : $data;
            $config->set($key, $data);
        });
    }
}
