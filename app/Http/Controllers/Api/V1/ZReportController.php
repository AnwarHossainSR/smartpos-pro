<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ZReportRequest;
use App\Models\Business;
use App\Services\Reporting\ZReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * End-of-day close. One trading day's money, tax and drawer reconciliation,
 * on screen and on an 80mm receipt roll. Aggregation lives in
 * {@see ZReportService}; both endpoints read the same figures so the printed
 * slip can never disagree with what the cashier saw.
 */
class ZReportController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ZReportService $reports) {}

    public function show(ZReportRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reports->generate($this->business($request), $request->reportDate(), $request->branchId()),
        ]);
    }

    /** Streams the Z-report inline by default; `?download=1` forces a download. */
    public function pdf(ZReportRequest $request)
    {
        $pdf = $this->reports->pdf($this->business($request), $request->reportDate(), $request->branchId());
        $filename = 'z-report-'.$request->reportDate()->toDateString().'.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    private function business(ZReportRequest $request): Business
    {
        return Business::findOrFail($request->user()->business_id);
    }
}
