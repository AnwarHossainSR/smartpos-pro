<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media Disk
    |--------------------------------------------------------------------------
    |
    | Where uploaded images live. Production points this at `r2` (Cloudflare R2,
    | configured in config/filesystems.php); local development and the test suite
    | fall back to the `public` disk so neither needs credentials to run.
    |
    */

    'disk' => env('MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Signed URL Lifetime
    |--------------------------------------------------------------------------
    |
    | Only consulted when the media disk has no public `url` configured — i.e. a
    | private bucket. Public buckets (an r2.dev subdomain or a custom domain bound
    | to the bucket) hand out permanent URLs instead, which are cacheable by the
    | browser and by Cloudflare's edge.
    |
    */

    'signed_url_ttl' => (int) env('MEDIA_SIGNED_URL_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Cache-Control
    |--------------------------------------------------------------------------
    |
    | Sent with every object written. Filenames are content-addressed by a fresh
    | ULID on each upload, so an object is never rewritten in place and can be
    | cached immutably — a replaced logo gets a new key, not a new body.
    |
    */

    'cache_control' => env('MEDIA_CACHE_CONTROL', 'public, max-age=31536000, immutable'),

    /*
    |--------------------------------------------------------------------------
    | Collections
    |--------------------------------------------------------------------------
    |
    | One entry per kind of image the app accepts. `max_kb` and `mimes` drive
    | validation; `variants` drive what gets written. Every variant is re-encoded
    | server-side, which normalises the format, caps the dimensions, and strips
    | EXIF (including any GPS tag a phone camera attached) as a side effect.
    |
    | `width`/`height` are a bounding box — aspect ratio is always preserved and
    | images smaller than the box are never upscaled.
    |
    */

    'collections' => [

        'business.logo' => [
            'label' => 'Business logo',
            'max_kb' => 2048,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
            'variants' => [
                'original' => ['width' => 1024, 'height' => 1024],
                'md' => ['width' => 320, 'height' => 320],
                'sm' => ['width' => 96, 'height' => 96],
            ],
        ],

        'business.logo_dark' => [
            'label' => 'Business logo (dark background)',
            'max_kb' => 2048,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
            'variants' => [
                'original' => ['width' => 1024, 'height' => 1024],
                'md' => ['width' => 320, 'height' => 320],
                'sm' => ['width' => 96, 'height' => 96],
            ],
        ],

        'business.favicon' => [
            'label' => 'Favicon',
            'max_kb' => 512,
            'mimes' => ['png', 'webp', 'svg', 'ico'],
            'variants' => [
                'original' => ['width' => 256, 'height' => 256],
                'sm' => ['width' => 32, 'height' => 32],
            ],
        ],

        /*
         * Thermal printers are 1-bit devices at 203dpi. A 512px-wide bounding box
         * is already more resolution than a 80mm roll can render, and anything
         * larger just slows the print.
         */
        'business.receipt_logo' => [
            'label' => 'Receipt logo',
            'max_kb' => 1024,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
            'variants' => [
                'original' => ['width' => 512, 'height' => 256],
            ],
        ],

        'business.cover' => [
            'label' => 'Storefront cover image',
            'max_kb' => 4096,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
            'variants' => [
                'original' => ['width' => 2048, 'height' => 1152],
                'md' => ['width' => 1200, 'height' => 630],
                'sm' => ['width' => 480, 'height' => 270],
            ],
        ],

        'brand.logo' => [
            'label' => 'Brand logo',
            'max_kb' => 2048,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
            'variants' => [
                'original' => ['width' => 800, 'height' => 800],
                'sm' => ['width' => 96, 'height' => 96],
            ],
        ],

        'product.image' => [
            'label' => 'Product image',
            'max_kb' => 4096,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
            'variants' => [
                'original' => ['width' => 1600, 'height' => 1600],
                'md' => ['width' => 600, 'height' => 600],
                // The POS grid renders dozens of these at once on a till that is
                // often on shop wifi, so it gets its own small variant.
                'sm' => ['width' => 160, 'height' => 160],
            ],
        ],

        'user.avatar' => [
            'label' => 'Profile photo',
            'max_kb' => 2048,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
            'variants' => [
                'original' => ['width' => 512, 'height' => 512],
                'sm' => ['width' => 64, 'height' => 64],
            ],
        ],

    ],

];
