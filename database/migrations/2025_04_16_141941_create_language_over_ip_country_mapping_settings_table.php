<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('language_over_ip_country_mapping_settings', function (Blueprint $table) {
            $table->id();
            // Country IDs are the primary keys of country rows in `regions`.
            $table->unsignedInteger('country_id');
            $table->foreign('country_id')->references('id')->on('regions')->onDelete('cascade');
            $table->string('language', 255);  // Language (e.g., English, Arabic)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('language_over_ip_country_mapping_settings');
    }
};
