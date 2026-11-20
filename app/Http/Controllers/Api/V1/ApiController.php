<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Envelope for the v1 unit API (M4).
 *
 * Body keys match the existing apiResponse2 helper exactly: success, status,
 * message and an optional data object, so consumers of the Rocket LMS API see a
 * familiar shape.
 *
 * It does not call that helper, because apiResponse2 always returns HTTP 200
 * regardless of outcome. These are new routes with no existing consumers, so
 * they carry correct status codes. Returning 200 for a validation failure would
 * make the M5 auth and permission tests meaningless.
 */
abstract class ApiController extends Controller
{
    protected function envelope(int $success, string $status, string $message, $data, int $code): JsonResponse
    {
        $json = ['success' => $success, 'status' => $status, 'message' => $message];

        if ($data !== null) {
            $json['data'] = $data;
        }

        return response()->json($json, $code);
    }

    protected function ok(string $status, string $message, $data = null, int $code = 200): JsonResponse
    {
        return $this->envelope(1, $status, $message, $data, $code);
    }

    protected function fail(string $status, string $message, $data = null, int $code = 422): JsonResponse
    {
        return $this->envelope(0, $status, $message, $data, $code);
    }
}
