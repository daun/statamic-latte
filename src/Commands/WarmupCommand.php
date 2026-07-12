<?php

namespace Daun\StatamicLatte\Commands;

use Daun\StatamicLatte\Latte\Warmup\Warmer;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;

/**
 * Compiles every `.latte` view up front so the first visitor after a deploy
 * doesn't pay the compile cost — and so compile errors surface here, at
 * warmup time, instead of on a real request.
 *
 * Laravel's `view:cache` only ever compiles `.blade.php` files, so this
 * fills the gap it leaves for Latte templates.
 */
class WarmupCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'latte:warmup
        {--clear : Remove previously compiled Latte templates before warming}';

    protected $description = 'Compile all Latte templates ahead of time';

    public function handle(Warmer $warmer): int
    {
        if ($this->option('clear')) {
            $removed = $warmer->clear();
            $this->components->info("Cleared {$removed} compiled Latte file(s).");
        }

        $result = $warmer->warm();

        foreach ($result->compiled as $file) {
            $this->components->twoColumnDetail($file, '<info>✓ Compiled</info>');
        }

        foreach ($result->failed as $file => $exception) {
            $this->components->twoColumnDetail($file, '<fg=red>✗ Failed</>');
            $this->components->bulletList([$exception->getMessage()]);
        }

        $this->newLine();

        if ($result->isEmpty()) {
            $this->components->warn('No .latte views found.');

            return self::SUCCESS;
        }

        $summary = "{$result->compiledCount()} compiled, {$result->failedCount()} failed";

        if ($result->successful()) {
            $this->components->info($summary);

            return self::SUCCESS;
        }

        $this->components->error($summary);

        return self::FAILURE;
    }
}
