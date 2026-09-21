<?php

declare(strict_types=1);

namespace Essabu\Toli\Laravel\Console;

use Essabu\Toli\Toli;
use Illuminate\Console\Command;

/** What the gateway answers, from the terminal: the catalogue, by category. */
final class KindsCommand extends Command
{
    protected $signature = 'toli:kinds';

    protected $description = 'List the kinds of question the Toli gateway answers, by category.';

    public function handle(Toli $toli): int
    {
        $catalogue = $toli->kinds();

        $this->line("Contract version {$catalogue->contractVersion}");

        foreach ($catalogue->categories as $category) {
            $this->newLine();
            $this->info($category['name']);
            if ($category['kinds'] === []) {
                $this->line('  (declared, nothing under it yet)');
            }
            foreach ($category['kinds'] as $kind) {
                $spec = $catalogue->kinds[$kind] ?? [];
                $required = implode(', ', $catalogue->required($kind));
                $this->line("  {$kind}  —  {$spec['description']}");
                $this->line("      question: {$required}  ·  certainty: {$catalogue->certainty($kind)}");
            }
        }

        return self::SUCCESS;
    }
}
