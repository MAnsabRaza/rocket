<?php

namespace App\Events;

use App\Services\PnpAcquisitionService;
use App\User;

class PnpUserRegistered
{
    public $userId;
    public $pnpUserUuid;
    public $email;
    public $mobile;
    public $acquisitionSource;
    public $externalReferralKey;
    public $campaignKey;
    public $targetReference;
    public $registeredAt;

    public function __construct(User $user)
    {
        $context = app(PnpAcquisitionService::class)->forUser($user);
        $this->userId = $user->id;
        $this->pnpUserUuid = $user->pnp_user_uuid;
        $this->email = $user->email;
        $this->mobile = $user->mobile;
        $this->acquisitionSource = $context['pnp_acquisition_source'] ?? null;
        $this->externalReferralKey = $context['pnp_external_referral_key'] ?? null;
        $this->campaignKey = $context['pnp_campaign_key'] ?? null;
        $this->targetReference = $context['pnp_target_reference'] ?? null;
        $this->registeredAt = $user->created_at;
    }
}