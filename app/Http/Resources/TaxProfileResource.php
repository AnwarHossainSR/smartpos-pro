<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'country_code' => $this->country_code,
            'sourcing' => $this->sourcing,
            'rounding_scope' => $this->rounding_scope,
            'rounding_mode' => $this->rounding_mode,
            'price_display' => $this->price_display,
            'tax_number_label' => $this->tax_number_label,
            'tax_number_pattern' => $this->tax_number_pattern,
            'required_invoice_fields' => $this->required_invoice_fields ?? [],
            'is_active' => (bool) $this->is_active,
        ];
    }
}
