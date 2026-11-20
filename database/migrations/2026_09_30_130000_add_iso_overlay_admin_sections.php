<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * M8: admin permission sections for the ISO Overlay Template Engine, granted to the
 * admin role (2), following the same pattern as admin_role_catalog. The Gate caches
 * the section list under the key 'sections', so the cache entry is cleared.
 */
return new class extends Migration
{
    private const PARENT = 'admin_iso_overlays';

    private const CHILDREN = [
        'admin_iso_overlays_list' => 'ISO Overlays List',
        'admin_iso_overlays_manage' => 'Create and Edit ISO Overlay Drafts',
        'admin_iso_overlays_publish' => 'Publish, Activate, Roll Back and Archive ISO Overlays',
    ];

    private const ADMIN_ROLE_ID = 2;

    public function up()
    {
        if (DB::table('sections')->where('name', self::PARENT)->exists()) {
            return;
        }

        DB::transaction(function () {
            $parentId = DB::table('sections')->insertGetId([
                'name' => self::PARENT,
                'section_group_id' => null,
                'caption' => 'ISO Overlays',
                'type' => 'admin',
            ]);

            $ids = [$parentId];
            foreach (self::CHILDREN as $name => $caption) {
                $ids[] = DB::table('sections')->insertGetId([
                    'name' => $name,
                    'section_group_id' => $parentId,
                    'caption' => $caption,
                    'type' => 'admin',
                ]);
            }

            foreach ($ids as $sectionId) {
                DB::table('permissions')->insert([
                    'role_id' => self::ADMIN_ROLE_ID,
                    'section_id' => $sectionId,
                    'allow' => 1,
                ]);
            }
        });

        Cache::forget('sections');
    }

    public function down()
    {
        $names = array_merge([self::PARENT], array_keys(self::CHILDREN));
        $ids = DB::table('sections')->whereIn('name', $names)->pluck('id');

        DB::transaction(function () use ($ids) {
            DB::table('permissions')->whereIn('section_id', $ids)->delete();
            DB::table('sections')->whereIn('id', $ids)->delete();
        });

        Cache::forget('sections');
    }
};
