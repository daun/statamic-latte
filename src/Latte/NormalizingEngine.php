<?php

namespace Daun\StatamicLatte\Latte;

use Daun\StatamicLatte\Data\Normalizer;
use Miko\LaravelLatte\LatteEngine;

/**
 * Extends Miko\LaravelLatte\LatteEngine, inserting Statamic data normalization
 * (Content objects + plain arrays) at the render boundary.
 *
 * Everything else — deterministic Livewire keys, filters, nodes, config — is
 * inherited unchanged; we only reshape the data on the way in.
 */
class NormalizingEngine extends LatteEngine
{
    public function get($path, array $data = [])
    {
        return parent::get($path, Normalizer::data($data));
    }
}
