<?php

namespace App\Support\Theme;

/**
 * Single source of truth on the PHP side for the theme values the API will accept.
 *
 * These mirror the web client exactly and must be kept in step with it:
 *   - MODES    ↔ the `Theme` union in `web/src/stores/theme.ts`
 *   - PALETTES ↔ the `PALETTES` array in `web/src/lib/palettes.ts`
 *
 * Keeping the lists here (rather than as literals inside the FormRequest) means adding a
 * palette is a one-line change on the backend, and the validation rules read as intent.
 */
final class ThemeCatalog
{
    /** Colour-scheme modes the web client can render. */
    public const MODES = ['light', 'dark', 'system'];

    /** Palette ids defined in web/src/lib/palettes.ts. */
    public const PALETTES = ['amber', 'indigo', 'emerald', 'ocean', 'violet', 'rose'];

    public const DEFAULT_MODE = 'system';

    public const DEFAULT_PALETTE = 'indigo';
}
