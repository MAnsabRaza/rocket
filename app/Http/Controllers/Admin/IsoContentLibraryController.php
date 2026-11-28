<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IsoContentChecklist;
use App\Models\IsoContentForm;
use App\Models\IsoContentKpi;
use App\Models\IsoContentRisk;
use App\Models\IsoContentWorkflow;
use App\Services\IsoContent\DefaultContentImportService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class IsoContentLibraryController extends Controller
{
    /** @var array<string, class-string<Model>> */
    private const MODELS = [
        'workflows'  => IsoContentWorkflow::class,
        'checklists' => IsoContentChecklist::class,
        'kpis'       => IsoContentKpi::class,
        'risks'      => IsoContentRisk::class,
        'forms'      => IsoContentForm::class,
    ];

    public function __construct(
        private readonly DefaultContentImportService $importer,
    ) {}

    public function index()
    {
        $this->authorize('admin_iso_content_list');

        return view('admin.iso_content.index', [
            'pageTitle'  => trans('iso_content.library'),
            'workflows'  => IsoContentWorkflow::active()->orderBy('technical_key')->paginate(20, ['*'], 'w'),
            'checklists' => IsoContentChecklist::active()->orderBy('technical_key')->paginate(20, ['*'], 'c'),
            'kpis'       => IsoContentKpi::active()->orderBy('technical_key')->paginate(20, ['*'], 'k'),
            'risks'      => IsoContentRisk::active()->orderBy('technical_key')->paginate(20, ['*'], 'r'),
            'forms'      => IsoContentForm::active()->orderBy('technical_key')->paginate(20, ['*'], 'f'),
        ]);
    }

    public function reimport(Request $request)
    {
        $this->authorize('admin_iso_content_manage');

        $dryRun = (bool) $request->boolean('dry_run');
        $preserve = (bool) $request->boolean('preserve');

        try {
            $report = $this->importer->importAll(auth()->id(), $dryRun, $preserve);
            if ($dryRun) {
                return back()->with('iso_dry_run', $report->toArray());
            }
            session(['iso_last_import_report' => $report]);
            $msg = $preserve
                ? trans('iso_content.reimported_preserved')
                : trans('iso_content.reimported');
            return back()->with('iso_status', $msg)->with('iso_last_report', $report->toArray());
        } catch (\Throwable $e) {
            return back()->withErrors(['iso' => [$e->getMessage()]]);
        }
    }

    public function rollback(Request $request)
    {
        $this->authorize('admin_iso_content_manage');

        $report = session('iso_last_import_report');
        if (!($report instanceof \App\Services\IsoContent\ImportReport)) {
            return back()->withErrors(['iso' => [trans('iso_content.no_import_to_rollback')]]);
        }

        try {
            $this->importer->rollbackImport($report, auth()->id());
            session()->forget('iso_last_import_report');
            return back()->with('iso_status', trans('iso_content.rolled_back'));
        } catch (\Throwable $e) {
            return back()->withErrors(['iso' => [$e->getMessage()]]);
        }
    }

    public function showWorkflow(string $key)
    {
        $this->authorize('admin_iso_content_list');
        $item = IsoContentWorkflow::where('technical_key', $key)->firstOrFail();
        return view('admin.iso_content.workflow', ['pageTitle' => $item->name['en'] ?? $key, 'item' => $item, 'type' => 'workflows']);
    }

    public function showChecklist(string $key)
    {
        $this->authorize('admin_iso_content_list');
        $item = IsoContentChecklist::where('technical_key', $key)->firstOrFail();
        return view('admin.iso_content.checklist', ['pageTitle' => $item->name['en'] ?? $key, 'item' => $item, 'type' => 'checklists']);
    }

    public function showKpi(string $key)
    {
        $this->authorize('admin_iso_content_list');
        $item = IsoContentKpi::where('technical_key', $key)->firstOrFail();
        return view('admin.iso_content.kpi', ['pageTitle' => $item->name['en'] ?? $key, 'item' => $item, 'type' => 'kpis']);
    }

    public function showRisk(string $key)
    {
        $this->authorize('admin_iso_content_list');
        $item = IsoContentRisk::where('technical_key', $key)->firstOrFail();
        return view('admin.iso_content.risk', ['pageTitle' => $item->name['en'] ?? $key, 'item' => $item, 'type' => 'risks']);
    }

    public function showForm(string $key)
    {
        $this->authorize('admin_iso_content_list');
        $item = IsoContentForm::where('technical_key', $key)->firstOrFail();
        return view('admin.iso_content.form', ['pageTitle' => $item->name['en'] ?? $key, 'item' => $item, 'type' => 'forms']);
    }

    public function edit(string $type, string $key)
    {
        $this->authorize('admin_iso_content_manage');
        $item = $this->find($type, $key);

        return view('admin.iso_content.edit', [
            'pageTitle' => trans('iso_content.edit') . ' — ' . ($item->name['en'] ?? $key),
            'type'      => $type,
            'item'      => $item,
            'payload'   => $this->payloadFor($type, $item),
        ]);
    }

    public function update(Request $request, string $type, string $key)
    {
        $this->authorize('admin_iso_content_manage');
        $item = $this->find($type, $key);

        $request->validate([
            'name.en'       => 'required|string|max:255',
            'name.*'        => 'nullable|string|max:255',
            'description.*' => 'nullable|string|max:2000',
            'payload'       => 'required|string',
        ]);

        $payload = json_decode((string) $request->input('payload'), true);
        if (!is_array($payload)) {
            return back()->withInput()->withErrors(['payload' => [trans('iso_content.invalid_json')]]);
        }

        $row = array_merge($payload, [
            'technical_key' => $item->technical_key,
            'name'          => $this->localizedInput($request->input('name', [])),
            'description'   => $this->localizedInput($request->input('description', [])),
        ]);

        try {
            DB::transaction(function () use ($item, $type, $row) {
                $this->pushHistory($item);
                $this->importer->importRow($type, $row, auth()->id());
            });
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['iso' => [$e->getMessage()]]);
        }

        return redirect()
            ->route($this->showRoute($type), $item->technical_key)
            ->with('iso_status', trans('iso_content.updated'));
    }

    public function clone(string $type, string $key)
    {
        $this->authorize('admin_iso_content_manage');
        $item = $this->find($type, $key);

        $row = Arr::except($item->toArray(), [
            'id', 'technical_key', 'created_at', 'updated_at', 'deleted_at',
            'created_by', 'updated_by', 'version_history',
        ]);
        $row['technical_key'] = $this->uniqueKey($type, $item->technical_key);
        $row['version']       = 1;
        $row['is_active']     = true;

        try {
            $newKey = $this->importer->importRow($type, $row, auth()->id());
        } catch (\Throwable $e) {
            return back()->withErrors(['iso' => [$e->getMessage()]]);
        }

        return redirect()
            ->route($this->showRoute($type), $newKey)
            ->with('iso_status', trans('iso_content.cloned'));
    }

    public function newVersion(string $type, string $key)
    {
        $this->authorize('admin_iso_content_manage');
        $item = $this->find($type, $key);

        $this->pushHistory($item);
        $item->increment('version');

        return back()->with('iso_status', trans('iso_content.versioned'));
    }

    public function toggle(string $type, string $key)
    {
        $this->authorize('admin_iso_content_manage');
        $item = $this->find($type, $key);

        $item->forceFill([
            'is_active'  => !$item->is_active,
            'updated_by' => auth()->id(),
        ])->save();

        return back()->with('iso_status', trans('iso_content.toggled'));
    }

    private function find(string $type, string $key): Model
    {
        $class = self::MODELS[$type] ?? abort(404);
        return $class::where('technical_key', $key)->firstOrFail();
    }

    private function showRoute(string $type): string
    {
        return match ($type) {
            'workflows'  => 'admin.iso_content.workflows.show',
            'checklists' => 'admin.iso_content.checklists.show',
            'kpis'       => 'admin.iso_content.kpis.show',
            'risks'      => 'admin.iso_content.risks.show',
            'forms'      => 'admin.iso_content.forms.show',
        };
    }

    /**
     * Only the module-specific structured body is editable as JSON. Identity,
     * version and localised name/description are handled by dedicated fields.
     */
    private function payloadFor(string $type, Model $item): array
    {
        $keys = match ($type) {
            'workflows'  => ['category', 'states', 'transitions', 'roles', 'settings'],
            'checklists' => ['standard_ref', 'schema', 'scoring'],
            'kpis'       => ['category', 'clause_ref', 'formula', 'unit', 'frequency', 'calculation', 'thresholds', 'roles', 'source'],
            'risks'      => ['category', 'causes', 'consequences', 'default_probability', 'default_impact', 'probability_scale', 'impact_scale', 'scoring_rule', 'levels', 'residual', 'mitigations', 'controls'],
            'forms'      => ['category', 'schema', 'approval'],
        };

        return Arr::only($item->toArray(), $keys);
    }

    private function localizedInput($values): array
    {
        $clean = [];
        foreach ((array) $values as $locale => $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $clean[$locale] = $value;
            }
        }
        return $clean;
    }

    private function uniqueKey(string $type, string $base): string
    {
        $class = self::MODELS[$type];
        $key = $base . '_copy';
        $n = 2;
        while ($class::withTrashed()->where('technical_key', $key)->exists()) {
            $key = $base . '_copy' . $n++;
        }
        return $key;
    }

    /**
     * Append a snapshot of the current record to version_history before it is
     * changed, so default content keeps an audit trail across edits and versions.
     */
    private function pushHistory(Model $item): void
    {
        $history = $item->version_history ?? [];
        $history[] = [
            'version'  => $item->version,
            'saved_at' => now()->toIso8601String(),
            'saved_by' => auth()->id(),
            'data'     => Arr::except($item->toArray(), ['version_history', 'created_at', 'updated_at', 'deleted_at']),
        ];

        $item->forceFill(['version_history' => $history])->saveQuietly();
    }
}
