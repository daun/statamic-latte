<?php

namespace Daun\StatamicLatte\Latte\Loaders;

use Daun\StatamicLatte\Latte\Support\TagExpressionSyntax;
use Daun\StatamicLatte\Latte\Support\TagMethodSyntax;
use Latte\Loader;

/**
 * Loader decorator that lowers Statamic nested tag-method syntax before the
 * template reaches Latte's compiler.
 *
 * It wraps any inner {@see Loader} and transforms only the loaded source via
 * {@see TagMethodSyntax}; every other responsibility (resolving names, expiry,
 * unique ids, file IO) is delegated untouched. This keeps the underlying
 * loader a pure IO concern and isolates the one place where Statamic's runtime
 * tag-method dispatch is reconciled with Latte's compile-time tag registration.
 */
class TagMethodLoader implements Loader
{
    public function __construct(
        protected Loader $inner,
    ) {}

    /** Latte comments and raw {antlers} islands, where Statamic syntax stays literal. */
    private const PROTECTED = '#(\{\*.*?\*\}|\{antlers\b[^}]*\}.*?\{/antlers\})#s';

    public function getContent(string $name): string
    {
        return $this->rewrite($this->inner->getContent($name));
    }

    /**
     * Rewrite Statamic tag syntax in every part outside a protected region.
     */
    protected function rewrite(string $content): string
    {
        $parts = preg_split(self::PROTECTED, $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $parts[$i] = TagMethodSyntax::rewrite(TagExpressionSyntax::rewrite($part));
            }
        }

        return implode('', $parts);
    }

    public function getReferredName(string $name, string $referringName): string
    {
        return $this->inner->getReferredName($name, $referringName);
    }

    public function getUniqueId(string $name): string
    {
        return $this->inner->getUniqueId($name);
    }
}
