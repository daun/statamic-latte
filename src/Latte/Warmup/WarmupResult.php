<?php

namespace Daun\StatamicLatte\Latte\Warmup;

/**
 * Outcome of a warmup run: which files compiled, which failed (with their
 * exception), and whether any views were found at all.
 */
class WarmupResult
{
    /**
     * @param  array<int, string>  $compiled  Absolute paths of successfully compiled views.
     * @param  array<string, \Throwable>  $failed  Absolute path => exception, for views that failed to compile.
     */
    public function __construct(
        public readonly array $compiled = [],
        public readonly array $failed = [],
    ) {}

    public function successful(): bool
    {
        return $this->failed === [];
    }

    public function compiledCount(): int
    {
        return count($this->compiled);
    }

    public function failedCount(): int
    {
        return count($this->failed);
    }

    public function isEmpty(): bool
    {
        return $this->compiled === [] && $this->failed === [];
    }

    public function exitCode(): int
    {
        return $this->successful() ? 0 : 1;
    }
}
