<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M9: default content library versioning support.
 *
 * Each library row keeps a JSON history of previous snapshots so edits and
 * explicit version bumps do not silently overwrite the content that published
 * overlays were built from.
 */
return new class extends Migration
{
    private const TABLES = [
        'iso_content_workflows',
        'iso_content_checklists',
        'iso_content_kpis',
        'iso_content_risks',
        'iso_content_forms',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->json('version_history')->nullable()->after('version');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('version_history');
            });
        }
    }
};
