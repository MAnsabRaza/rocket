<?php

namespace Database\Factories;

use App\Models\ErpCredential;
use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ErpCredentialFactory extends Factory
{
    protected $model = ErpCredential::class;

    public function definition(): array
    {
        return [
            'vendor_id' => User::factory(),
            'type' => 'import_export',
            'base_url' => 'https://erp.test',
            'api_key' => 'test-api-key',
            'is_active' => true,
            'export_ability_enabled' => false,
            'checklist' => [],
            'import_dropshipping_enabled' => false,
            'rate_limit_per_minute' => 60,
        ];
    }
}