<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Export\ExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ExportController extends Controller
{
    public function __construct(protected ExportService $exportService) {}

    public function index(string $module, Request $request): Response
    {
        $this->validatePermission($module);

        $format = $request->query('format', 'csv');
        $filters = $request->only([
            'sort', 'per_page', 'page', 'search',
        ]);

        if ($format === 'pdf') {
            $pdfData = $this->exportService->exportPdf($module, $filters);

            $filename = "{$module}-export-".now()->format('Y-m-d').'.pdf';

            return response()->streamDownload(
                function () use ($pdfData) {
                    echo $pdfData;
                },
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "inline; filename={$filename}",
                ]
            );
        }

        $data = $this->exportService->exportCsv($module, $filters);

        return response()->streamDownload(
            function () use ($data) {
                echo $data;
            },
            200,
            ['Content-Type' => 'text/csv; charset=utf-8']
        );
    }

    protected function validatePermission(string $module): void
    {
        $action = 'export';
        $slug = "{$module}.{$action}";

        if (! auth()->user()->can($slug)) {
            throw new ValidationException([
                'message' => ["Unauthorized. Missing permission: {$slug}."],
            ])->status(403);
        }
    }
}
