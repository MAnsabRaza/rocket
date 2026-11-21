<?php

namespace Database\Seeders;

use App\Services\IsoContent\DefaultContentImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class IsoContentLibrarySeeder extends \Illuminate\Database\Seeder
{
    public function run(): void
    {
        /** @var DefaultContentImportService $svc */
        $svc    = App::make(DefaultContentImportService::class);
        $report = $svc->importAll(userId: null);

        $this->renderReport($report);
        Log::info('IsoContentLibrarySeeder completed', $report->toArray());
    }

    private function renderReport($report): void
    {
        if (!$this->command instanceof Command) {
            return;
        }
        $this->command->info('Default ISO content pack imported:');
        foreach (['workflows','checklists','kpis','risks','forms'] as $m) {
            $r = $report->{$m};
            $this->command->line(sprintf('  %-12s %d / %d', $m, $r->created, $r->expected));
        }
        $this->command->line('  overlay      ' . $report->overlay);
    }
}