<?php

namespace App\Services\IsoOverlay;

use App\Models\IsoOverlay;
use App\Models\IsoOverlayAssignment;
use App\Models\IsoOverlayAuditLog;
use App\Models\IsoOverlayItem;
use App\Models\IsoOverlayVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * M8 ISO Overlay Template Engine. Every operation on overlays goes through this
 * service, so the admin screens and the API share one set of rules, and every
 * change is written to the audit log in the same transaction as the change.
 *
 * Overlay details (name, description, framework label, FlinkISO standard) are
 * recorded in each version (meta) and covered by its content hash, so they are
 * as immutable as the items once the version is published. They are changed only
 * in a draft; the overlay record shows the details of one version (detailsVersion).
 */
class OverlayService
{
    public const FLINKISO_CONNECTION = 'flinkiso';

    // ---------------------------------------------------------------- overlays

    public function createOverlay(array $attrs, ?int $userId = null): IsoOverlay
    {
        $errors = [];
        $code = trim((string)($attrs['code'] ?? ''));

        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,63}$/', $code)) {
            $errors[] = 'Code must be 2 to 64 characters: letters, digits, dot, dash or underscore.';
        } elseif (IsoOverlay::where('code', $code)->exists()) {
            $errors[] = "Code {$code} is already in use.";
        }

        // Blank optional languages arrive as null (ConvertEmptyStringsToNull); drop them before checking.
        $errors = array_merge($errors, $this->labelErrors($this->cleanLabel($attrs['name'] ?? []), 'Name'));

        if (!empty($attrs['flinkiso_standard_id']) && !$this->flinkisoRecordExists('standards', $attrs['flinkiso_standard_id'])) {
            $errors[] = 'The selected FlinkISO standard does not exist.';
        }
        $errors = array_merge($errors, $this->frameworkLabelErrors($attrs['framework_label'] ?? null));

        if ($errors) {
            throw new OverlayException('Overlay not created.', $errors);
        }

        $details = IsoOverlayVersion::canonicalMeta([
            'name' => $attrs['name'] ?? [],
            'description' => $attrs['description'] ?? null,
            'flinkiso_standard_id' => $attrs['flinkiso_standard_id'] ?? null,
            'framework_label' => $attrs['framework_label'] ?? null,
        ]);

        return DB::transaction(function () use ($attrs, $code, $details, $userId) {
            $overlay = IsoOverlay::create($details + [
                'code' => $code,
                'is_active' => false,
                'created_by' => $userId,
            ]);

            $version = IsoOverlayVersion::create([
                'overlay_id' => $overlay->id,
                'version_no' => 1,
                'edition' => $attrs['edition'] ?? null,
                'meta' => $details,
                'status' => IsoOverlayVersion::DRAFT,
                'change_note' => $attrs['change_note'] ?? null,
                'created_by' => $userId,
            ]);

            $this->audit('overlay_created', $overlay, $version, $userId, null, null, ['code' => $code]);

            return $overlay->fresh();
        });
    }

    /**
     * Change the overlay details. They belong to a version, so the change is made in
     * the overlay's draft version; with no draft, it is refused. Published and
     * archived versions keep the details they were published with.
     */
    public function updateOverlay(IsoOverlay $overlay, array $attrs, ?int $userId = null): IsoOverlay
    {
        $draft = $overlay->draftVersion();
        if (!$draft) {
            throw new OverlayException('Overlay details not changed.', [
                'The overlay details are part of each published version and cannot be changed after publication. '
                . 'Create a new version, change the details in that draft, and publish it.',
            ]);
        }

        $errors = [];
        $changes = [];

        if (array_key_exists('name', $attrs)) {
            $errors = array_merge($errors, $this->labelErrors($this->cleanLabel($attrs['name'] ?? []), 'Name'));
            $changes['name'] = $this->cleanLabel($attrs['name'] ?? []);
        }
        if (array_key_exists('description', $attrs)) {
            $changes['description'] = $attrs['description'] === null ? null : $this->cleanLabel($attrs['description']);
        }
        if (array_key_exists('framework_label', $attrs)) {
            $errors = array_merge($errors, $this->frameworkLabelErrors($attrs['framework_label']));
            $changes['framework_label'] = $attrs['framework_label'];
        }
        if (array_key_exists('flinkiso_standard_id', $attrs)) {
            if (!empty($attrs['flinkiso_standard_id']) && !$this->flinkisoRecordExists('standards', $attrs['flinkiso_standard_id'])) {
                $errors[] = 'The selected FlinkISO standard does not exist.';
            }
            $changes['flinkiso_standard_id'] = $attrs['flinkiso_standard_id'] ?: null;
        }

        if ($errors) {
            throw new OverlayException('Overlay not updated.', $errors);
        }

        return DB::transaction(function () use ($overlay, $draft, $changes, $userId) {
            $draft->fill(['meta' => IsoOverlayVersion::canonicalMeta(array_merge($this->detailsOf($draft), $changes))])->save();
            $this->syncOverlayDetails($overlay);
            $this->audit('details_updated', $overlay, $draft, $userId, null, null,
                ['version_no' => (int)$draft->version_no, 'fields' => array_keys($changes)]);

            return $overlay->fresh();
        });
    }

    // ---------------------------------------------------------------- drafts

    public function updateDraft(IsoOverlayVersion $version, array $attrs, ?int $userId = null): IsoOverlayVersion
    {
        $this->assertDraft($version);

        return DB::transaction(function () use ($version, $attrs, $userId) {
            $version->fill(array_intersect_key($attrs, array_flip(['edition', 'change_note'])))->save();
            $this->audit('draft_updated', $version->overlay, $version, $userId);

            return $version->fresh();
        });
    }

    public function addItem(IsoOverlayVersion $version, array $attrs, ?int $userId = null): IsoOverlayItem
    {
        $this->assertDraft($version);
        $row = $this->normaliseItem($attrs);
        $errors = $this->itemErrors($row);

        if (IsoOverlayItem::where('version_id', $version->id)->where('type', $row['type'])->where('key', $row['key'])->exists()) {
            $errors[] = "An item of type {$row['type']} with key {$row['key']} already exists in this version.";
        }

        if ($errors) {
            throw new OverlayException('Item not added.', $errors);
        }

        return DB::transaction(function () use ($version, $row, $userId) {
            $item = IsoOverlayItem::create($row + ['version_id' => $version->id]);
            $this->audit('item_added', $version->overlay, $version, $userId, null, null, ['type' => $row['type'], 'key' => $row['key']]);

            return $item;
        });
    }

    public function updateItem(IsoOverlayItem $item, array $attrs, ?int $userId = null): IsoOverlayItem
    {
        $version = $item->version;
        $this->assertDraft($version);

        $row = $this->normaliseItem(array_merge($item->only(['type', 'key', 'parent_key', 'ref_id', 'label', 'sort_order', 'data']), $attrs));
        $errors = $this->itemErrors($row);

        $clash = IsoOverlayItem::where('version_id', $version->id)->where('type', $row['type'])->where('key', $row['key'])
            ->where('id', '!=', $item->id)->exists();
        if ($clash) {
            $errors[] = "An item of type {$row['type']} with key {$row['key']} already exists in this version.";
        }

        if ($errors) {
            throw new OverlayException('Item not updated.', $errors);
        }

        return DB::transaction(function () use ($item, $version, $row, $userId) {
            $item->fill($row)->save();
            $this->audit('item_updated', $version->overlay, $version, $userId, null, null, ['type' => $row['type'], 'key' => $row['key']]);

            return $item->fresh();
        });
    }

    public function removeItem(IsoOverlayItem $item, ?int $userId = null): void
    {
        $version = $item->version;
        $this->assertDraft($version);

        DB::transaction(function () use ($item, $version, $userId) {
            $meta = ['type' => $item->type, 'key' => $item->key];
            $item->delete();
            $this->audit('item_removed', $version->overlay, $version, $userId, null, null, $meta);
        });
    }

    public function deleteDraft(IsoOverlayVersion $version, ?int $userId = null): void
    {
        $this->assertDraft($version);
        $overlay = $version->overlay;

        if ($overlay->versions()->count() === 1) {
            throw new OverlayException('The only version of an overlay cannot be deleted.');
        }

        DB::transaction(function () use ($version, $overlay, $userId) {
            $versionNo = $version->version_no;
            foreach ($version->items()->get() as $item) {
                $item->delete();
            }
            $version->delete();
            $this->audit('draft_deleted', $overlay, null, $userId, null, null, ['version_no' => $versionNo]);
        });
    }

    // ---------------------------------------------------------------- lifecycle

    /**
     * Every problem that would stop this version being published.
     */
    public function validateVersion(IsoOverlayVersion $version): array
    {
        $errors = [];
        $items = $version->items()->get();

        if ($items->isEmpty()) {
            $errors[] = 'A version needs at least one item before it can be published.';
        }
        if (!$version->hasRecordedDetails()) {
            $errors[] = 'The version has no overlay details recorded. Save the overlay details for this draft first.';
        } else {
            $errors = array_merge($errors, $this->labelErrors($version->meta['name'] ?? null, 'Overlay name'));
        }

        $keys = [];
        foreach ($items as $item) {
            $keys[$item->key] = true;
        }

        foreach ($items as $item) {
            $row = $item->only(['type', 'key', 'parent_key', 'ref_source', 'ref_table', 'ref_id', 'label', 'sort_order', 'data']);
            foreach ($this->itemErrors($row) as $error) {
                $errors[] = "{$item->type}/{$item->key}: {$error}";
            }
            if ($item->parent_key !== null && $item->parent_key !== '' && !isset($keys[$item->parent_key])) {
                $errors[] = "{$item->type}/{$item->key}: parent key {$item->parent_key} is not in this version.";
            }
        }

        return $errors;
    }

    public function publish(IsoOverlayVersion $version, ?int $userId = null): IsoOverlayVersion
    {
        $this->assertDraft($version);
        $errors = $this->validateVersion($version);

        if ($errors) {
            throw new OverlayException('Version not published.', $errors);
        }

        return DB::transaction(function () use ($version, $userId) {
            $hash = $this->contentHash($version, IsoOverlayVersion::HASH_SCHEME);
            $version->fill([
                'status' => IsoOverlayVersion::PUBLISHED,
                'content_hash' => $hash,
                'hash_scheme' => IsoOverlayVersion::HASH_SCHEME,
                'published_at' => now(),
                'published_by' => $userId,
            ])->save();

            $this->syncOverlayDetails($version->overlay);
            $this->audit('published', $version->overlay, $version, $userId, null, $hash, ['hash_scheme' => IsoOverlayVersion::HASH_SCHEME]);

            return $version->fresh();
        });
    }

    /**
     * Copy a published or archived version into a new draft of the same overlay.
     */
    public function newVersion(IsoOverlay $overlay, ?IsoOverlayVersion $source = null, ?int $userId = null): IsoOverlayVersion
    {
        $source = $source ?? $overlay->versions()->where('status', '!=', IsoOverlayVersion::DRAFT)->orderByDesc('version_no')->first();

        if (!$source || (int)$source->overlay_id !== (int)$overlay->id) {
            throw new OverlayException('A published version of this overlay is needed to start a new version.');
        }
        if ($source->isDraft()) {
            throw new OverlayException('A new version is copied from a published or archived version, not from a draft.');
        }
        if ($overlay->versions()->where('status', IsoOverlayVersion::DRAFT)->exists()) {
            throw new OverlayException('This overlay already has a draft version. Publish or delete it first.');
        }

        return DB::transaction(function () use ($overlay, $source, $userId) {
            $next = (int)$overlay->versions()->max('version_no') + 1;
            $draft = IsoOverlayVersion::create([
                'overlay_id' => $overlay->id,
                'version_no' => $next,
                'edition' => $source->edition,
                'meta' => IsoOverlayVersion::canonicalMeta($this->detailsOf($source)),
                'status' => IsoOverlayVersion::DRAFT,
                'parent_version_id' => $source->id,
                'created_by' => $userId,
            ]);
            $this->copyItems($source, $draft);
            $this->audit('version_created', $overlay, $draft, $userId, $source->content_hash, null, ['from_version_no' => $source->version_no]);

            return $draft->fresh();
        });
    }

    /**
     * Copy a published or archived version into a new overlay as its version 1 draft.
     */
    public function cloneOverlay(IsoOverlayVersion $source, string $newCode, array $name, ?int $userId = null): IsoOverlay
    {
        if ($source->isDraft()) {
            throw new OverlayException('Only a published or archived version can be cloned.');
        }

        $origin = $source->overlay;

        $details = $this->detailsOf($source);

        return DB::transaction(function () use ($source, $origin, $details, $newCode, $name, $userId) {
            $clone = $this->createOverlay([
                'code' => $newCode,
                'name' => $name,
                'description' => $details['description'],
                'flinkiso_standard_id' => $details['flinkiso_standard_id'],
                'framework_label' => $details['framework_label'],
                'edition' => $source->edition,
            ], $userId);

            $draft = $clone->versions()->first();
            $draft->fill(['parent_version_id' => $source->id])->save();
            $this->copyItems($source, $draft);
            $this->audit('cloned', $clone, $draft, $userId, $source->content_hash, null, ['from_overlay' => $origin->code, 'from_version_no' => $source->version_no]);

            return $clone->fresh();
        });
    }

    public function activate(IsoOverlay $overlay, IsoOverlayVersion $version, ?int $userId = null): IsoOverlay
    {
        $this->assertPublishedOf($overlay, $version);

        return DB::transaction(function () use ($overlay, $version, $userId) {
            $before = $overlay->current_version_id;
            $overlay->fill(['current_version_id' => $version->id, 'is_active' => true])->save();
            $this->syncOverlayDetails($overlay);
            $this->audit('activated', $overlay, $version, $userId, null, $version->content_hash, ['previous_version_id' => $before]);

            return $overlay->fresh();
        });
    }

    public function deactivate(IsoOverlay $overlay, ?int $userId = null): IsoOverlay
    {
        return DB::transaction(function () use ($overlay, $userId) {
            $overlay->fill(['is_active' => false])->save();
            $this->audit('deactivated', $overlay, $overlay->currentVersion, $userId);

            return $overlay->fresh();
        });
    }

    /**
     * Point the overlay back at an earlier published version. No version is changed.
     */
    public function rollback(IsoOverlay $overlay, IsoOverlayVersion $target, ?int $userId = null): IsoOverlay
    {
        $this->assertPublishedOf($overlay, $target);
        $current = $overlay->currentVersion;

        if (!$current) {
            throw new OverlayException('The overlay has no current version to roll back from.');
        }
        if ((int)$target->version_no >= (int)$current->version_no) {
            throw new OverlayException('Rollback targets an earlier version than the current one.');
        }

        return DB::transaction(function () use ($overlay, $target, $current, $userId) {
            $overlay->fill(['current_version_id' => $target->id])->save();
            $this->syncOverlayDetails($overlay);
            $this->audit('rolled_back', $overlay, $target, $userId, $current->content_hash, $target->content_hash,
                ['from_version_no' => $current->version_no, 'to_version_no' => $target->version_no]);

            return $overlay->fresh();
        });
    }

    public function archive(IsoOverlayVersion $version, ?int $userId = null): IsoOverlayVersion
    {
        if ($version->status !== IsoOverlayVersion::PUBLISHED) {
            throw new OverlayException('Only a published version can be archived.');
        }
        if ((int)$version->overlay->current_version_id === (int)$version->id) {
            throw new OverlayException('The current version cannot be archived. Activate or roll back to another version first.');
        }

        return DB::transaction(function () use ($version, $userId) {
            $version->fill(['status' => IsoOverlayVersion::ARCHIVED, 'archived_at' => now(), 'archived_by' => $userId])->save();
            $this->audit('archived', $version->overlay, $version, $userId, $version->content_hash, $version->content_hash);

            return $version->fresh();
        });
    }

    // ---------------------------------------------------------------- assignments

    public function assign(IsoOverlay $overlay, string $targetType, string $targetRef, ?int $userId = null): IsoOverlayAssignment
    {
        $errors = [];
        $targetRef = trim($targetRef);

        if (!in_array($targetType, config('iso_overlay.assignment_targets', []), true)) {
            $errors[] = "Assignment target type {$targetType} is not supported.";
        }
        if ($targetRef === '' || strlen($targetRef) > 64) {
            $errors[] = 'Assignment target reference is required (up to 64 characters).';
        }
        $table = ['department' => 'departments', 'process' => 'processes'][$targetType] ?? null;
        if (!$errors && $table && !$this->flinkisoRecordExists($table, $targetRef)) {
            $errors[] = "The selected FlinkISO {$targetType} does not exist.";
        }
        if (!$errors && IsoOverlayAssignment::where(['overlay_id' => $overlay->id, 'target_type' => $targetType, 'target_ref' => $targetRef])->exists()) {
            $errors[] = 'This overlay is already assigned to that target.';
        }

        if ($errors) {
            throw new OverlayException('Assignment not saved.', $errors);
        }

        return DB::transaction(function () use ($overlay, $targetType, $targetRef, $userId) {
            $assignment = IsoOverlayAssignment::create([
                'overlay_id' => $overlay->id,
                'target_type' => $targetType,
                'target_ref' => $targetRef,
                'is_active' => true,
                'assigned_by' => $userId,
            ]);
            $this->audit('assigned', $overlay, null, $userId, null, null, ['target_type' => $targetType, 'target_ref' => $targetRef]);

            return $assignment;
        });
    }

    public function setAssignmentActive(IsoOverlayAssignment $assignment, bool $active, ?int $userId = null): IsoOverlayAssignment
    {
        return DB::transaction(function () use ($assignment, $active, $userId) {
            $assignment->fill(['is_active' => $active])->save();
            $this->audit($active ? 'assignment_enabled' : 'assignment_disabled', $assignment->overlay, null, $userId, null, null,
                ['target_type' => $assignment->target_type, 'target_ref' => $assignment->target_ref]);

            return $assignment->fresh();
        });
    }

    // ---------------------------------------------------------------- reading

    /**
     * Canonical, ordered content of a version. The content hash is taken over this.
     * Scheme 2 (current) covers the items and the overlay details recorded in the
     * version. Scheme 1 covers the items only; it is used solely to check versions
     * published before details were recorded.
     */
    public function snapshot(IsoOverlayVersion $version, ?int $scheme = null): array
    {
        $scheme = $scheme ?? $version->hashScheme();

        $items = IsoOverlayItem::where('version_id', $version->id)->orderBy('type')->orderBy('key')->get()
            ->map(function (IsoOverlayItem $item) {
                return $this->sortKeys([
                    'type' => $item->type,
                    'key' => $item->key,
                    'parent_key' => $item->parent_key,
                    'ref_source' => $item->ref_source,
                    'ref_table' => $item->ref_table,
                    'ref_id' => $item->ref_id,
                    'label' => $item->label,
                    'sort_order' => (int)$item->sort_order,
                    'data' => $item->data,
                ]);
            })->values()->all();

        $snapshot = [
            'overlay_code' => $version->overlay->code,
            'version_no' => (int)$version->version_no,
            'edition' => $version->edition,
            'items' => $items,
        ];

        if ($scheme >= 2) {
            $snapshot = ['details' => $version->hasRecordedDetails() ? IsoOverlayVersion::canonicalMeta($version->meta) : null] + $snapshot;
        }

        return $snapshot;
    }

    public function contentHash(IsoOverlayVersion $version, ?int $scheme = null): string
    {
        return hash('sha256', json_encode($this->snapshot($version, $scheme), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * True when a published or archived version still matches the hash taken at publication.
     */
    public function verifyIntegrity(IsoOverlayVersion $version): bool
    {
        if ($version->isDraft() || !$version->content_hash) {
            return false;
        }

        return hash_equals($version->content_hash, $this->contentHash($version));
    }

    // ---------------------------------------------------------------- internals

    private function assertDraft(?IsoOverlayVersion $version): void
    {
        if (!$version || !$version->isDraft()) {
            throw new OverlayException('Only a draft version can be changed. Create a new version to make changes.');
        }
    }

    private function assertPublishedOf(IsoOverlay $overlay, IsoOverlayVersion $version): void
    {
        if ((int)$version->overlay_id !== (int)$overlay->id) {
            throw new OverlayException('The version does not belong to this overlay.');
        }
        if ($version->status !== IsoOverlayVersion::PUBLISHED) {
            throw new OverlayException('Only a published version can be made current.');
        }
    }

    /**
     * The details recorded in a version. A version published before details were
     * recorded has none; its overlay record's current values are used only as the
     * starting point for a new draft, never written back to that version.
     */
    private function detailsOf(IsoOverlayVersion $version): array
    {
        $source = $version->hasRecordedDetails() ? $version->meta : $version->overlay->only(IsoOverlayVersion::DETAIL_FIELDS);

        return IsoOverlayVersion::canonicalMeta($source);
    }

    /**
     * Bring the overlay record's display copy of the details in line with the
     * version it follows. Versions without recorded details leave it unchanged.
     */
    private function syncOverlayDetails(IsoOverlay $overlay): void
    {
        $overlay->refresh();
        $source = $overlay->detailsVersion();

        if ($source && $source->hasRecordedDetails()) {
            $overlay->fill(IsoOverlayVersion::canonicalMeta($source->meta))->save();
        }
    }

    private function frameworkLabelErrors($value): array
    {
        return $value !== null && mb_strlen(trim((string)$value)) > 120 ? ['Framework label must be up to 120 characters.'] : [];
    }

    private function copyItems(IsoOverlayVersion $from, IsoOverlayVersion $to): void
    {
        foreach (IsoOverlayItem::where('version_id', $from->id)->orderBy('id')->get() as $item) {
            IsoOverlayItem::create($item->only(['type', 'key', 'parent_key', 'ref_source', 'ref_table', 'ref_id', 'label', 'sort_order', 'data'])
                + ['version_id' => $to->id]);
        }
    }

    private function normaliseItem(array $attrs): array
    {
        $type = (string)($attrs['type'] ?? '');
        $def = config("iso_overlay.item_types.{$type}");
        $source = $def['ref']['source'] ?? null;
        $refId = isset($attrs['ref_id']) && $attrs['ref_id'] !== '' ? trim((string)$attrs['ref_id']) : null;

        return [
            'type' => $type,
            'key' => trim((string)($attrs['key'] ?? '')),
            'parent_key' => isset($attrs['parent_key']) && $attrs['parent_key'] !== '' ? trim((string)$attrs['parent_key']) : null,
            'ref_source' => $source === 'flinkiso' ? 'flinkiso' : ($refId !== null ? 'external' : null),
            'ref_table' => $source === 'flinkiso' ? $def['ref']['table'] : null,
            'ref_id' => $source === 'none' ? null : $refId,
            'label' => is_array($attrs['label'] ?? null) ? $this->cleanLabel($attrs['label']) : [],
            'sort_order' => (int)($attrs['sort_order'] ?? 0),
            'data' => isset($attrs['data']) && is_array($attrs['data']) && $attrs['data'] !== [] ? $attrs['data'] : null,
        ];
    }

    private function itemErrors(array $row): array
    {
        $errors = [];
        $def = config("iso_overlay.item_types.{$row['type']}");

        if (!$def) {
            return ["Item type {$row['type']} is not a configured item type."];
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/', $row['key'])) {
            $errors[] = 'Key must be 1 to 100 characters: letters, digits, dot, dash or underscore.';
        }

        $errors = array_merge($errors, $this->labelErrors($row['label'], 'Label'));

        if (($def['ref']['source'] ?? null) === 'flinkiso') {
            if ($row['ref_id'] === null) {
                $errors[] = "A FlinkISO {$def['ref']['table']} reference is required.";
            } elseif (!$this->flinkisoRecordExists($def['ref']['table'], $row['ref_id'])) {
                $errors[] = "FlinkISO {$def['ref']['table']} record {$row['ref_id']} does not exist.";
            }
        }

        if (!empty($def['data'])) {
            $validator = Validator::make(['data' => $row['data'] ?? []], $this->prefixRules($def['data']));
            foreach ($validator->errors()->all() as $message) {
                $errors[] = $message;
            }
        }

        if (!$errors && $row['type'] === 'kpi') {
            $errors = array_merge($errors, $this->kpiThresholdErrors($row['data']));
        }
        if (!$errors && $row['type'] === 'risk') {
            $errors = array_merge($errors, $this->riskLevelErrors($row['data']));
        }

        return $errors;
    }

    private function kpiThresholdErrors(array $data): array
    {
        $warning = (float)$data['thresholds']['warning'];
        $critical = (float)$data['thresholds']['critical'];
        $target = (float)$data['target'];

        if ($data['direction'] === 'higher_is_better' && !($target >= $warning && $warning >= $critical)) {
            return ['For higher_is_better, target must be at least warning, and warning at least critical.'];
        }
        if ($data['direction'] === 'lower_is_better' && !($target <= $warning && $warning <= $critical)) {
            return ['For lower_is_better, target must be at most warning, and warning at most critical.'];
        }

        return [];
    }

    private function riskLevelErrors(array $data): array
    {
        $p = (int)$data['probability_scale'];
        $i = (int)$data['impact_scale'];
        $maxScore = $data['scoring_rule'] === 'multiply' ? $p * $i : $p + $i;
        $bounds = array_map(fn($level) => (float)$level['max_score'], $data['levels']);

        $sorted = $bounds;
        sort($sorted);
        if ($sorted !== $bounds || count(array_unique($bounds)) !== count($bounds)) {
            return ['Risk levels must be listed in increasing max_score order without duplicates.'];
        }
        if (end($bounds) < $maxScore) {
            return ["The highest risk level must cover the maximum score of {$maxScore}."];
        }

        return [];
    }

    private function prefixRules(array $rules): array
    {
        $prefixed = [];
        foreach ($rules as $field => $rule) {
            $prefixed["data.{$field}"] = $rule;
        }

        return $prefixed;
    }

    private function labelErrors($label, string $what): array
    {
        if (!is_array($label) || !isset($label['en']) || trim((string)$label['en']) === '') {
            return ["{$what} needs an English (en) value."];
        }

        foreach ($label as $locale => $value) {
            if (!in_array($locale, config('iso_overlay.locales', ['en']), true)) {
                return ["{$what} has an unsupported locale: {$locale}."];
            }
            if (!is_string($value) || mb_strlen($value) > 500) {
                return ["{$what} values must be text of up to 500 characters."];
            }
        }

        return [];
    }

    private function cleanLabel($label): array
    {
        $clean = [];
        foreach ((array)$label as $locale => $value) {
            if (is_string($value) && trim($value) !== '') {
                $clean[$locale] = trim($value);
            }
        }
        ksort($clean);

        return $clean;
    }

    private function flinkisoRecordExists(string $table, string $id): bool
    {
        return DB::connection(self::FLINKISO_CONNECTION)->table($table)->where('id', $id)->exists();
    }

    private function sortKeys($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value);
        }

        return array_map(fn($v) => $this->sortKeys($v), $value);
    }

    private function audit(string $action, ?IsoOverlay $overlay, ?IsoOverlayVersion $version, ?int $userId,
                           ?string $beforeHash = null, ?string $afterHash = null, array $payload = []): void
    {
        IsoOverlayAuditLog::create([
            'overlay_id' => $overlay->id ?? null,
            'version_id' => $version->id ?? null,
            'action' => $action,
            'user_id' => $userId,
            'before_hash' => $beforeHash,
            'after_hash' => $afterHash,
            'payload' => $payload ?: null,
        ]);
    }
}
