<?php

namespace App\Http\Requests\Support;

use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;

class CreateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'category' => ['nullable', 'string', 'in:'.implode(',', SupportTicket::CATEGORIES)],
            'priority' => ['nullable', 'string', 'in:'.implode(',', SupportTicket::PRIORITIES)],
        ];
    }
}
