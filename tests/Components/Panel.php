<?php

namespace Tests\Components;

use Daun\StatamicLatte\Components\Component;

/**
 * A Latte component with a backing class. Constructor params are filled from the
 * tag's attributes; data() adds a derived value spread into the template.
 */
class Panel extends Component
{
    public function __construct(
        public string $type = 'info',
    ) {}

    public function data(): array
    {
        return [...parent::data(), 'heading' => strtoupper($this->type)];
    }
}
