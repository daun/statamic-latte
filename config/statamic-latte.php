<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cascade
    |--------------------------------------------------------------------------
    |
    | Latte does not promote page fields to top-level variables: the title
    | field is only available as `$page->title`, not as `$title`. This is
    | idiomatic for Latte and plain PHP. If you prefer the Statamic way of
    | handling this, you can configure it here.
    |
    | "page" — page fields removed; reach them through `$page` instead
    | "full" — Statamic's behaviour: page fields available as variables
    |
    */

    'cascade' => 'page',

];
