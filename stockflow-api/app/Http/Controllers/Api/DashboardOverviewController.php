<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardOverviewService;
use Illuminate\Http\JsonResponse;

class DashboardOverviewController extends Controller
{
    public function __construct(
        private readonly DashboardOverviewService $dashboardOverviewService
    ) {}

    public function __invoke(): JsonResponse
    {
        return response()->json(
            $this->dashboardOverviewService
                ->generate()
        );
    }
}
