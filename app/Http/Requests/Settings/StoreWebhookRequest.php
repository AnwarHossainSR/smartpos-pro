<?php

namespace App\Http\Requests\Settings;

use App\Services\Webhooks\WebhookService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $webhookId = $this->route('webhook')?->id;

        return [
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(WebhookService::EVENTS)],
            'is_active' => ['boolean'],
        ];
    }
}
