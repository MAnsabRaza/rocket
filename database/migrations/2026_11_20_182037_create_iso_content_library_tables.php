<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ────────────────────────────────────────────────────────────────
        // Workflows: state machine definitions
        // ────────────────────────────────────────────────────────────────
        Schema::create('iso_content_workflows', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('technical_key', 80)->unique();
            $table->string('category', 40)->index();
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('states');
            $table->json('transitions');
            $table->json('roles')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ────────────────────────────────────────────────────────────────
        // Audit Checklists
        // ────────────────────────────────────────────────────────────────
        Schema::create('iso_content_checklists', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('technical_key', 80)->unique();
            $table->string('standard_ref', 40)->nullable()->index();
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('schema');
            $table->json('scoring')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ────────────────────────────────────────────────────────────────
        // KPIs
        // ────────────────────────────────────────────────────────────────
        Schema::create('iso_content_kpis', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('technical_key', 80)->unique();
            $table->string('category', 40)->index();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('clause_ref', 40)->nullable();
            $table->string('formula', 255);
            $table->string('unit', 30);
            $table->string('frequency', 20)->default('monthly');
            $table->string('calculation', 20)->default('automatic');
            $table->json('thresholds');
            $table->json('roles')->nullable();
            $table->json('source')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ────────────────────────────────────────────────────────────────
        // Risk library
        // ────────────────────────────────────────────────────────────────
        Schema::create('iso_content_risks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('technical_key', 80)->unique();
            $table->string('category', 40)->index();
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('causes')->nullable();
            $table->json('consequences')->nullable();
            $table->unsignedTinyInteger('default_probability')->default(3);
            $table->unsignedTinyInteger('default_impact')->default(3);
            $table->json('mitigations')->nullable();
            $table->json('controls')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ────────────────────────────────────────────────────────────────
        // Form templates
        // ────────────────────────────────────────────────────────────────
        Schema::create('iso_content_forms', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('technical_key', 80)->unique();
            $table->string('category', 40)->index();
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('schema');
            $table->json('approval')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iso_content_forms');
        Schema::dropIfExists('iso_content_risks');
        Schema::dropIfExists('iso_content_kpis');
        Schema::dropIfExists('iso_content_checklists');
        Schema::dropIfExists('iso_content_workflows');
    }
};