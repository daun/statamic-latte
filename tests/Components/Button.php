<?php

namespace Tests\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A real Laravel Blade class component.
 * Used to prove the Blade dispatch path of `<x-…>` components.
 */
class Button extends Component
{
    public function __construct(public string $type = 'button') {}

    public function render(): View
    {
        return view('button-blade-component');
    }
}
