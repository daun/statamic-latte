<?php

namespace Daun\StatamicLatte\Latte\Warmup;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Collection;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Latte\Engine;
use Latte\Runtime\Cache;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Discovers and compiles every `.latte` view across all registered view
 * locations, using the addon's own Latte engine instance (injected) so
 * compiled output lands in the real compiled path with the real extensions
 * and tags registered — never a bare `new Engine()`.
 *
 * Shared between the `latte:warmup` command and the opt-in `view:cache`
 * listener so both call sites compile the exact same way.
 */
class Warmer
{
    public function __construct(
        protected Engine $engine,
        protected Factory $view,
        protected ConfigRepository $config,
    ) {}

    /**
     * Discover every `.latte` file across all registered view paths and
     * namespace hints (vendor-published/overridden views), deduplicated.
     *
     * @return Collection<int, string>
     */
    public function discover(): Collection
    {
        return $this->paths()
            ->flatMap(fn (string $path) => $this->latteFilesIn($path))
            ->unique()
            ->values();
    }

    /**
     * Compile every discovered `.latte` file. Per-file failures are caught
     * so the rest of the batch still compiles.
     */
    public function warm(): WarmupResult
    {
        $compiled = [];
        $failed = [];

        foreach ($this->discover() as $file) {
            try {
                $this->engine->warmupCache($file);
                $compiled[] = $file;
            } catch (\Throwable $e) {
                $failed[$file] = $e;
            }
        }

        return new WarmupResult($compiled, $failed);
    }

    /**
     * Remove previously compiled Latte output (not Blade's) from the
     * compiled views directory, using Latte's own cache filename pattern.
     * Returns the number of files removed.
     */
    public function clear(): int
    {
        $directory = $this->compiledDirectory();

        if (! $directory || ! is_dir($directory)) {
            return 0;
        }

        $removed = 0;

        foreach (Finder::create()->in($directory)->files() as $file) {
            $path = $file->getPathname();

            if (Cache::isCacheFile($path) || str_ends_with($path, '.lock')) {
                @unlink($path);
                $removed++;
            }
        }

        return $removed;
    }

    protected function compiledDirectory(): ?string
    {
        return $this->config->get('latte.compiled') ?? $this->config->get('view.compiled');
    }

    /**
     * Every view path and namespace hint directory, deduplicated the same
     * way Laravel's own `view:cache` command does: reject paths that are
     * nested inside another registered path.
     *
     * @return Collection<int, string>
     */
    protected function paths(): Collection
    {
        $finder = $this->view->getFinder();

        if (! $finder instanceof FileViewFinder) {
            return collect();
        }

        /** @var Collection<int, string> $hints */
        $hints = collect($finder->getHints())->flatten();

        $paths = collect($finder->getPaths())
            ->merge($hints)
            ->unique()
            ->filter(fn (string $path) => is_dir($path));

        return $paths->reject(fn ($path) => $paths->contains(function ($existing) use ($path) {
            return $existing !== $path && str_starts_with(realpath($path) ?: $path, realpath($existing) ?: $existing);
        }))->values();
    }

    /**
     * @return Collection<int, string>
     */
    protected function latteFilesIn(string $path): Collection
    {
        $files = Finder::create()->in($path)->name('*.latte')->files();

        return collect($files)->map(fn (SplFileInfo $file) => $file->getRealPath());
    }
}
