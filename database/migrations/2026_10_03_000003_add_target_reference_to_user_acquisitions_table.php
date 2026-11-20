<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddTargetReferenceToUserAcquisitionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('user_acquisitions') && !Schema::hasColumn('user_acquisitions', 'target_reference')) {
            Schema::table('user_acquisitions', function (Blueprint $table) {
                $table->string('target_reference', 100)->nullable()->after('campaign_key');
            });
        }

        if (Schema::hasTable('user_acquisitions') && Schema::hasTable('users_metas')
            && Schema::hasColumn('user_acquisitions', 'target_reference')) {
            DB::table('users_metas')->where('name', 'pnp_target_reference')->orderBy('id')->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('user_acquisitions')->where('user_id', $row->user_id)
                        ->whereNull('target_reference')->update(['target_reference' => $row->value]);
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('user_acquisitions') && Schema::hasColumn('user_acquisitions', 'target_reference')) {
            $hasTargetReferences = DB::table('user_acquisitions')->whereNotNull('target_reference')->exists();
            if ($hasTargetReferences && !Schema::hasTable('users_metas')) {
                throw new RuntimeException('Cannot roll back target_reference without the users_metas table.');
            }

            if (Schema::hasTable('users_metas')) {
                DB::table('user_acquisitions')->whereNotNull('target_reference')->orderBy('id')->chunkById(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('users_metas')->updateOrInsert(
                            ['user_id' => $row->user_id, 'name' => 'pnp_target_reference'],
                            ['value' => $row->target_reference]
                        );
                    }
                });
            }

            Schema::table('user_acquisitions', function (Blueprint $table) {
                $table->dropColumn('target_reference');
            });
        }
    }
}


