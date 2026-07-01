<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function usage(): JsonResponse
    {
        $org = Organisation::with('plan')->findOrFail(app('current_organisation_id'));

        return response()->json([
            'plan'      => $org->plan?->nom ?? 'Starter',
            'resources' => PlanLimitService::getAll($org),
        ]);
    }
}
