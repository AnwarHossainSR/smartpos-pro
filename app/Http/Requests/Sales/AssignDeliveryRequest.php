<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class AssignDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `zone_id` is deliberately not checked with `exists`: an unknown zone should
     * surface as a 404 from the service's findOrFail, not a 422. Missing is a 422.
     */
    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'integer'],
        ];
    }
}
