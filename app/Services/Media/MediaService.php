<?php

namespace App\Services\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use Throwable;

/**
 * The one place an image enters or leaves the app.
 *
 * Everything uploaded is re-encoded to WebP at a capped size before it is written,
 * which normalises the format, bounds what a tenant can cost us in storage, and
 * strips EXIF (a phone photo of a shelf carries the GPS coordinates of the shop).
 * The bytes that reach the bucket are therefore never the bytes the client sent —
 * a deliberate property, since a re-encode also neutralises anything smuggled into
 * a file that merely claims to be an image.
 *
 * Stored paths are *directories*, not files: `media/{scope}/{collection}/{ulid}`,
 * holding one object per variant. Callers persist that prefix and ask for a variant
 * at read time, so adding a size later does not require a schema change.
 *
 * @see config/media.php for the per-collection size, mime, and variant rules.
 */
class MediaService
{
    /** Quality for the WebP re-encode. 82 is visually lossless for product photography. */
    private const WEBP_QUALITY = 82;

    private ?ImageManager $images = null;

    /**
     * Store an upload and return the path to persist on the owning model.
     *
     * @param  string  $collection  A key from `config('media.collections')`.
     * @param  string  $scope  Usually the tenant id — the first path segment, so a
     *                         bucket listing is grouped by tenant and a tenant's
     *                         media can be deleted wholesale on account closure.
     */
    public function store(UploadedFile $file, string $collection, string $scope): string
    {
        $config = $this->collection($collection);
        $prefix = sprintf('media/%s/%s/%s', $scope, $collection, (string) Str::ulid());

        // SVG cannot be raster re-encoded, so it takes the sanitiser path instead and
        // is written once, with no variants — it is resolution-independent anyway.
        // Note this returns the *file* path, not the prefix: an extension on the
        // stored path is what later tells the reader there are no variants to ask
        // for, without having to probe the bucket to find out.
        if ($this->isSvg($file)) {
            $key = "{$prefix}/original.svg";

            $this->put($key, SvgSanitizer::clean(
                (string) file_get_contents($file->getRealPath())
            ), 'image/svg+xml');

            return $key;
        }

        foreach ($config['variants'] as $variant => $box) {
            try {
                $encoded = $this->images()
                    ->read($file->getRealPath())
                    // scaleDown, not resize: preserves aspect ratio and refuses to
                    // upscale, so a 40px logo stays 40px instead of turning to mush.
                    ->scaleDown($box['width'], $box['height'])
                    ->toWebp(self::WEBP_QUALITY);
            } catch (DecoderException) {
                // The `image` validation rule already rejects anything that is not a
                // real image, so reaching here means a file that passed sniffing but
                // the decoder still cannot read — a truncated upload, or a format GD
                // was not built with. Either way it is the client's input that is at
                // fault, so it comes back as a 422 rather than a 500.
                throw ValidationException::withMessages([
                    'file' => "That file could not be read as an image. Try re-saving it as {$config['mimes'][0]}.",
                ]);
            }

            $this->put("{$prefix}/{$variant}.webp", (string) $encoded, 'image/webp');
        }

        return $prefix;
    }

    /**
     * Write the new image first, and only delete the old one once that succeeded.
     * The reverse order would leave the model pointing at nothing if the bucket
     * write failed — a visibly broken logo is worse than a briefly orphaned object.
     */
    public function replace(?string $old, UploadedFile $file, string $collection, string $scope): string
    {
        $path = $this->store($file, $collection, $scope);

        $this->delete($old);

        return $path;
    }

    /** Remove every variant. Safe to call with null or an already-deleted path. */
    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        try {
            // A path with an extension is a single file: an SVG, or an upload from
            // before variants existed. The SVG case still sits inside a per-upload
            // ULID directory, so the directory goes with it; the legacy case does
            // not, which is why the file itself is removed either way.
            if (pathinfo($path, PATHINFO_EXTENSION)) {
                $this->disk()->delete($path);

                if (str_contains(dirname($path), '/media/') || str_starts_with($path, 'media/')) {
                    $this->disk()->deleteDirectory(dirname($path));
                }

                return;
            }

            $this->disk()->deleteDirectory($path);
        } catch (Throwable) {
            // A failed cleanup must never fail the request that triggered it. The
            // object is unreferenced from this point on; lifecycle rules on the
            // bucket sweep the remainder.
        }
    }

    /**
     * Public URL for one variant, or null when nothing is stored.
     *
     * Deliberately does not check that the object exists. This runs once per image
     * per row of a product list, and a HEAD request against the bucket for each one
     * would turn a catalogue page into hundreds of round trips. The key is derivable
     * from the stored path, so it is derived.
     *
     * The consequence: adding a new variant to `config('media.collections')` does not
     * retroactively create it for images uploaded before the change, and asking for
     * that variant yields a URL that 404s. Adding a size therefore means re-uploading
     * or backfilling, not just editing config.
     */
    public function url(?string $path, string $variant = 'original'): ?string
    {
        if (! $path) {
            return null;
        }

        // A path carrying an extension is a single file with no variants — either an
        // SVG, or an upload from before this service stored variant directories.
        $key = pathinfo($path, PATHINFO_EXTENSION)
            ? $path
            : "{$path}/{$variant}.webp";

        return $this->publicUrl($key);
    }

    /**
     * Every variant of an image, keyed by name, for handing a client a srcset.
     *
     * @return array<string, string>|null
     */
    public function urls(?string $path, string $collection): ?array
    {
        if (! $path) {
            return null;
        }

        if (pathinfo($path, PATHINFO_EXTENSION)) {
            $url = $this->publicUrl($path);

            return $url ? ['original' => $url] : null;
        }

        $urls = [];

        foreach (array_keys($this->collection($collection)['variants']) as $variant) {
            if ($url = $this->url($path, $variant)) {
                $urls[$variant] = $url;
            }
        }

        return $urls ?: null;
    }

    /**
     * Validation rules for a collection's upload field.
     *
     * `image` is what does the real work here: it checks the decoded contents
     * rather than the filename, so a .php renamed to .png fails before it is ever
     * handed to the encoder.
     *
     * @return array<int, string>
     */
    public function rules(string $collection): array
    {
        $config = $this->collection($collection);

        $rules = ['required', 'file', 'mimes:'.implode(',', $config['mimes']), 'max:'.$config['max_kb']];

        // `image` rejects SVG outright (it is not a raster format), so collections
        // that accept vector logos rely on the mimes check plus the sanitiser.
        if (! in_array('svg', $config['mimes'], true)) {
            array_splice($rules, 2, 0, 'image');
        }

        return $rules;
    }

    /** @return array{label: string, max_kb: int, mimes: array<int, string>, variants: array<string, array{width: int, height: int}>} */
    public function collection(string $collection): array
    {
        // Indexed, not `config('media.collections.'.$collection)`: collection keys
        // contain a dot, and config() treats a dot as a level separator, so the
        // dotted form silently looks for a `product` level with an `image` under it.
        $config = config('media.collections')[$collection] ?? null;

        if (! is_array($config)) {
            throw new InvalidArgumentException("Unknown media collection [{$collection}].");
        }

        return $config;
    }

    protected function disk(): Filesystem
    {
        return Storage::disk(config('media.disk'));
    }

    private function driver(): ?string
    {
        return $this->diskConfig('driver');
    }

    private function diskConfig(string $key): mixed
    {
        return config('filesystems.disks.'.config('media.disk').'.'.$key);
    }

    private function images(): ImageManager
    {
        return $this->images ??= ImageManager::gd();
    }

    private function put(string $key, string $contents, string $contentType): void
    {
        $options = [
            'ContentType' => $contentType,
            'CacheControl' => config('media.cache_control'),
        ];

        // Laravel turns a `visibility` option into an S3 ACL header, and R2 has no
        // ACLs — a bucket there is public or not at the bucket level. Sending one
        // is at best ignored and at worst a 400, so it goes only to local disks,
        // where it is what makes the file readable through the storage symlink.
        if ($this->driver() === 'local') {
            $options['visibility'] = 'public';
        }

        $this->disk()->put($key, $contents, $options);
    }

    /**
     * A bucket with a configured `url` is public and gets a permanent, edge-cacheable
     * link. A bucket without one is private, so the only way to hand the browser an
     * image is a time-limited signed URL.
     */
    private function publicUrl(string $key): ?string
    {
        $disk = $this->disk();

        if ($this->diskConfig('url') || $this->driver() === 'local') {
            return $disk->url($key);
        }

        try {
            return $disk->temporaryUrl($key, now()->addSeconds((int) config('media.signed_url_ttl')));
        } catch (Throwable) {
            return null;
        }
    }

    private function isSvg(UploadedFile $file): bool
    {
        return strtolower((string) $file->getClientOriginalExtension()) === 'svg'
            || $file->getMimeType() === 'image/svg+xml';
    }
}
