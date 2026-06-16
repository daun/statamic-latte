<?php

namespace Tests\Components;

use Illuminate\View\View;
use Miko\LaravelLatte\IComponent;

/**
 * A real miko IComponent Latte component that renders a `.latte` view.
 * Used to prove the Latte dispatch path of `<x-…>` components.
 */
class Badge implements IComponent
{
    protected string $label = '';

    public function init(...$params): void
    {
        $this->label = $params['label'] ?? '';
    }

    public function render(): View|string
    {
        return view('badge-latte-component', ['label' => $this->label]);
    }
}
