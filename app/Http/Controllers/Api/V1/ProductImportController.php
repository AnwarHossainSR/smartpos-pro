<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Imports\ProductImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductImportController extends Controller
{
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:csv,xlsx',
            'dry_run' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $dryRun = $request->boolean('dry_run');

        if ($dryRun) {
            $import = new ProductImport;
            $result = $import->collection($request->file->get());

            if (isset($result['errors'])) {
                return response()->json([
                    'dry_run' => true,
                    'rows_valid' => 0,
                    'rows_invalid' => count($result['errors']),
                    'errors' => $result['errors'],
                ]);
            }

            return response()->json([
                'dry_run' => true,
                'rows_valid' => $result['rows_valid'],
                'rows_invalid' => $result['rows_invalid'],
                'errors' => [],
            ]);
        }

        $import = new ProductImport;
        $result = $import->collection($request->file->get());

        if (isset($result['errors'])) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed',
                'errors' => $result['errors'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "{$result['rows_valid']} products imported successfully",
            'rows_valid' => $result['rows_valid'],
            'rows_invalid' => $result['rows_invalid'],
        ]);
    }

    public function template()
    {
        return response()->download(storage_path('app/public/products_import_template.csv'));
    }
}
