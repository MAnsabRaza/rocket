<?php

namespace App\Services\IsoOverlay;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only pick lists of FlinkISO records for the admin screens. Never writes.
 */
class FlinkisoLookup
{
    private const LABEL_COLUMNS = ['title', 'name', 'table_name', 'caption', 'short_name'];

    private const LIMIT = 1000;

    public function options(string $table): array
    {
        try {
            $connection = DB::connection(OverlayService::FLINKISO_CONNECTION);
            $columns = Schema::connection(OverlayService::FLINKISO_CONNECTION)->getColumnListing($table);
        } catch (\Throwable $e) {
            return [];
        }

        if (!in_array('id', $columns, true)) {
            return [];
        }

        $select = ['id'];
        foreach (array_merge(['clause'], self::LABEL_COLUMNS) as $column) {
            if (in_array($column, $columns, true)) {
                $select[] = $column;
            }
        }

        $query = $connection->table($table)->select($select)->limit(self::LIMIT);
        if (in_array('soft_delete', $columns, true)) {
            $query->where(function ($q) {
                $q->where('soft_delete', 0)->orWhereNull('soft_delete');
            });
        }
        if (in_array('sr_no', $columns, true)) {
            $query->orderBy('sr_no');
        }

        $options = [];
        foreach ($query->get() as $row) {
            $options[$row->id] = $this->label($row);
        }

        return $options;
    }

    public function label(object $row): string
    {
        $parts = [];
        if (!empty($row->clause)) {
            $parts[] = $row->clause;
        }
        foreach (self::LABEL_COLUMNS as $column) {
            if (!empty($row->{$column})) {
                $parts[] = $row->{$column};
                break;
            }
        }

        return $parts ? mb_strimwidth(implode(' ', $parts), 0, 120, '...') : (string)$row->id;
    }
}
