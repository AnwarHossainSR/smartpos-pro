<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\UploadBusinessMediaRequest;
use App\Http\Requests\Media\UploadImageRequest;
use App\Models\Brand;
use App\Models\Business;
use App\Services\Media\MediaService;
use App\Support\Media\BusinessMediaSlots;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Image slots that hang off a record rather than being a record in their own right:
 * the business's branding set, a brand's logo, a user's profile photo.
 *
 * Every action here is a straight delegate to MediaService — the controller's only
 * jobs are picking the column to write and the tenant to scope the object key to.
 */
class MediaController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly MediaService $media) {}

    /** POST /v1/settings/business/media/{slot} */
    public function uploadBusiness(UploadBusinessMediaRequest $request): JsonResponse
    {
        $business = $this->business($request);
        $column = $request->column();

        $path = $this->media->replace(
            $business->{$column},
            $request->file('file'),
            $request->collection(),
            $business->tenant_id,
        );

        $business->update([$column => $path]);

        return response()->json([
            'data' => $this->payload($path, $request->collection(), $request->slot()),
        ]);
    }

    /**
     * DELETE /v1/settings/business/media/{slot}
     *
     * Takes a plain Request rather than the upload request: there is no file on
     * a delete, and reusing the upload's rules would demand one.
     */
    public function destroyBusiness(Request $request, string $slot): JsonResponse
    {
        $business = $this->business($request);
        $column = BusinessMediaSlots::column($slot);

        $this->media->delete($business->{$column});
        $business->update([$column => null]);

        return response()->json([
            'data' => ['slot' => $slot, 'path' => null, 'url' => null, 'urls' => null],
        ]);
    }

    /** POST /v1/brands/{brand}/logo */
    public function uploadBrandLogo(UploadImageRequest $request, Brand $brand): JsonResponse
    {
        $path = $this->media->replace(
            $brand->logo_path,
            $request->file('file'),
            $request->collection(),
            $brand->tenant_id,
        );

        $brand->update(['logo_path' => $path]);

        return response()->json(['data' => $this->payload($path, $request->collection())]);
    }

    /** DELETE /v1/brands/{brand}/logo */
    public function destroyBrandLogo(Brand $brand): JsonResponse
    {
        $this->media->delete($brand->logo_path);
        $brand->update(['logo_path' => null]);

        return response()->json(['data' => ['path' => null, 'url' => null, 'urls' => null]]);
    }

    /**
     * POST /v1/me/avatar
     *
     * Deliberately ungated by any permission: this is the caller changing their own
     * photo, and every authenticated user can do that regardless of role.
     */
    public function uploadAvatar(UploadImageRequest $request): JsonResponse
    {
        $user = $request->user();

        $path = $this->media->replace(
            $user->avatar_path,
            $request->file('file'),
            $request->collection(),
            // A platform super admin belongs to no tenant, so their avatar lands in
            // a shared prefix rather than under a tenant that does not exist.
            $user->tenant_id ?? 'shared',
        );

        $user->forceFill(['avatar_path' => $path])->save();

        return response()->json(['data' => $this->payload($path, $request->collection())]);
    }

    /** DELETE /v1/me/avatar */
    public function destroyAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->media->delete($user->avatar_path);
        $user->forceFill(['avatar_path' => null])->save();

        return response()->json(['data' => ['path' => null, 'url' => null, 'urls' => null]]);
    }

    /**
     * The catalogue the upload widgets read their client-side limits from, so the
     * browser rejects an oversized file before spending the upload, and the two
     * sides can never disagree about what is allowed.
     *
     * GET /v1/media/collections
     */
    public function collections(): JsonResponse
    {
        $collections = collect(config('media.collections'))
            ->map(fn (array $config, string $key) => [
                'key' => $key,
                'label' => $config['label'],
                'max_kb' => $config['max_kb'],
                'mimes' => $config['mimes'],
                'accept' => collect($config['mimes'])
                    ->map(fn (string $ext) => match ($ext) {
                        'jpg', 'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'webp' => 'image/webp',
                        'svg' => 'image/svg+xml',
                        'ico' => 'image/x-icon',
                        default => "image/{$ext}",
                    })
                    ->unique()
                    ->values()
                    ->implode(','),
                'variants' => $config['variants'],
            ])
            ->values();

        return response()->json(['data' => $collections]);
    }

    /** @return array<string, mixed> */
    private function payload(string $path, string $collection, ?string $slot = null): array
    {
        return array_filter([
            'slot' => $slot,
            'path' => $path,
            'url' => $this->media->url($path),
            'urls' => $this->media->urls($path, $collection),
        ], fn ($value) => $value !== null);
    }

    private function business(Request $request): Business
    {
        return Business::findOrFail($request->user()->business_id);
    }
}
