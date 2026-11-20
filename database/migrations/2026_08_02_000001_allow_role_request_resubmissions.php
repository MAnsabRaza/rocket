<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $table = 'user_role_requests';

    public function up(): void
    {
        foreach ([
            'user_role_requests_user_id_role_catalog_id_unique',
            'user_role_requests_user_role_idx',
        ] as $legacyIndex) {
            $this->dropIndexIfExists($legacyIndex);
        }

        if (!$this->indexExists('user_role_requests_user_role_index')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->index(['user_id', 'role_catalog_id'], 'user_role_requests_user_role_index');
            });
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('user_role_requests_user_role_index');

        if (!$this->indexExists('user_role_requests_user_role_idx')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->index(['user_id', 'role_catalog_id'], 'user_role_requests_user_role_idx');
            });
        }
    }

    protected function dropIndexIfExists(string $indexName): void
    {
        if (!$this->indexExists($indexName)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }

    protected function indexExists(string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select('PRAGMA index_list(' . $this->table . ')');

            return collect($rows)->contains(fn ($row) => $row->name === $indexName);
        }

        if ($driver === 'pgsql') {
            $rows = DB::select(
                'select indexname from pg_indexes where tablename = ? and indexname = ?',
                [$this->table, $indexName]
            );

            return count($rows) > 0;
        }

        $rows = DB::select('SHOW INDEX FROM ' . $this->table . ' WHERE Key_name = ?', [$indexName]);

        return count($rows) > 0;
    }
};