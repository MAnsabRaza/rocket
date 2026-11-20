<?php

namespace App\Http\Middleware\Api\V1;

use Closure;

/**
 * Account-state permission gate for the v1 API.
 *
 * A token outlives the account state that issued it. Banning a user, setting
 * them inactive or deleting them does not invalidate tokens already in the
 * wild, so authentication alone is not authorisation.
 *
 * Rejects, using this application's own vocabulary:
 *   ban        1 for a banned account
 *   status     anything other than active
 *   deleted_at set for a soft-deleted account
 *
 * No endpoint is restricted by role. Every role in this installation, user,
 * teacher, organization and admin, legitimately reads units and holds unit
 * preferences, and each endpoint acts only on the authenticated caller. That is
 * a deliberate decision rather than an omission, and it is recorded in the M4
 * report.
 */
class EnsureAccountIsUsable
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($this->isRefused($user)) {
            return response()->json([
                'success' => 0,
                'status'  => 'account_not_permitted',
                'message' => 'This account may not use the API.',
            ], 403);
        }

        return $next($request);
    }

    /** Exposed so the rules can be tested without mutating a real account. */
    public function isRefused($user): bool
    {
        if ((int) ($user->ban ?? 0) === 1) {
            return true;
        }

        if (($user->status ?? 'active') !== 'active') {
            return true;
        }

        return ! empty($user->deleted_at);
    }
}
