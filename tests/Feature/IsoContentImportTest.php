<?php

namespace Tests\Feature;

use App\Models\IsoContentChecklist;
use App\Models\IsoContentForm;
use App\Models\IsoContentKpi;
use App\Models\IsoContentRisk;
use App\Models\IsoContentWorkflow;
use App\Models\IsoOverlay;
use App\Services\IsoContent\DefaultContentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsoContentImportTest extends TestCase
{
    use RefreshDatabase;

    private DefaultContentImportService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(DefaultContentImportService::class);
    }

    public function test_import_all_is_idempotent(): void
    {
        $first  = $this->svc->importAll();
        $second = $this->svc->importAll();

        $this->assertSame(IsoContentWorkflow::count(),  $first->workflows->created);
        $this->assertSame(IsoContentChecklist::count(), $first->checklists->created);
        $this->assertSame(IsoContentKpi::count(),       $first->kpis->created);
        $this->assertSame(IsoContentRisk::count(),      $first->risks->created);
        $this->assertSame(IsoContentForm::count(),      $first->forms->created);

        $this->assertNotNull(IsoOverlay::where('code', 'default_iso_pack')->first());
    }

    public function test_default_overlay_references_library_items(): void
    {
        $this->svc->importAll();
        $overlay = IsoOverlay::where('code', 'default_iso_pack')->first();
        $this->assertNotNull($overlay);

        $draft = $overlay->draftVersion() ?? $overlay->versions()->latest()->first();
        $this->assertGreaterThan(0, $draft->items()->where('ref_source', 'library')->count());
    }

    public function test_workflow_states_and_transitions_are_consistent(): void
    {
        $this->svc->importWorkflows();
        foreach (IsoContentWorkflow::all() as $w) {
            $stateKeys = array_column($w->states, 'key');
            foreach ($w->transitions as $t) {
                $this->assertContains($t['from'], $stateKeys);
                $this->assertContains($t['to'],   $stateKeys);
            }
            $initials = array_filter($w->states, fn($s) => ($s['kind'] ?? null) === 'initial');
            $this->assertCount(1, $initials, "Workflow {$w->technical_key} must have exactly one initial state");
        }
    }

    public function test_kpi_formulas_are_safe(): void
    {
        $this->svc->importKpis();
        foreach (IsoContentKpi::all() as $kpi) {
            $this->assertDoesNotMatchRegularExpression('/exec|eval|system|shell/i', $kpi->formula);
        }
    }

    public function test_localisation_keys_present(): void
    {
        $this->svc->importAll();
        foreach (IsoContentWorkflow::all() as $m) {
            $this->assertArrayHasKey('en', $m->name, "Workflow {$m->technical_key} missing English name");
        }
        foreach (IsoContentKpi::all() as $m) {
            $this->assertArrayHasKey('en', $m->name);
        }
    }
}