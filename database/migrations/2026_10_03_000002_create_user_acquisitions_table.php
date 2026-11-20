<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateUserAcquisitionsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('user_acquisitions')) {
            Schema::create('user_acquisitions', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->increments('id');
                $table->integer('user_id')->unsigned()->unique();
                $table->string('acquisition_source', 100)->nullable();
                $table->string('external_referral_key', 100)->nullable()->index();
                $table->string('campaign_key', 100)->nullable();
                $table->timestamp('captured_at')->nullable();
                $table->timestamps();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // Preserve any PNP metadata captured by an earlier release.
        if (Schema::hasTable('users_metas')) {
            DB::table('users_metas')->whereIn('name', ['pnp_acquisition_source', 'pnp_external_referral_key', 'pnp_campaign_key', 'pnp_captured_at'])
                ->select('id', 'user_id')->orderBy('id')->chunkById(500, function ($rows) {
                foreach ($rows->pluck('user_id')->unique() as $userId) {
                    $values = DB::table('users_metas')->where('user_id', $userId)
                        ->whereIn('name', ['pnp_acquisition_source', 'pnp_external_referral_key', 'pnp_campaign_key', 'pnp_captured_at'])
                        ->pluck('value', 'name')->all();
                    $exists = DB::table('user_acquisitions')->where('user_id', $userId)->exists();
                    if (!$exists) {
                        DB::table('user_acquisitions')->insert([
                            'user_id' => $userId,
                            'acquisition_source' => $values['pnp_acquisition_source'] ?? null,
                            'external_referral_key' => $values['pnp_external_referral_key'] ?? null,
                            'campaign_key' => $values['pnp_campaign_key'] ?? null,
                            'captured_at' => $values['pnp_captured_at'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        DB::table('users_metas')->where('user_id', $userId)
                            ->whereIn('name', ['pnp_acquisition_source', 'pnp_external_referral_key', 'pnp_campaign_key', 'pnp_captured_at'])->delete();
                    }
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('user_acquisitions') && !Schema::hasTable('users_metas')
            && DB::table('user_acquisitions')->where(function ($query) {
                $query->whereNotNull('acquisition_source')
                    ->orWhereNotNull('external_referral_key')
                    ->orWhereNotNull('campaign_key')
                    ->orWhereNotNull('captured_at');
            })->exists()) {
            throw new RuntimeException('Cannot roll back acquisition data without the users_metas table.');
        }

        if (Schema::hasTable('user_acquisitions') && Schema::hasTable('users_metas')) {
            DB::table('user_acquisitions')->orderBy('id')->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $values = [
                        'pnp_acquisition_source' => $row->acquisition_source,
                        'pnp_external_referral_key' => $row->external_referral_key,
                        'pnp_campaign_key' => $row->campaign_key,
                        'pnp_captured_at' => $row->captured_at,
                    ];
                    foreach ($values as $name => $value) {
                        if ($value !== null) {
                            DB::table('users_metas')->updateOrInsert(
                                ['user_id' => $row->user_id, 'name' => $name],
                                ['value' => (string) $value]
                            );
                        }
                    }
                }
            });
        }
        Schema::dropIfExists('user_acquisitions');
    }
}
