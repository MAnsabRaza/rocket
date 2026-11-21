<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M9 Module 4: configurable risk matrix metadata.
 *
 * Each default risk library entry carries the probability/impact scales, the
 * scoring rule, the risk-level matrix and the residual-risk rule so a risk can
 * be scored and rendered as a heat map without per-standard code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iso_content_risks', function (Blueprint $table) {
            $table->unsignedTinyInteger('probability_scale')->default(5)->after('default_impact');
            $table->unsignedTinyInteger('impact_scale')->default(5)->after('probability_scale');
            $table->string('scoring_rule', 20)->default('multiply')->after('impact_scale');
            $table->json('levels')->nullable()->after('scoring_rule');
            $table->json('residual')->nullable()->after('levels');
        });
    }

    public function down(): void
    {
        Schema::table('iso_content_risks', function (Blueprint $table) {
            $table->dropColumn(['probability_scale', 'impact_scale', 'scoring_rule', 'levels', 'residual']);
        });
    }
};
