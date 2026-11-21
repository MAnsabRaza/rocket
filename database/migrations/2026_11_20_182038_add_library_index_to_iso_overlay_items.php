<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iso_overlay_items', function (Blueprint $table) {
            $table->index(['ref_source', 'ref_table', 'ref_id'], 'iso_items_library_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('iso_overlay_items', function (Blueprint $table) {
            $table->dropIndex('iso_items_library_lookup');
        });
    }
};