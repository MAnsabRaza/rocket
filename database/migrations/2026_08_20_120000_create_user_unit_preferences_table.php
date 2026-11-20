<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M3 — normalised user unit preferences. Option B, approved decision 2, 20 Aug 2026.
 *
 * The existing preferred_length_unit, preferred_mass_unit and preferred_area_unit
 * columns on `users` are LEFT IN PLACE and are not modified. PreferenceRepository
 * falls back to them when no normalised row exists, so behaviour is unchanged for
 * every existing account until a preference is explicitly saved.
 *
 * A normalised table was chosen over adding columns because configuration defines
 * nine categories of which only three had storage, and because `users` sits inside
 * a foreign key chain that caused row manipulation to fail during the M1 rollback
 * rehearsal. Adding a column per category would mean repeated migrations against a
 * live table holding 306 accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_unit_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('category', 40);
            $table->string('unit', 20);
            $table->timestamps();

            $table->unique(['user_id', 'category']);
            $table->index('user_id');
        });

        Schema::create('user_format_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('measurement_system', 20)->nullable();
            $table->string('locale', 10)->nullable();
            $table->string('decimal_separator', 4)->nullable();
            $table->string('thousands_separator', 4)->nullable();
            $table->unsignedTinyInteger('display_precision')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_format_preferences');
        Schema::dropIfExists('user_unit_preferences');
    }
};
