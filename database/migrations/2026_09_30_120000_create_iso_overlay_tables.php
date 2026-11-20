<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M8 ISO Overlay Template Engine.
 *
 * Overlays reference FlinkISO records (flinkisodb) by their existing ids and never
 * copy or change them. Versions are copy-on-write: a published version and its
 * items are never modified; changes are made in a new draft version.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('iso_overlays', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 64)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('flinkiso_standard_id', 36)->nullable()->index();
            $table->string('framework_label', 120)->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('iso_overlay_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('overlay_id');
            $table->unsignedInteger('version_no');
            $table->string('edition', 60)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedBigInteger('parent_version_id')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->string('change_note', 1000)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['overlay_id', 'version_no']);
            $table->foreign('overlay_id')->references('id')->on('iso_overlays');
        });

        Schema::create('iso_overlay_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('version_id');
            $table->string('type', 30);
            $table->string('key', 100);
            $table->string('parent_key', 100)->nullable();
            $table->string('ref_source', 20)->nullable();
            $table->string('ref_table', 64)->nullable();
            $table->string('ref_id', 64)->nullable();
            $table->json('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('data')->nullable();
            $table->timestamps();

            $table->unique(['version_id', 'type', 'key']);
            $table->index(['version_id', 'type']);
            $table->foreign('version_id')->references('id')->on('iso_overlay_versions');
        });

        Schema::create('iso_overlay_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('overlay_id');
            $table->string('target_type', 30);
            $table->string('target_ref', 64);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamps();

            $table->unique(['overlay_id', 'target_type', 'target_ref'], 'iso_overlay_assign_unique');
            $table->foreign('overlay_id')->references('id')->on('iso_overlays');
        });

        Schema::create('iso_overlay_audit_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('overlay_id')->nullable()->index();
            $table->unsignedBigInteger('version_id')->nullable()->index();
            $table->string('action', 40);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->char('before_hash', 64)->nullable();
            $table->char('after_hash', 64)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('iso_overlay_audit_log');
        Schema::dropIfExists('iso_overlay_assignments');
        Schema::dropIfExists('iso_overlay_items');
        Schema::dropIfExists('iso_overlay_versions');
        Schema::dropIfExists('iso_overlays');
    }
};
