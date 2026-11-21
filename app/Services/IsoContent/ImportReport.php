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

    public function toArray(): array
    {
        return [
            'workflows'  => $this->workflows?->toArray(),
            'checklists' => $this->checklists?->toArray(),
            'kpis'       => $this->kpis?->toArray(),
            'risks'      => $this->risks?->toArray(),
            'forms'      => $this->forms?->toArray(),
            'overlay'    => $this->overlay,
        ];
    }
}
