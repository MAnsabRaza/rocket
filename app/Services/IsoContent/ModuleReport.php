<?php

namespace App\Services\IsoContent;

class ModuleReport
{
    public int $created = 0;

    public function __construct(
        public readonly string $module,
        public readonly int $expected,
    ) {}

    public function toArray(): array
    {
        return ['module' => $this->module, 'expected' => $this->expected, 'imported' => $this->created];
    }
}
