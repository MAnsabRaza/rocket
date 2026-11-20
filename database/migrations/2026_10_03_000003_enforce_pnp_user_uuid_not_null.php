<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EnforcePnpUserUuidNotNull extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('users', 'pnp_user_uuid')) {
            return;
        }

        DB::table('users')->whereNull('pnp_user_uuid')->orderBy('id')->chunkById(500, function ($users) {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->whereNull('pnp_user_uuid')->update([
                    'pnp_user_uuid' => (string) Str::uuid(),
                ]);
            }
        });

        DB::statement('ALTER TABLE `users` MODIFY `pnp_user_uuid` CHAR(36) NOT NULL');
    }

    public function down()
    {
        if (Schema::hasColumn('users', 'pnp_user_uuid')) {
            DB::statement('ALTER TABLE `users` MODIFY `pnp_user_uuid` CHAR(36) NULL');
        }
    }
}
