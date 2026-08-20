<?php

namespace App\Support\Media;

/**
 * The image slots on a business profile.
 *
 * One declaration, read by four places that must agree: the router (which slots
 * exist), the upload request (what a slot accepts), the controller (which column to
 * write), and the settings payload (what to hand back). Adding a slot here is the
 * whole change — nothing else enumerates them.
 */
final class BusinessMediaSlots
{
    /** @var array<string, array{column: string, collection: string}> */
    public const ALL = [
        'logo' => ['column' => 'logo_path', 'collection' => 'business.logo'],
        'logo-dark' => ['column' => 'logo_dark_path', 'collection' => 'business.logo_dark'],
        'favicon' => ['column' => 'favicon_path', 'collection' => 'business.favicon'],
        'receipt-logo' => ['column' => 'receipt_logo_path', 'collection' => 'business.receipt_logo'],
        'cover' => ['column' => 'cover_path', 'collection' => 'business.cover'],
    ];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    public static function column(string $slot): string
    {
        return self::ALL[$slot]['column'];
    }

    public static function collection(string $slot): string
    {
        return self::ALL[$slot]['collection'];
    }
}
