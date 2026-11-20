<?php

namespace App\Http\Middleware;

use App\Services\PnpAcquisitionService;
use App\Services\SafeRedirect;
use Closure;
use Illuminate\Http\Request;

class CapturePnpContext
{
    public function handle(Request $request, Closure $next)
    {
        $acquisition = app(PnpAcquisitionService::class)->capture($request->query());
        if (!$request->session()->has('pnp_acquisition') && $acquisition) {
            $request->session()->put('pnp_acquisition', $acquisition);
        }

        $parameter = config('pnp.continue_parameter', 'continue');
        $candidate = $request->query($parameter);
        if (!$request->session()->has('pnp_continue') && $candidate !== null) {
            $safe = app(SafeRedirect::class)->validate($candidate);
            if ($safe) {
                $request->session()->put('pnp_continue', $safe);
            }
        }

        return $next($request);
    }
}
