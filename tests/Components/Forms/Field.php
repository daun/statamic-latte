<?php

namespace Tests\Components\Forms;

use Illuminate\View\View;
use Miko\LaravelLatte\IComponent;

class Field implements IComponent
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
