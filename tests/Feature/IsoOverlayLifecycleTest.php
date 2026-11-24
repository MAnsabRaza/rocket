<?php

namespace Tests\Feature;

use App\Models\IsoOverlay;
use App\Models\IsoOverlayVersion;
use App\Services\IsoOverlay\OverlayException;
use App\Services\IsoOverlay\OverlayService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * M10: activation and rollback must refuse a published version whose stored
 * content no longer matches the content hash recorded at publication.
 */
class IsoOverlayLifecycleTest extends TestCase
{
    private OverlayService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(OverlayService::class);
    }

    /**
     * @return array{0: IsoOverlay, 1: IsoOverlayVersion}
     */
    private function publishedOverlay(string $code): array
    {
        $overlay = $this->svc->createOverlay([
            'code' => $code,
            'name' => ['en' => 'Lifecycle Test'],
            'framework_label' => 'ISO 9001',
        ]);

        $draft = $overlay->draftVersion();
        $this->svc->addItem($draft, [
            'type' => 'module',
            'key' => 'module_a',
            'label' => ['en' => 'Module A'],
        ]);
        $this->svc->publish($draft);

        return [$overlay->fresh(), $draft->fresh()];
    }

    private function tamper(IsoOverlayVersion $version): void
    {
        DB::table('iso_overlay_items')
            ->where('version_id', $version->id)
            ->update(['label' => json_encode(['en' => 'Tampered after publication'])]);
    }

    public function test_activation_fails_if_integrity_check_fails(): void
    {
        [$overlay, $version] = $this->publishedOverlay('activation_integrity');

        $this->tamper($version);
        $version = $version->fresh();

        $this->assertFalse($this->svc->verifyIntegrity($version));

        try {
            $this->svc->activate($overlay, $version);
            $this->fail('Activation should have been refused for a version that fails its integrity check.');
        } catch (OverlayException $e) {
            $this->assertStringContainsString('Integrity check failed', $e->getMessage());
        }

        $this->assertNull($overlay->fresh()->current_version_id);
        $this->assertFalse($overlay->fresh()->is_active);
    }

    public function test_rollback_fails_if_integrity_check_fails(): void
    {
        [$overlay, $v1] = $this->publishedOverlay('rollback_integrity');
        $this->svc->activate($overlay, $v1);

        $v2 = $this->svc->newVersion($overlay->fresh(), $v1);
        $this->svc->publish($v2);
        $this->svc->activate($overlay->fresh(), $v2->fresh());

        $this->tamper($v1);
        $v1 = $v1->fresh();

        $this->assertFalse($this->svc->verifyIntegrity($v1));
        $this->assertSame($v2->id, $overlay->fresh()->current_version_id);

        try {
            $this->svc->rollback($overlay->fresh(), $v1);
            $this->fail('Rollback should have been refused for a version that fails its integrity check.');
        } catch (OverlayException $e) {
            $this->assertStringContainsString('Integrity check failed', $e->getMessage());
        }

        $this->assertSame($v2->id, $overlay->fresh()->current_version_id);
    }

    public function test_published_overlay_can_be_cloned(): void
    {
        [$overlay, $v1] = $this->publishedOverlay('clone_source');

        $clone = $this->svc->cloneOverlay($v1, 'clone_target', ['en' => 'Clone Target']);

        $this->assertSame('clone_target', $clone->code);
        $this->assertSame('Clone Target', $clone->name['en']);

        $draft = $clone->draftVersion();
        $this->assertNotNull($draft);
        $this->assertTrue($draft->isDraft());
        $this->assertSame($v1->items()->count(), $draft->items()->count());
        $this->assertSame($v1->items()->orderBy('id')->pluck('key')->all(), $draft->items()->orderBy('id')->pluck('key')->all());
    }

    public function test_arabic_rtl_content_is_preserved(): void
    {
        $overlay = $this->svc->createOverlay([
            'code' => 'rtl_overlay',
            'name' => ['en' => 'RTL Overlay', 'ar' => 'طبقة الاختبار'],
            'framework_label' => 'ISO 9001',
        ]);

        $draft = $overlay->draftVersion();
        $this->svc->addItem($draft, [
            'type' => 'module',
            'key' => 'module_ar',
            'label' => ['en' => 'Module', 'ar' => 'وحدة'],
        ]);
        $this->svc->publish($draft);
        $published = $draft->fresh();

        $this->assertSame('طبقة الاختبار', $published->localizedDetail('name', 'ar'));
        $this->assertSame('وحدة', $published->items()->first()->localizedLabel('ar'));
    }
}
