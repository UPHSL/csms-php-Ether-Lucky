<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Reports which application and release is running and that it responds.
 *
 * Every value comes from config/csms.php. Nothing about the environment,
 * database or configuration secrets is included.
 */
class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'application' => config('csms.name'),
            'short_name' => config('csms.short_name'),
            'version' => config('csms.version'),
        ]);
    }
}
