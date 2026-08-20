<?php

namespace App\Http\Requests\Media;

use App\Services\Media\MediaService;
use App\Support\Media\BusinessMediaSlots;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload into one of the business branding slots.
 *
 * The slot itself carries no `in:` rule — an unlisted slot 404s at the router,
 * which is constrained from BusinessMediaSlots::keys().
 */
class UploadBusinessMediaRequest extends FormRequest
{
    /** Authorisation is the route's `permission:settings.update` guard. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'file' => app(MediaService::class)->rules($this->collection()),
        ];
    }

    public function collection(): string
    {
        return BusinessMediaSlots::collection($this->slot());
    }

    public function column(): string
    {
        return BusinessMediaSlots::column($this->slot());
    }

    public function slot(): string
    {
        return (string) $this->route('slot');
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $config = app(MediaService::class)->collection($this->collection());

        return [
            'file.max' => "{$config['label']} must be smaller than ".round($config['max_kb'] / 1024, 1).' MB.',
            'file.mimes' => "{$config['label']} must be one of: ".implode(', ', $config['mimes']).'.',
        ];
    }
}
