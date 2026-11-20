<?php

namespace App\Http\Middleware\Api\V1;

use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Sanctum authentication for the v1 unit API.
 *
 * Used instead of the stock auth:sanctum middleware because this application's
 * exception handler renders an unauthenticated API request as HTTP 500 with the
 * message HTTP_INTERNAL_SERVER_ERROR. A missing credential is a client error,
 * not a server fault, and reporting it as 500 would make the M5 authentication
 * tests meaningless.
 *
 * The host handler is not modified. This middleware answers before the
 * exception path is reached, and returns the same envelope as every other v1
 * response.
 */
class AuthenticateWithSanctum
{
    public function handle($request, Closure $next)
    {
        $user = Auth::guard('sanctum')->user();

        if ($user === null) {
            return response()->json([
                'success' => 0,
                'status'  => 'unauthenticated',
                'message' => 'A valid bearer token is required.',
            ], 401);
        }

        Auth::shouldUse('sanctum');
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
