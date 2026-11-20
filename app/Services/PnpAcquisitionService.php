<?php

namespace App\Services;

use App\Models\UserAcquisition;
use App\User;
use Illuminate\Support\Carbon;

class PnpAcquisitionService
{
    public function capture(array $input): array
    {
        $values = [];
        foreach ((array) config('pnp.acquisition_parameters', []) as $parameter => $field) {
            $value = $input[$parameter] ?? null;
            if (is_string($value) && strlen($value) <= config('pnp.parameter_max_length', 100)
                && preg_match('/^[A-Za-z0-9_.-]*$/', $value) && $value !== '') {
                $values[$field] = $value;
            }
        }

        return $values;
    }

    public function storeFirstTouch(User $user, array $values): void
    {
        if (!UserAcquisition::where('user_id', $user->id)->exists()
            && array_filter($values, function ($value) { return is_string($value) && $value !== ''; })) {
            UserAcquisition::create([
                'user_id' => $user->id,
                'acquisition_source' => $values['acquisition_source'] ?? null,
                'external_referral_key' => $values['external_referral_key'] ?? null,
                'campaign_key' => $values['campaign_key'] ?? null,
                'target_reference' => $values['target_reference'] ?? null,
                'captured_at' => Carbon::now(),
            ]);
        }
    }

    public function forUser(User $user): array
    {
        $record = UserAcquisition::where('user_id', $user->id)->first();
        if (!$record) {
            return [];
        }

        return [
            'pnp_acquisition_source' => $record->acquisition_source,
            'pnp_external_referral_key' => $record->external_referral_key,
            'pnp_campaign_key' => $record->campaign_key,
            'pnp_target_reference' => $record->target_reference,
            'pnp_captured_at' => $record->captured_at,
        ];
    }
}
