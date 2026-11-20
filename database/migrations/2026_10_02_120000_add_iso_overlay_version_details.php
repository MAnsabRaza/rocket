<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M8 correction: overlay details (name, description, framework label, FlinkISO
 * standard) are recorded in each version and covered by its content hash.
 *
 * meta         the version's own copy of the overlay details
 * hash_scheme  which content definition the stored hash covers:
 *              NULL or 1 = items only (versions published before this correction)
 *              2         = items and details
 *
 * Only draft versions are filled in from the overlay record. Published and
 * archived versions are not touched: their details at publication time were not
 * recorded, and copying today's values into them would claim something unknown.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('iso_overlay_versions', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('edition');
            $table->unsignedTinyInteger('hash_scheme')->nullable()->after('content_hash');
        });

        $drafts = DB::table('iso_overlay_versions as v')
            ->join('iso_overlays as o', 'o.id', '=', 'v.overlay_id')
            ->where('v.status', 'draft')
            ->get(['v.id', 'o.name', 'o.description', 'o.framework_label', 'o.flinkiso_standard_id']);

        foreach ($drafts as $row) {
            $name = json_decode($row->name ?? 'null', true);
            $description = json_decode($row->description ?? 'null', true);
            $meta = [
                'description' => is_array($description) && $description ? $description : null,
                'flinkiso_standard_id' => $row->flinkiso_standard_id !== null && $row->flinkiso_standard_id !== '' ? (string)$row->flinkiso_standard_id : null,
                'framework_label' => $row->framework_label !== null && $row->framework_label !== '' ? (string)$row->framework_label : null,
                'name' => is_array($name) ? $name : [],
            ];
            DB::table('iso_overlay_versions')->where('id', $row->id)->where('status', 'draft')
                ->update(['meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
    }

    public function down()
    {
        Schema::table('iso_overlay_versions', function (Blueprint $table) {
            $table->dropColumn(['meta', 'hash_scheme']);
        });
    }
};
