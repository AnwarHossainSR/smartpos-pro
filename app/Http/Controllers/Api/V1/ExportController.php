<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Export\ExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ExportController extends Controller
{
    public function __construct(protected ExportService $exportService) {}

    public function index(string $module, Request $request): StreamedResponse
    {
        $this->validatePermission($module);

        $format = $request->query('format', 'csv');

        if ($format === 'pdf') {
            $pdfData = $this->exportService->exportPdf($module, $request);

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

        $data = $this->exportService->exportCsv($module, $request);

        return response()->streamDownload(
            function () use ($data) {
                echo $data;
            },
            200,
            ['Content-Type' => 'text/csv; charset=utf-8']
        );
    }

    /**
     * The module is a URL segment, so the `<module>.export` slug can only be known
     * here — a static `permission:` middleware alias cannot see it.
     */
    protected function validatePermission(string $module): void
    {
        $slug = "{$module}.export";

        if (! auth()->user()?->can($slug)) {
            throw new AccessDeniedHttpException("Unauthorized. Missing permission: {$slug}.");
        }
    }
}
