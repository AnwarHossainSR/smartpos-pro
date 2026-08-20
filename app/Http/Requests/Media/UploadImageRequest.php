<?php

namespace App\Http\Requests\Media;

use App\Services\Media\MediaService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Single-slot image uploads (a product photo, a brand logo, a profile picture).
 *
 * The collection is declared on the route as a default rather than guessed from the
 * URL, so the size and mime limits a request is held to are visible next to the
 * route that accepts it:
 *
 *     Route::post('brands/{brand}/logo', ...)->defaults('media_collection', 'brand.logo');
 */
class UploadImageRequest extends FormRequest
{
    /** Authorisation is the route's own `permission:*` guard. */
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
        return (string) $this->route()->defaults['media_collection'];
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
