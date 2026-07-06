<?php

namespace Daun\StatamicLatte\Data;

/**
 * @deprecated Use {@see Content::wrap()}, {@see Content::wrapAll()} and
 * {@see Content::unwrap()} instead. This shim delegates to Content and exists
 * only so that already-compiled Latte templates baking the old FQCN keep
 * working across an addon upgrade. It will be removed in the next major.
 */
class Normalizer
{
    /**
     * @deprecated Use {@see Content::wrapAll()}.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function data(array $data): array
    {
        return Content::wrapAll($data);
    }

    /**
     * @deprecated Use {@see Content::wrap()}.
     */
    public static function normalize(mixed $value): mixed
    {
        return Content::wrap($value);
    }

    /**
     * @deprecated Use {@see Content::unwrap()}.
     */
    public static function unwrap(mixed $value): mixed
    {
        return Content::unwrap($value);
    }
}
