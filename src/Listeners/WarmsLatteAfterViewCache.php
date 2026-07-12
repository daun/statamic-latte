<?php

namespace Daun\StatamicLatte\Listeners;

use Daun\StatamicLatte\Latte\Warmup\Warmer;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use RuntimeException;

/**
 * Opt-in integration with `view:cache`: Laravel's own command only compiles
 * `.blade.php` files (it hardcodes the 'blade' engine when resolving the
 * compiler), so it silently leaves every `.latte` template uncompiled. When
 * enabled via `statamic-latte.warmup_on_view_cache`, this warms Latte's
 * compiled cache right after `view:cache` finishes — which is also exactly
 * the right moment, since `view:cache` already ran `view:clear` internally
 * and wiped out any previously compiled Latte output too.
 */
class WarmsLatteAfterViewCache
{
    public function __construct(
        protected ConfigRepository $config,
        protected Warmer $warmer,
    ) {}

    public function handle(CommandFinished $event): void
    {
        if ($event->command !== 'view:cache' || $event->exitCode !== 0) {
            return;
        }

        if (! $this->config->get('statamic-latte.warmup_on_view_cache', false)) {
            return;
        }

        $output = $event->output;
        $result = $this->warmer->warm();

        foreach ($result->compiled as $file) {
            $output->writeln("  <info>✓</info> {$file}");
        }

        foreach ($result->failed as $file => $exception) {
            $output->writeln("  <error>✗ {$file}</error>");
            $output->writeln("    <fg=red>{$exception->getMessage()}</>");
        }

        $output->writeln('');
        $output->writeln(sprintf('Latte: %d compiled, %d failed', $result->compiledCount(), $result->failedCount()));

        if (! $result->successful()) {
            // CommandFinished fires after Symfony has already captured view:cache's
            // exit code, so returning/logging here can't change it. Throwing is the
            // only way left to fail the process (and therefore the deploy) when a
            // Latte template fails to compile.
            throw new RuntimeException(sprintf(
                'Latte warmup failed to compile %d template(s) after view:cache: %s',
                $result->failedCount(),
                implode(', ', array_keys($result->failed))
            ));
        }
    }
}
