<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AddPnpUserUuidToUsersTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('users', 'pnp_user_uuid')) {
            Schema::table('users', function (Blueprint $table) {
                $table->char('pnp_user_uuid', 36)->nullable();
            });
        }

        DB::table('users')->whereNull('pnp_user_uuid')->orderBy('id')->chunkById(500, function ($users) {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->whereNull('pnp_user_uuid')->update([
                    'pnp_user_uuid' => (string) Str::uuid(),
                ]);
            }
        });

        $seenUuids = [];
        DB::table('users')->whereNotNull('pnp_user_uuid')->orderBy('id')->select('id', 'pnp_user_uuid')->chunkById(500, function ($users) use (&$seenUuids) {
            foreach ($users as $user) {
                if (isset($seenUuids[$user->pnp_user_uuid])) {
                    DB::table('users')->where('id', $user->id)->update([
                        'pnp_user_uuid' => (string) Str::uuid(),
                    ]);
                } else {
                    $seenUuids[$user->pnp_user_uuid] = true;
                }
            }
        });

        $indexes = collect(DB::select("SHOW INDEX FROM users"))->pluck('Key_name')->all();
        if (!in_array('users_pnp_user_uuid_unique', $indexes, true)) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('pnp_user_uuid', 'users_pnp_user_uuid_unique');
            });
        }
    }

    public function down()
    {
        $indexes = collect(DB::select("SHOW INDEX FROM users"))->pluck('Key_name')->all();
        if (in_array('users_pnp_user_uuid_unique', $indexes, true)) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_pnp_user_uuid_unique');
            });
        }
        if (Schema::hasColumn('users', 'pnp_user_uuid')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('pnp_user_uuid');
            });
        }
    }
}
