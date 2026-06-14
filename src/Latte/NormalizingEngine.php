<?php

namespace Daun\StatamicLatte\Latte;

use Daun\StatamicLatte\Data\Normalizer;
use Daun\StatamicLatte\Latte\Support\Sections;
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
        // Substitute deferred {yield} placeholders once the whole template
        // (layout + body + partials) has rendered, so sections defined anywhere
        // resolve regardless of render order.
        return Sections::resolve(parent::get($path, Normalizer::data($data)));
    }
}
