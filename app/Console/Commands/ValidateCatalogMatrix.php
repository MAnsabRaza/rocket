<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml; // Note: Requires symfony/yaml package

class ValidateCatalogMatrix extends Command
{
    protected $signature = 'iso:validate-catalog';
    protected $description = 'Validate M11 ISO Catalog Matrix and Common Field Dictionary YAML files';

    public function handle(): int
    {
        $this->info('Validating M11 ISO Catalog Artifacts...');

        $files = [
            'Matrix' => base_path('docs/iso-catalog/matrix.v1.0.0.yaml'),
            'Dictionary' => base_path('docs/iso-catalog/common-field-dictionary.v1.0.0.yaml'),
            'Lookups' => base_path('docs/iso-catalog/lookups.v1.0.0.yaml'),
        ];

        $allValid = true;

        foreach ($files as $name => $path) {
            if (!File::exists($path)) {
                $this->error("❌ {$name} file not found at: {$path}");
                $allValid = false;
                continue;
            }

            try {
                $data = Yaml::parseFile($path);
                if (empty($data)) {
                    throw new \Exception("File is empty or invalid YAML.");
                }
                $this->info("✅ {$name} is valid YAML.");
            } catch (\Exception $e) {
                $this->error("❌ {$name} YAML parsing failed: " . $e->getMessage());
                $allValid = false;
            }
        }

        if ($allValid) {
            $this->info('🎉 All M11 Artifacts are valid and ready for client review.');
            return 0;
        }

        $this->error('⚠️ Validation failed. Please fix the errors above.');
        return 1;
    }
}