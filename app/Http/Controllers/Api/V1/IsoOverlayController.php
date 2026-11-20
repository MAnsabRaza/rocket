<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IsoOverlay;
use App\Models\IsoOverlayVersion;
use App\Services\IsoOverlay\OverlayService;
use Illuminate\Http\JsonResponse;

/**
 * M8 read-only API for ISO overlays (API-first principle). Drafts are never
 * exposed; only published and archived versions, which are immutable.
 * Labels are returned with every locale so each consumer picks its own.
 * A version's content includes the overlay details recorded in it ("details",
 * fingerprint scheme 2); the overlay summary shows the current version's details.
 */
class IsoOverlayController extends Controller
{
    private OverlayService $service;

    public function __construct(OverlayService $service)
    {
        $this->service = $service;
    }

    public function index(): JsonResponse
    {
        $overlays = IsoOverlay::with('currentVersion')->orderBy('code')->get()->map(fn(IsoOverlay $o) => $this->overlaySummary($o));

        return response()->json(['data' => $overlays->values()]);
    }

    public function show(string $code): JsonResponse
    {
        $overlay = IsoOverlay::with('currentVersion')->where('code', $code)->first();
        if (!$overlay) {
            return $this->notFound('Overlay not found.');
        }

        $versions = $overlay->versions()->where('status', '!=', IsoOverlayVersion::DRAFT)->get()->map(fn(IsoOverlayVersion $v) => [
            'version_no' => (int)$v->version_no,
            'edition' => $v->edition,
            'status' => $v->status,
            'content_hash' => $v->content_hash,
            'hash_scheme' => $v->hashScheme(),
            'published_at' => optional($v->published_at)->toIso8601String(),
            'archived_at' => optional($v->archived_at)->toIso8601String(),
            'is_current' => (int)$overlay->current_version_id === (int)$v->id,
        ]);

        $assignments = $overlay->assignments()->where('is_active', true)->get(['target_type', 'target_ref']);

        return response()->json(['data' => $this->overlaySummary($overlay) + [
            'versions' => $versions->values(),
            'assignments' => $assignments,
        ]]);
    }

    public function version(string $code, int $versionNo): JsonResponse
    {
        $overlay = IsoOverlay::where('code', $code)->first();
        $version = $overlay ? IsoOverlayVersion::where('overlay_id', $overlay->id)->where('version_no', $versionNo)
            ->where('status', '!=', IsoOverlayVersion::DRAFT)->first() : null;

        if (!$version) {
            return $this->notFound('Published version not found.');
        }

        return response()->json(['data' => $this->service->snapshot($version) + [
            'status' => $version->status,
            'content_hash' => $version->content_hash,
            'hash_scheme' => $version->hashScheme(),
            'integrity_verified' => $this->service->verifyIntegrity($version),
        ]]);
    }

    private function overlaySummary(IsoOverlay $overlay): array
    {
        $current = $overlay->currentVersion;

        return [
            'code' => $overlay->code,
            'name' => $overlay->name,
            'description' => $overlay->description,
            'flinkiso_standard_id' => $overlay->flinkiso_standard_id,
            'framework_label' => $overlay->framework_label,
            'is_active' => (bool)$overlay->is_active,
            'current_version' => $current ? [
                'version_no' => (int)$current->version_no,
                'edition' => $current->edition,
                'content_hash' => $current->content_hash,
            ] : null,
        ];
    }

    private function notFound(string $message): JsonResponse
    {
        return response()->json(['error' => ['code' => 'not_found', 'message' => $message]], 404);
    }
}
