<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Warm up Latte on `view:cache`
    |--------------------------------------------------------------------------
    |
    | Laravel's `view:cache` command only compiles `.blade.php` templates, so
    | `.latte` templates are left to compile lazily on first request. Enable
    | this to also warm the Latte compiled cache right after `view:cache`
    | finishes — catching compile errors at deploy time instead of on your
    | first visitor. Equivalent to running `php artisan latte:warmup` after
    | `view:cache` in your deploy script, without adding a second command.
    |
    */

    'warmup_on_view_cache' => false,

];
