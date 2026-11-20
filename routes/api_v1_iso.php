<?php

use App\Http\Controllers\Api\V1\IsoOverlayController;
use App\Http\Middleware\Api\V1\AuthenticateWithSanctum;
use App\Http\Middleware\Api\V1\EnsureAccountIsUsable;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

/*
 * M8 ISO overlay read API, same security chain as the M4 unit API:
 *   1 throttle        60 requests per minute
 *   2 authenticate    Sanctum bearer token only
 *   3 account state   the token owner's account must be usable
 *   4 scope           the token must carry iso:read
 * The dead platform x-api-key check is bypassed as for M4 (decision 19).
 * Read only: drafts are never exposed and nothing can be changed through this API.
 */

Route::prefix('v1')
    ->withoutMiddleware(\App\Http\Middleware\Api\CheckApiKey::class)
    ->middleware([
        'throttle:60,1',
        AuthenticateWithSanctum::class,
        EnsureAccountIsUsable::class,
        CheckForAnyAbility::class . ':iso:read',
    ])
    ->group(function () {
        Route::get('iso-overlays', [IsoOverlayController::class, 'index'])->name('api.v1.iso_overlays.index');
        Route::get('iso-overlays/{code}', [IsoOverlayController::class, 'show'])
            ->where('code', '[A-Za-z0-9._-]+')->name('api.v1.iso_overlays.show');
        Route::get('iso-overlays/{code}/versions/{versionNo}', [IsoOverlayController::class, 'version'])
            ->where('code', '[A-Za-z0-9._-]+')->whereNumber('versionNo')->name('api.v1.iso_overlays.version');
    });
