<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IsoOverlay;
use App\Models\IsoOverlayAssignment;
use App\Models\IsoOverlayAuditLog;
use App\Models\IsoOverlayItem;
use App\Models\IsoOverlayVersion;
use App\Services\IsoOverlay\FlinkisoLookup;
use App\Services\IsoOverlay\OverlayException;
use App\Services\IsoOverlay\OverlayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * M8 admin screens. Every change goes through OverlayService, which enforces the
 * copy-on-write rules and writes the audit log. This controller only reads input,
 * checks permissions and shows results.
 */
class IsoOverlayController extends Controller
{
    private OverlayService $service;
    private FlinkisoLookup $lookup;

    public function __construct(OverlayService $service, FlinkisoLookup $lookup)
    {
        $this->service = $service;
        $this->lookup = $lookup;
    }

    // ---------------------------------------------------------------- overlays

    public function index()
    {
        $this->authorize('admin_iso_overlays_list');

        $overlays = IsoOverlay::with('currentVersion')->withCount('versions')->orderBy('code')->paginate(30);

        return view('admin.iso_overlays.index', [
            'pageTitle' => trans('iso_overlay.overlays'),
            'overlays' => $overlays,
            'standards' => $this->lookup->options('standards'),
        ]);
    }

    public function create()
    {
        $this->authorize('admin_iso_overlays_manage');

        return view('admin.iso_overlays.create', [
            'pageTitle' => trans('iso_overlay.new_overlay'),
            'standards' => $this->lookup->options('standards'),
            'locales' => config('iso_overlay.locales'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('admin_iso_overlays_manage');

        return $this->attempt(function () use ($request) {
            $overlay = $this->service->createOverlay([
                'code' => $request->input('code'),
                'name' => (array)$request->input('name', []),
                'description' => $this->filled((array)$request->input('description', [])),
                'flinkiso_standard_id' => $request->input('flinkiso_standard_id') ?: null,
                'framework_label' => $request->input('framework_label'),
                'edition' => $request->input('edition'),
                'change_note' => $request->input('change_note'),
            ], auth()->id());

            return redirect()->route('admin.iso_overlays.show', $overlay->id)->with('iso_status', trans('iso_overlay.saved'));
        });
    }

    public function show($id)
    {
        $this->authorize('admin_iso_overlays_list');

        $overlay = IsoOverlay::with(['versions', 'assignments'])->findOrFail($id);
        $integrity = [];
        foreach ($overlay->versions as $version) {
            if (!$version->isDraft()) {
                $integrity[$version->id] = $this->service->verifyIntegrity($version);
            }
        }

        return view('admin.iso_overlays.show', [
            'pageTitle' => $overlay->code,
            'overlay' => $overlay,
            'draft' => $overlay->draftVersion(),
            'integrity' => $integrity,
            'standards' => $this->lookup->options('standards'),
            'departments' => $this->lookup->options('departments'),
            'processes' => $this->lookup->options('processes'),
            'targets' => config('iso_overlay.assignment_targets'),
            'locales' => config('iso_overlay.locales'),
            'audit' => IsoOverlayAuditLog::where('overlay_id', $overlay->id)->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('admin_iso_overlays_manage');
        $overlay = IsoOverlay::findOrFail($id);

        return $this->attempt(function () use ($request, $overlay) {
            $this->service->updateOverlay($overlay, [
                'name' => (array)$request->input('name', []),
                'description' => $this->filled((array)$request->input('description', [])),
                'framework_label' => $request->input('framework_label'),
                'flinkiso_standard_id' => $request->input('flinkiso_standard_id') ?: null,
            ], auth()->id());

            return back()->with('iso_status', trans('iso_overlay.saved'));
        });
    }

    public function newVersion(Request $request, $id)
    {
        $this->authorize('admin_iso_overlays_manage');
        $overlay = IsoOverlay::findOrFail($id);
        $source = $request->filled('version_id') ? $this->versionOf($overlay, $request->input('version_id')) : null;

        return $this->attempt(function () use ($overlay, $source) {
            $draft = $this->service->newVersion($overlay, $source, auth()->id());

            return redirect()->route('admin.iso_overlays.version', $draft->id)->with('iso_status', trans('iso_overlay.saved'));
        });
    }

    public function cloneOverlay(Request $request, $id)
    {
        $this->authorize('admin_iso_overlays_manage');
        $overlay = IsoOverlay::findOrFail($id);
        $source = $this->versionOf($overlay, $request->input('version_id'));

        return $this->attempt(function () use ($request, $source) {
            $clone = $this->service->cloneOverlay($source, (string)$request->input('code'),
                (array)$request->input('name', []), auth()->id());

            return redirect()->route('admin.iso_overlays.show', $clone->id)->with('iso_status', trans('iso_overlay.saved'));
        });
    }

    public function activate(Request $request, $id)
    {
        $this->authorize('admin_iso_overlays_publish');
        $overlay = IsoOverlay::findOrFail($id);
        $version = $this->versionOf($overlay, $request->input('version_id'));

        return $this->attempt(fn() => $this->done($this->service->activate($overlay, $version, auth()->id())));
    }

    public function deactivate($id)
    {
        $this->authorize('admin_iso_overlays_publish');
        $overlay = IsoOverlay::findOrFail($id);

        return $this->attempt(fn() => $this->done($this->service->deactivate($overlay, auth()->id())));
    }

    public function rollback(Request $request, $id)
    {
        $this->authorize('admin_iso_overlays_publish');
        $overlay = IsoOverlay::findOrFail($id);
        $version = $this->versionOf($overlay, $request->input('version_id'));

        return $this->attempt(fn() => $this->done($this->service->rollback($overlay, $version, auth()->id())));
    }

    // ---------------------------------------------------------------- assignments

    public function assign(Request $request, $id)
    {
        $this->authorize('admin_iso_overlays_manage');
        $overlay = IsoOverlay::findOrFail($id);

        return $this->attempt(fn() => $this->done($this->service->assign($overlay,
            (string)$request->input('target_type'), (string)$request->input('target_ref'), auth()->id())));
    }

    public function toggleAssignment($assignmentId)
    {
        $this->authorize('admin_iso_overlays_manage');
        $assignment = IsoOverlayAssignment::findOrFail($assignmentId);

        return $this->attempt(fn() => $this->done($this->service->setAssignmentActive($assignment, !$assignment->is_active, auth()->id())));
    }

    // ---------------------------------------------------------------- versions

    public function version($versionId)
    {
        $this->authorize('admin_iso_overlays_list');
        $version = IsoOverlayVersion::with('overlay')->findOrFail($versionId);
        $items = $version->items()->get()->groupBy('type');

        return view('admin.iso_overlays.version', [
            'pageTitle' => $version->overlay->code . ' v' . $version->version_no,
            'version' => $version,
            'overlay' => $version->overlay,
            'items' => $items,
            'types' => config('iso_overlay.item_types'),
            'locales' => config('iso_overlay.locales'),
            'problems' => $version->isDraft() ? $this->service->validateVersion($version) : [],
            'intact' => $version->isDraft() ? null : $this->service->verifyIntegrity($version),
        ]);
    }

    public function updateVersion(Request $request, $versionId)
    {
        $this->authorize('admin_iso_overlays_manage');
        $version = IsoOverlayVersion::findOrFail($versionId);

        return $this->attempt(fn() => $this->done($this->service->updateDraft($version,
            $request->only(['edition', 'change_note']), auth()->id())));
    }

    public function publish($versionId)
    {
        $this->authorize('admin_iso_overlays_publish');
        $version = IsoOverlayVersion::findOrFail($versionId);

        return $this->attempt(fn() => $this->done($this->service->publish($version, auth()->id())));
    }

    public function archive($versionId)
    {
        $this->authorize('admin_iso_overlays_publish');
        $version = IsoOverlayVersion::findOrFail($versionId);

        return $this->attempt(fn() => $this->done($this->service->archive($version, auth()->id())));
    }

    public function deleteVersion($versionId)
    {
        $this->authorize('admin_iso_overlays_manage');
        $version = IsoOverlayVersion::findOrFail($versionId);
        $overlayId = $version->overlay_id;

        return $this->attempt(function () use ($version, $overlayId) {
            $this->service->deleteDraft($version, auth()->id());

            return redirect()->route('admin.iso_overlays.show', $overlayId)->with('iso_status', trans('iso_overlay.saved'));
        });
    }

    // ---------------------------------------------------------------- items

    public function createItem(Request $request, $versionId)
    {
        $this->authorize('admin_iso_overlays_manage');
        $version = IsoOverlayVersion::with('overlay')->findOrFail($versionId);
        $type = (string)$request->query('type', 'clause');

        if (!config("iso_overlay.item_types.{$type}")) {
            abort(404);
        }

        return view('admin.iso_overlays.item', [
            'pageTitle' => trans('iso_overlay.add_item'),
            'version' => $version,
            'item' => null,
            'type' => $type,
            'def' => config("iso_overlay.item_types.{$type}"),
            'options' => $this->optionsFor($type) ?? [],
            'locales' => config('iso_overlay.locales'),
        ]);
    }

    public function storeItem(Request $request, $versionId)
    {
        $this->authorize('admin_iso_overlays_manage');
        $version = IsoOverlayVersion::findOrFail($versionId);

        return $this->attempt(function () use ($request, $version) {
            $this->service->addItem($version, $this->itemInput($request, (string)$request->input('type')), auth()->id());

            return redirect()->route('admin.iso_overlays.version', $version->id)->with('iso_status', trans('iso_overlay.saved'));
        });
    }

    public function editItem($itemId)
    {
        $this->authorize('admin_iso_overlays_manage');
        $item = IsoOverlayItem::with('version.overlay')->findOrFail($itemId);

        return view('admin.iso_overlays.item', [
            'pageTitle' => trans('iso_overlay.edit_item'),
            'version' => $item->version,
            'item' => $item,
            'type' => $item->type,
            'def' => config("iso_overlay.item_types.{$item->type}"),
            'options' => $this->optionsFor($item->type) ?? [],
            'locales' => config('iso_overlay.locales'),
        ]);
    }

    public function updateItem(Request $request, $itemId)
    {
        $this->authorize('admin_iso_overlays_manage');
        $item = IsoOverlayItem::findOrFail($itemId);

        return $this->attempt(function () use ($request, $item) {
            $this->service->updateItem($item, $this->itemInput($request, $item->type), auth()->id());

            return redirect()->route('admin.iso_overlays.version', $item->version_id)->with('iso_status', trans('iso_overlay.saved'));
        });
    }

    public function deleteItem($itemId)
    {
        $this->authorize('admin_iso_overlays_manage');
        $item = IsoOverlayItem::findOrFail($itemId);
        $versionId = $item->version_id;

        return $this->attempt(function () use ($item, $versionId) {
            $this->service->removeItem($item, auth()->id());

            return redirect()->route('admin.iso_overlays.version', $versionId)->with('iso_status', trans('iso_overlay.saved'));
        });
    }

    // ---------------------------------------------------------------- helpers

    private function attempt(callable $action)
    {
        try {
            return $action();
        } catch (OverlayException $e) {
            return back()->withInput()->withErrors($e->getErrors(), 'iso');
        }
    }

    private function done($result)
    {
        return back()->with('iso_status', trans('iso_overlay.saved'));
    }

    private function versionOf(IsoOverlay $overlay, $versionId): IsoOverlayVersion
    {
        return IsoOverlayVersion::where('overlay_id', $overlay->id)->findOrFail((int)$versionId);
    }

    private function filled(array $values): ?array
    {
        $values = array_filter($values, fn($v) => is_string($v) && trim($v) !== '');

        return $values ?: null;
    }

private function optionsFor(string $type): array
{
    $def = config("iso_overlay.item_types.{$type}");
    $source = $def['ref']['source'] ?? null;

    // M8: External FlinkISO database
    if ($source === 'flinkiso') {
        return $this->lookup->options($def['ref']['table']);
    }

    // M9: Local Rocket LMS content library
    if ($source === 'library') {
        $table = $def['ref']['table'];
        
        try {
            $rows = \Illuminate\Support\Facades\DB::table($table)
                ->where('is_active', 1)
                ->orderBy('technical_key')
                ->get(['id', 'technical_key', 'name']);

            $out = [];
            foreach ($rows as $row) {
                $name = json_decode($row->name ?? 'null', true);
                $label = is_array($name) 
                    ? ($name['en'] ?? $row->technical_key) 
                    : $row->technical_key;
                $out[$row->id] = $row->technical_key . ' — ' . $label;
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    return [];
}

    /**
     * Options for a default content library table (M9). Only the known library
     * tables are accepted, and the localized English name is preferred.
     */
    private function libraryOptions(string $table): array
    {
        $allowed = [
            'iso_content_workflows',
            'iso_content_checklists',
            'iso_content_kpis',
            'iso_content_risks',
            'iso_content_forms',
        ];

        if (!in_array($table, $allowed, true)) {
            return [];
        }

        return DB::table($table)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('technical_key')
            ->get(['id', 'name', 'technical_key'])
            ->mapWithKeys(function ($row) {
                $name = json_decode((string) $row->name, true) ?: [];
                $label = $name['en'] ?? $row->technical_key;
                return [$row->id => $label . ' (' . $row->technical_key . ')'];
            })
            ->all();
    }

    /**
     * Builds the item attributes from the form. Structured data fields are generated
     * from the type's rules in config/iso_overlay.php, so this stays generic.
     */
    private function itemInput(Request $request, string $type): array
    {
        $rules = config("iso_overlay.item_types.{$type}.data", []);
        $data = [];

        foreach ($rules as $field => $rule) {
            if (str_contains($field, '*') || $field === 'levels') {
                continue;
            }
            $value = $request->input('data.' . $field);
            if (is_array($value) || $value === null || trim((string)$value) === '') {
                continue;
            }
            $value = trim((string)$value);
            if (str_contains($rule, 'integer') && is_numeric($value)) {
                $value = (int)$value;
            } elseif (str_contains($rule, 'numeric') && is_numeric($value)) {
                $value = $value + 0;
            }
            data_set($data, $field, $value);
        }

        if (array_key_exists('levels', $rules)) {
            $levels = [];
            foreach (preg_split('/\r\n|\r|\n/', (string)$request->input('levels_text', '')) as $line) {
                if (trim($line) === '') {
                    continue;
                }
                [$max, $label] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
                $levels[] = ['max_score' => is_numeric($max) ? $max + 0 : $max, 'label' => $label];
            }
            if ($levels) {
                $data['levels'] = $levels;
            }
        }

        return [
            'type' => $type,
            'key' => $request->input('key'),
            'parent_key' => $request->input('parent_key'),
            'ref_id' => $request->input('ref_id'),
            'label' => (array)$request->input('label', []),
            'sort_order' => (int)$request->input('sort_order', 0),
            'data' => $data,
        ];
    }
}
