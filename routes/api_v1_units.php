<?php

use App\Http\Controllers\Api\V1\ConversionController;
use App\Http\Controllers\Api\V1\PreferenceController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Middleware\Api\V1\AuthenticateWithSanctum;
use App\Http\Middleware\Api\V1\EnsureAccountIsUsable;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

/**
 * M4 - versioned internal unit API.
 *
 * Sanctum rather than the existing jwt "api" guard, because the specification
 * names Sanctum and requires token scopes, which this installation's jwt guard
 * does not provide natively. Two auth mechanisms therefore coexist in one API,
 * and that is declared in the M4 report rather than left to be discovered.
 *
 * Middleware order is deliberate:
 *   1 throttle          bound the request rate
 *   2 authenticate      is there a valid token
 *   3 account state     is this account still permitted to use the API
 *   4 scope             does this token carry the right ability
 *
 * Account state precedes scope so a banned account is refused even when its
 * token carries every ability. Middleware classes are named in full because
 * app/Http/Kernel.php is ionCube-encoded and aliases cannot be registered.
 *
 * The rate limit is the specification's "protection against abusive payloads"
 * and the non-functional requirement to prevent unbounded requests. Batch size
 * bounds one request; this bounds how many arrive. A batch of 500 at 60
 * requests a minute is the ceiling a single token can reach.
 */
Route::prefix('v1')
    ->withoutMiddleware(\App\Http\Middleware\Api\CheckApiKey::class)
    ->middleware([
        'throttle:60,1',
        AuthenticateWithSanctum::class,
        EnsureAccountIsUsable::class,
    ])
    ->group(function () {

        Route::get('units', [UnitController::class, 'index'])
            ->middleware(CheckForAnyAbility::class . ':units:read')
            ->name('api.v1.units.index');

        Route::post('convert', [ConversionController::class, 'convert'])
            ->middleware(CheckForAnyAbility::class . ':units:convert')
            ->name('api.v1.convert');

        Route::post('convert/batch', [ConversionController::class, 'batch'])
            ->middleware(CheckForAnyAbility::class . ':units:convert')
            ->name('api.v1.convert.batch');

        Route::get('preferences', [PreferenceController::class, 'show'])
            ->middleware(CheckForAnyAbility::class . ':preferences:read')
            ->name('api.v1.preferences.show');

        Route::put('preferences', [PreferenceController::class, 'update'])
            ->middleware(CheckForAnyAbility::class . ':preferences:write')
            ->name('api.v1.preferences.update');
    });
