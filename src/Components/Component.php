<?php

namespace Daun\StatamicLatte\Components;

/**
 * Optional backing class for a `<x-…>` Latte component.
 *
 * A component is, first and foremost, a `.latte` template resolved under the
 * `components/` view directory. Extend this class when a component needs PHP
 * logic: constructor parameters are filled from the tag's attributes (via the
 * container), and {@see data()} is spread into the template's variables.
 *
 *   class Alert extends Component
 *   {
 *       public function __construct(
 *           public string $type = 'info',
 *       ) {}
 *
 *       public function classes(): string
 *       {
 *           return "alert alert-{$this->type}";
 *       }
 *
 *       // data() defaults to the public properties ($type); override to add more.
 *       public function data(): array
 *       {
 *           return [...parent::data(), 'classes' => $this->classes()];
 *       }
 *   }
 *
 * The matching template `components/alert.latte` then reads `{$type}` and
 * `{$classes}` alongside any raw attributes passed at the call site.
 */
abstract class Component
{
    /**
     * Variables exposed to the component's Latte template.
     *
     * Defaults to the component's public properties. Override to compute
     * derived values or rename keys.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return get_object_vars($this);
    }
}
