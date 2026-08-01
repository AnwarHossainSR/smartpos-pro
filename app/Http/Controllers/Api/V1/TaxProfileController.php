<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaxProfileResource;
use App\Models\TaxProfile;

/** Read-only: profiles are a global jurisdiction catalogue, not tenant-owned. */
class TaxProfileController extends Controller
{
    public function index()
    {
        return TaxProfileResource::collection(
            TaxProfile::where('is_active', true)->orderBy('name')->get()
        );
    }

    public function show(TaxProfile $taxProfile): TaxProfileResource
    {
        return new TaxProfileResource($taxProfile);
    }
}
