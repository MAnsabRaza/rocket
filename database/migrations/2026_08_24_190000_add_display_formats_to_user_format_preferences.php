<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Date format, time format and week start gain durable storage.
 *
 * These three preferences never had a column anywhere. They were passed to
 * users->update() where the fillable filter silently discarded them, leaving a
 * cookie and the session as their only persistence. Suppressing cookies for
 * signed-in users to correct the 502 of 24 August removed one of those two, and
 * the client reported the date format not surviving a refresh.
 *
 * They are added to this project's own table rather than to `users`, which has
 * 304 live accounts and is out of bounds for this work. Each column is nullable
 * and guarded, so the migration is safe to run more than once and reverses
 * cleanly.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'date_format' => 30,
        'time_format' => 30,
        'week_start'  => 10,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('user_format_preferences')) {
            return;
        }

        Schema::table('user_format_preferences', function (Blueprint $table) {
            foreach (self::COLUMNS as $column => $length) {
                if (! Schema::hasColumn('user_format_preferences', $column)) {
                    $table->string($column, $length)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_format_preferences')) {
            return;
        }

        Schema::table('user_format_preferences', function (Blueprint $table) {
            foreach (array_keys(self::COLUMNS) as $column) {
                if (Schema::hasColumn('user_format_preferences', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
