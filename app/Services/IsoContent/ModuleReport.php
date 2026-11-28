<?php

namespace App\Services\IsoContent;

class ModuleReport
{
    public int $created   = 0;
    public int $updated   = 0;
    public int $skipped   = 0;
    /** @var string[] technical keys that differed from the seed and were overwritten */
    public array $conflicts = [];
    /** @var string[] technical keys newly created by this import */
    public array $createdKeys = [];

    public function __construct(
        public readonly string $module,
        public readonly int $expected,
    ) {}

    public function toArray(): array
    {
        return [
            'module'    => $this->module,
            'expected'  => $this->expected,
            'created'   => $this->created,
            'updated'   => $this->updated,
            'skipped'   => $this->skipped,
            'conflicts' => $this->conflicts,
        ];
    }
}
