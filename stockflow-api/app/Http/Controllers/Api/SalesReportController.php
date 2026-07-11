<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesReportRequest;
use App\Services\SalesReportService;
use Illuminate\Http\JsonResponse;

class SalesReportController extends Controller
{
    public function __construct(
        private readonly SalesReportService $salesReportService
    ) {}

    public function __invoke(
        SalesReportRequest $request
    ): JsonResponse {
        $report =
            $this->salesReportService
                ->generate(
                    $request->validated()
                );

        return response()->json(
            $report
        );
    }
}
