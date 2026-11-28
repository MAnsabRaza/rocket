<?php

namespace App\Services\IsoContent;

class ImportReport
{
    public ?ModuleReport $workflows  = null;
    public ?ModuleReport $checklists = null;
    public ?ModuleReport $kpis       = null;
    public ?ModuleReport $risks      = null;
    public ?ModuleReport $forms      = null;
    public ?string       $overlay   = null;

    /** True when this report was produced without writing anything. */
    public bool $dryRun = false;

    /** Batch identifier for the last real import (used for rollback). */
    public ?string $batchId = null;

    public function toArray(): array
    {
        return [
            'dry_run'    => $this->dryRun,
            'batch_id'   => $this->batchId,
            'workflows'  => $this->workflows?->toArray(),
            'checklists' => $this->checklists?->toArray(),
            'kpis'       => $this->kpis?->toArray(),
            'risks'      => $this->risks?->toArray(),
            'forms'      => $this->forms?->toArray(),
            'overlay'    => $this->overlay,
        ];
    }
}
