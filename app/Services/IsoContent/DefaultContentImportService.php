<?php

namespace App\Services\IsoContent;

use App\Models\IsoContentChecklist;
use App\Models\IsoContentForm;
use App\Models\IsoContentKpi;
use App\Models\IsoContentRisk;
use App\Models\IsoContentWorkflow;
use App\Models\IsoOverlay;
use App\Models\IsoOverlayItem;
use App\Models\IsoOverlayVersion;
use App\Services\IsoOverlay\OverlayService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DefaultContentImportService
{
    public function __construct(
        private readonly OverlayService $overlayService,
    ) {}

    /**
     * Master import method. Runs all modules in a single transaction.
     */
    public function importAll(?int $userId = null, bool $dryRun = false, bool $preserve = false): ImportReport
    {
        $report = new ImportReport();
        $report->dryRun = $dryRun;
        if (!$dryRun) {
            $report->batchId = (string) \Illuminate\Support\Str::uuid();
        }

        $runner = function () use ($userId, $report, $dryRun, $preserve) {
            $report->workflows  = $this->importWorkflows($userId, $dryRun, $preserve);
            $report->checklists = $this->importChecklists($userId, $dryRun, $preserve);
            $report->kpis       = $this->importKpis($userId, $dryRun, $preserve);
            $report->risks      = $this->importRisks($userId, $dryRun, $preserve);
            $report->forms      = $this->importForms($userId, $dryRun, $preserve);
            if (!$dryRun) {
                $report->overlay = $this->ensureDefaultOverlay($userId);
            }
        };

        if ($dryRun) {
            $runner();
        } else {
            DB::transaction($runner);
        }

        Log::info('Default ISO content imported', $report->toArray());
        return $report;
    }

    // ────────────────────────────────────────────────────────────────
    // Module Importers (Idempotent via updateOrCreate)
    // ────────────────────────────────────────────────────────────────

    public function importWorkflows(?int $userId = null, bool $dryRun = false, bool $preserve = false): ModuleReport
    {
        $rows = $this->load('workflows');
        $rep  = new ModuleReport('workflows', count($rows));

        foreach ($rows as $row) {
            $r = $this->saveWorkflow($row, $userId, $dryRun, $preserve, $rep);
            if ($r === null) {
                // dry run or created-tracking handled inside
            }
        }
        return $rep;
    }

    public function importChecklists(?int $userId = null, bool $dryRun = false, bool $preserve = false): ModuleReport
    {
        $rows = $this->load('checklists');
        $rep  = new ModuleReport('checklists', count($rows));

        foreach ($rows as $row) {
            $this->saveChecklist($row, $userId, $dryRun, $preserve, $rep);
        }
        return $rep;
    }

    public function importKpis(?int $userId = null, bool $dryRun = false, bool $preserve = false): ModuleReport
    {
        $rows = $this->load('kpis');
        $rep  = new ModuleReport('kpis', count($rows));

        foreach ($rows as $row) {
            $this->saveKpi($row, $userId, $dryRun, $preserve, $rep);
        }
        return $rep;
    }

    public function importRisks(?int $userId = null, bool $dryRun = false, bool $preserve = false): ModuleReport
    {
        $rows = $this->load('risks');
        $rep  = new ModuleReport('risks', count($rows));

        foreach ($rows as $row) {
            $this->saveRisk($row, $userId, $dryRun, $preserve, $rep);
        }
        return $rep;
    }

    public function importForms(?int $userId = null, bool $dryRun = false, bool $preserve = false): ModuleReport
    {
        $rows = $this->load('forms');
        $rep  = new ModuleReport('forms', count($rows));

        foreach ($rows as $row) {
            $this->saveForm($row, $userId, $dryRun, $preserve, $rep);
        }
        return $rep;
    }

    // ────────────────────────────────────────────────────────────────
    // Save Methods (Used by Seeder & Admin Edit Screens)
    // ────────────────────────────────────────────────────────────────

    public function importRow(string $type, array $row, ?int $userId = null): string
    {
        $model = match ($type) {
            'workflows' => $this->saveWorkflow($row, $userId),
            'checklists' => $this->saveChecklist($row, $userId),
            'kpis' => $this->saveKpi($row, $userId),
            'risks' => $this->saveRisk($row, $userId),
            'forms' => $this->saveForm($row, $userId),
            default => throw new \InvalidArgumentException("Unsupported ISO content type: {$type}"),
        };

        return $model->technical_key;
    }

    public function saveWorkflow(array $row, ?int $userId = null, bool $dryRun = false, bool $preserve = false, ?ModuleReport $report = null): ?IsoContentWorkflow
    {
        $this->validateRequired($row, ['technical_key', 'category', 'name', 'states', 'transitions']);
        $this->validateTechnicalKey($row['technical_key']);
        $this->validateLocalizedName($row['name']);
        $this->validateStatesTransitions($row);

        $keys = ['category', 'name', 'description', 'states', 'transitions', 'roles', 'settings', 'is_active'];
        return $this->syncRecord(IsoContentWorkflow::class, $row, $keys, $userId, $dryRun, $preserve, $report);
    }

    public function saveChecklist(array $row, ?int $userId = null, bool $dryRun = false, bool $preserve = false, ?ModuleReport $report = null): ?IsoContentChecklist
    {
        $this->validateRequired($row, ['technical_key', 'name', 'schema']);
        $this->validateTechnicalKey($row['technical_key']);
        $this->validateLocalizedName($row['name']);
        $this->validateChecklistSchema($row['schema']);

        $keys = ['standard_ref', 'name', 'description', 'schema', 'scoring', 'is_active'];
        return $this->syncRecord(IsoContentChecklist::class, $row, $keys, $userId, $dryRun, $preserve, $report);
    }

    public function saveKpi(array $row, ?int $userId = null, bool $dryRun = false, bool $preserve = false, ?ModuleReport $report = null): ?IsoContentKpi
    {
        $this->validateRequired($row, ['technical_key', 'category', 'name', 'formula', 'unit', 'thresholds']);
        $this->validateTechnicalKey($row['technical_key']);
        $this->validateLocalizedName($row['name']);
        $this->validateFormula($row['formula']);
        $this->validateThresholds($row['thresholds']);

        $keys = ['category', 'name', 'description', 'clause_ref', 'formula', 'unit', 'frequency', 'calculation', 'thresholds', 'roles', 'source', 'is_active'];
        return $this->syncRecord(IsoContentKpi::class, $row, $keys, $userId, $dryRun, $preserve, $report);
    }

    public function saveRisk(array $row, ?int $userId = null, bool $dryRun = false, bool $preserve = false, ?ModuleReport $report = null): ?IsoContentRisk
    {
        $this->validateRequired($row, ['technical_key', 'category', 'name']);
        $this->validateTechnicalKey($row['technical_key']);
        $this->validateLocalizedName($row['name']);
        $this->validateRiskScales($row);

        $keys = ['category', 'name', 'description', 'causes', 'consequences', 'default_probability', 'default_impact', 'probability_scale', 'impact_scale', 'scoring_rule', 'levels', 'residual', 'mitigations', 'controls', 'is_active'];
        return $this->syncRecord(IsoContentRisk::class, $row, $keys, $userId, $dryRun, $preserve, $report);
    }

    public function saveForm(array $row, ?int $userId = null, bool $dryRun = false, bool $preserve = false, ?ModuleReport $report = null): ?IsoContentForm
    {
        $this->validateRequired($row, ['technical_key', 'category', 'name', 'schema']);
        $this->validateTechnicalKey($row['technical_key']);
        $this->validateLocalizedName($row['name']);
        $this->validateFormSchema($row['schema']);

        $keys = ['category', 'name', 'description', 'schema', 'approval', 'is_active'];
        return $this->syncRecord(IsoContentForm::class, $row, $keys, $userId, $dryRun, $preserve, $report);
    }

    /**
     * Shared, version-aware sync logic for all five library tables.
     *
     * - New rows are created.
     * - Identical rows are skipped (no write).
     * - Changed rows: snapshot the current row into version_history, bump version,
     *   then overwrite (unless $preserve is true, in which case the row is kept
     *   and reported as a conflict).
     * - In dry-run mode nothing is written; conflicts are still reported.
     */
    private function syncRecord(string $class, array $row, array $keys, ?int $userId, bool $dryRun, bool $preserve, ?ModuleReport $report): ?\Illuminate\Database\Eloquent\Model
    {
        $technicalKey = $row['technical_key'];
        $existing = $class::where('technical_key', $technicalKey)->first();

        $incoming = Arr::only($row, $keys) + ['updated_by' => $userId];

        if (!$existing) {
            if ($dryRun) {
                if ($report) { $report->created++; $report->createdKeys[] = $technicalKey; }
                return null;
            }
            $record = $class::create(array_merge(['technical_key' => $technicalKey, 'version' => (int) ($row['version'] ?? 1)], $incoming));
            if ($report) { $report->created++; $report->createdKeys[] = $technicalKey; }
            return $record;
        }

        // Compare current vs incoming (excluding version, which we manage).
        $current = Arr::only($existing->toArray(), array_keys($incoming));
        $differs = $this->rowsDiffer($current, $incoming);

        if (!$differs) {
            if ($report) { $report->skipped++; }
            return $existing;
        }

        if ($report) { $report->conflicts[] = $technicalKey; }

        if ($dryRun) {
            return $existing;
        }

        if ($preserve) {
            if ($report) { $report->skipped++; }
            return $existing;
        }

        // Snapshot current row into version_history before overwriting.
        $history = $existing->version_history ?? [];
        $history[] = [
            'version'  => $existing->version,
            'saved_at' => now()->toIso8601String(),
            'saved_by' => $userId,
            'data'     => Arr::except($existing->toArray(), ['version_history', 'created_at', 'updated_at', 'deleted_at']),
        ];
        $existing->forceFill(['version_history' => $history])->saveQuietly();

        $existing->increment('version');
        $existing->refresh();

        $existing->fill($incoming)->save();
        if ($report) { $report->updated++; }

        return $existing->fresh();
    }

    /**
     * Roll back a previous real import using its report.
     *
     * - Rows that were created are soft-deleted.
     * - Rows that were updated are restored from their last version_history snapshot
     *   and their version is decremented.
     */
    public function rollbackImport(ImportReport $report, ?int $userId = null): void
    {
        $pairs = [
            'workflows'  => IsoContentWorkflow::class,
            'checklists' => IsoContentChecklist::class,
            'kpis'       => IsoContentKpi::class,
            'risks'      => IsoContentRisk::class,
            'forms'      => IsoContentForm::class,
        ];

        DB::transaction(function () use ($report, $pairs, $userId) {
            foreach ($pairs as $prop => $class) {
                $mr = $report->{$prop};
                if (!$mr instanceof ModuleReport) continue;

                foreach ($mr->createdKeys as $key) {
                    $row = $class::where('technical_key', $key)->first();
                    if ($row) $row->delete();
                }

                foreach ($mr->conflicts as $key) {
                    $row = $class::where('technical_key', $key)->first();
                    if (!$row) continue;
                    $history = $row->version_history ?? [];
                    if (empty($history)) continue;
                    $last = array_pop($history);
                    $data = $last['data'] ?? null;
                    if (!is_array($data)) continue;
                    $data = Arr::except($data, ['id', 'technical_key', 'version', 'version_history', 'created_at', 'updated_at', 'deleted_at']);
                    $row->forceFill(array_merge($data, ['version_history' => $history]))->saveQuietly();
                    if ($row->version > 1) $row->decrement('version');
                }
            }
        });

        Log::info('Default ISO content import rolled back', ['batch_id' => $report->batchId]);
    }

    private function rowsDiffer(array $a, array $b): bool
    {
        foreach (array_keys($b) as $k) {
            $va = array_key_exists($k, $a) ? $a[$k] : null;
            $vb = $b[$k];
            if (is_array($va) || is_array($vb)) {
                if ($this->canonicalJson($va) !== $this->canonicalJson($vb)) return true;
            } else {
                if ($va !== $vb) return true;
            }
        }
        return false;
    }

    private function canonicalJson($value): string
    {
        if (is_array($value)) {
            $isList = array_is_list($value);
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->canonicalJson($v);
            }
            if (!$isList) {
                ksort($out);
            }
            return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (is_bool($value) || $value === null) {
            return json_encode($value);
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // ────────────────────────────────────────────────────────────────
    // Overlay Integration (THE FIX FOR VALIDATION ERRORS)
    // ────────────────────────────────────────────────────────────────

        public function ensureDefaultOverlay(?int $userId = null): ?string
    {
        $code = 'default_iso_pack';

        $overlay = IsoOverlay::where('code', $code)->first();
        if (!$overlay) {
            $overlay = $this->overlayService->createOverlay([
                'code'            => $code,
                'name'            => ['en' => 'Default ISO Pack', 'ar' => 'الحزمة الافتراضية'],
                'description'     => ['en' => 'Shipped default workflows, checklists, KPIs, risks and forms.'],
                'framework_label' => 'Multi-standard',
            ], $userId);
        }

        $draft = $overlay->draftVersion();
        if (!$draft) {
            $published = $overlay->versions()->where('status', '!=', IsoOverlayVersion::DRAFT)
                ->orderByDesc('version_no')->first();
            $draft = $published
                ? $this->overlayService->newVersion($overlay, $published, $userId)
                : $this->overlayService->newVersion($overlay, null, $userId);
        }

        // Purane library items delete karein taake fresh link ho sakein
        IsoOverlayItem::where('version_id', $draft->id)
            ->where('ref_source', 'library')
            ->delete();

        $order = 0;

        // Workflows & Checklists & Forms don't need extra data for validation
        foreach (IsoContentWorkflow::active()->get()  as $m) $this->linkLibraryItem($draft, 'workflow',  $m, ++$order);
        foreach (IsoContentChecklist::active()->get() as $m) $this->linkLibraryItem($draft, 'checklist', $m, ++$order);
        foreach (IsoContentForm::active()->get()      as $m) $this->linkLibraryItem($draft, 'form',      $m, ++$order);

        // KPIs: Must pass full data structure to satisfy OverlayService validation
        foreach (IsoContentKpi::active()->get() as $m) {
            $data = [
                'formula'     => $m->formula,
                'unit'        => $m->unit,
                'frequency'   => $m->frequency,
                'calculation' => $m->calculation,
                'target'      => (float) ($m->thresholds['target'] ?? 0),
                'thresholds'  => [
                    'warning'  => (float) ($m->thresholds['warning'] ?? 0),
                    'critical' => (float) ($m->thresholds['critical'] ?? 0),
                ],
                'direction'   => $m->thresholds['direction'] ?? 'higher_is_better',
                'roles'       => $m->roles,
                'source'      => $this->toText($m->source),
            ];
            $this->linkLibraryItem($draft, 'kpi', $m, ++$order, $data);
        }

        // Risks: Safe processing for levels to prevent Array to String conversion
        foreach (IsoContentRisk::active()->get() as $m) {
            $safeLevels = [];
            
            // Only process if levels exist AND are a proper list of arrays with max_score/label
            if (!empty($m->levels) && is_array($m->levels)) {
                foreach ($m->levels as $level) {
                    if (is_array($level) && isset($level['max_score']) && isset($level['label'])) {
                        $label = $level['label'];
                        if (is_array($label)) {
                            $label = $label[app()->getLocale()]
                                ?? $label[config('app.fallback_locale', 'en')]
                                ?? reset($label)
                                ?? '';
                        }

                        $safeLevels[] = [
                            'max_score' => (float) $level['max_score'],
                            'label'     => is_scalar($label) ? (string) $label : '',
                        ];
                    }
                }
            }

            // Fallback if no valid levels found in DB
            if (empty($safeLevels)) {
                $safeLevels = [
                    ['max_score' => 4.0,  'label' => 'Low'],
                    ['max_score' => 9.0,  'label' => 'Medium'],
                    ['max_score' => 16.0, 'label' => 'High'],
                    ['max_score' => 25.0, 'label' => 'Critical'],
                ];
            }

            $data = [
                'category'          => (string) ($m->category ?? 'operational'),
                'probability_scale' => (int) ($m->probability_scale ?? 5),
                'impact_scale'      => (int) ($m->impact_scale ?? 5),
                'scoring_rule'      => (string) ($m->scoring_rule ?? 'multiply'),
                'levels'            => $safeLevels,
                'mitigation'        => $this->toText($m->mitigations),
                'controls'          => $m->controls,
            ];
            $this->linkLibraryItem($draft, 'risk', $m, ++$order, $data);
        }

        return $overlay->code;
    }

    /**
     * Link a library record to the overlay version with optional extra data.
     */
    private function linkLibraryItem(IsoOverlayVersion $draft, string $type, $model, int $order, array $extraData = []): void
    {
        IsoOverlayItem::create([
            'version_id' => $draft->id,
            'type'       => $type,
            'key'        => $model->technical_key,
            'ref_source' => 'library',
            'ref_table'  => $model->getTable(),
            'ref_id'     => (string) $model->id,
            'label'      => $model->name,
            'sort_order' => $order,
            'data'       => !empty($extraData) ? $extraData : null,
        ]);
    }

    private function toText($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    // ────────────────────────────────────────────────────────────────
    // Validation Helpers
    // ────────────────────────────────────────────────────────────────

    private function load(string $module): array
    {
        $path = database_path("seeders/content/{$module}.php");
        if (!is_file($path)) {
            throw new \RuntimeException("Default content file not found: {$path}");
        }
        $data = require $path;
        if (!is_array($data)) {
            throw new \RuntimeException("Default content file must return an array: {$path}");
        }
        return $data;
    }

    private function validateRequired(array $row, array $keys): void
    {
        foreach ($keys as $k) {
            if (!array_key_exists($k, $row) || $row[$k] === null || $row[$k] === '') {
                throw new \InvalidArgumentException("Missing required field '{$k}' in row " . ($row['technical_key'] ?? '?'));
            }
        }
    }

    private function validateTechnicalKey(string $key): void
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,79}$/', $key)) {
            throw new \InvalidArgumentException(
                "Technical key '{$key}' must be 1-80 characters: letters, digits, dot, dash or underscore."
            );
        }
    }

    private function validateLocalizedName($name): void
    {
        if (!is_array($name) || trim((string) ($name['en'] ?? '')) === '') {
            throw new \InvalidArgumentException('Every library row needs an English (en) name.');
        }
    }

    private function validateStatesTransitions(array $row): void
    {
        $stateKeys = array_column($row['states'], 'key');
        if (count($stateKeys) !== count(array_unique($stateKeys))) {
            throw new \InvalidArgumentException("Duplicate state keys in workflow {$row['technical_key']}");
        }
        foreach ($row['transitions'] as $t) {
            if (!in_array($t['from'], $stateKeys, true) || !in_array($t['to'], $stateKeys, true)) {
                throw new \InvalidArgumentException(
                    "Transition references unknown state in workflow {$row['technical_key']}: {$t['from']} -> {$t['to']}"
                );
            }
        }
        $initials = array_filter($row['states'], fn($s) => ($s['kind'] ?? null) === 'initial');
        if (count($initials) !== 1) {
            throw new \InvalidArgumentException("Workflow {$row['technical_key']} must have exactly one initial state");
        }
    }

    private function validateChecklistSchema(array $schema): void
    {
        $seen = [];
        foreach ($schema as $section) {
            foreach ($section['questions'] ?? [] as $q) {
                if (isset($seen[$q['key']])) {
                    throw new \InvalidArgumentException("Duplicate question key: {$q['key']}");
                }
                $seen[$q['key']] = true;
            }
        }
    }

    private function validateFormSchema(array $schema): void
    {
        $seen = [];
        foreach ($schema as $section) {
            foreach ($section['fields'] ?? [] as $f) {
                if (isset($seen[$f['key']])) {
                    throw new \InvalidArgumentException("Duplicate field key: {$f['key']}");
                }
                $seen[$f['key']] = true;
            }
        }
    }

    private function validateFormula(string $formula): void
    {
        $allowed = '/^(?:[a-z_][a-z0-9_]*|\d+(?:\.\d+)?|\s|\+|\-|\*|\/|\(|\)|,)+$/i';
        if (!preg_match($allowed, $formula)) {
            throw new \InvalidArgumentException("Unsafe formula: {$formula}");
        }
        $banned = ['exec', 'eval', 'system', 'shell', 'passthru', 'assert'];
        foreach ($banned as $b) {
            if (stripos($formula, $b) !== false) {
                throw new \InvalidArgumentException("Forbidden token in formula: {$b}");
            }
        }
    }

    private function validateThresholds(array $t): void
    {
        foreach (['target', 'warning', 'critical', 'direction'] as $k) {
            if (!array_key_exists($k, $t)) {
                throw new \InvalidArgumentException("Threshold missing '{$k}'");
            }
        }
        if (!in_array($t['direction'], ['higher_is_better', 'lower_is_better'], true)) {
            throw new \InvalidArgumentException("Invalid direction: {$t['direction']}");
        }
    }

    private function validateRiskScales(array $row): void
    {
        foreach (['default_probability', 'default_impact'] as $k) {
            if (isset($row[$k]) && ($row[$k] < 1 || $row[$k] > 10)) {
                throw new \InvalidArgumentException("Risk {$k} must be between 1 and 10");
            }
        }

        if (isset($row['levels']) && is_array($row['levels'])) {
            $bounds = array_map(fn($level) => (float) ($level['max_score'] ?? 0), $row['levels']);
            $sorted = $bounds;
            sort($sorted);
            if ($sorted !== $bounds || count(array_unique($bounds)) !== count($bounds)) {
                throw new \InvalidArgumentException(
                    "Risk levels for {$row['technical_key']} must be in increasing max_score order without duplicates"
                );
            }
        }
    }
}
