<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('erp_post_sale_syncs') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `erp_post_sale_syncs` MODIFY COLUMN `status` ENUM('pending', 'success', 'partial', 'failed') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down()
    {
        if (Schema::hasTable('erp_post_sale_syncs') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `erp_post_sale_syncs` MODIFY COLUMN `status` ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending'");
        }
    }
};
