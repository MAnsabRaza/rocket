<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M4 - Sanctum personal access tokens.
 *
 * Sanctum 3.0 is installed but the table it needs does not exist. The vendor
 * migration 2019_12_14_000001 has its up() body commented out, so this
 * installation deliberately never created it, while its down() still drops the
 * table.
 *
 * That vendor file is not edited here. It belongs to Rocket LMS, a platform
 * update could overwrite it, and it was disabled on purpose by someone. This
 * migration is ours, carries its own batch, and reverses independently with
 * migrate:rollback --step=1.
 *
 * The guard matters: on this installation the migration ledger and the schema
 * disagree in both directions, so presence is checked rather than inferred.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
