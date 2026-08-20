"use client";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { MediaField } from "@/components/ui/media-field";
import { Skeleton } from "@/components/ui/skeleton";
import { api } from "@/lib/api";
import { useAuth } from "@/stores/auth";
import { useQuery } from "@tanstack/react-query";

/** One resolved slot as `GET /settings/business` returns it. */
interface MediaSlot {
  path: string | null;
  url: string | null;
  urls: Record<string, string> | null;
}

interface BusinessMedia {
  logo: MediaSlot;
  "logo-dark": MediaSlot;
  favicon: MediaSlot;
  "receipt-logo": MediaSlot;
  cover: MediaSlot;
}

/**
 * The slots, in the order a shop owner would work through them: the one that shows
 * up everywhere first, the situational ones after.
 *
 * `collection` must match a key in the backend's `config/media.php`; `slot` is the
 * URL segment the upload endpoint is keyed by. Both come from the same server-side
 * declaration (App\Support\Media\BusinessMediaSlots), so a slot added there needs
 * only a row here to surface.
 */
const SLOTS = [
  {
    slot: "logo",
    collection: "business.logo",
    label: "Logo",
    aspect: "square" as const,
    hint: "Shown in the sidebar, on invoices, and in emails. A transparent PNG or SVG sits best on both light and dark backgrounds.",
  },
  {
    slot: "logo-dark",
    collection: "business.logo_dark",
    label: "Logo for dark backgrounds",
    aspect: "square" as const,
    hint: "Optional. Only needed if your main logo is dark ink that disappears in dark mode.",
  },
  {
    slot: "receipt-logo",
    collection: "business.receipt_logo",
    label: "Receipt logo",
    aspect: "wide" as const,
    hint: "Printed at the top of thermal receipts. Thermal printers are black and white at low resolution, so a plain high-contrast mark prints far better than a colour one.",
  },
  {
    slot: "favicon",
    collection: "business.favicon",
    label: "Favicon",
    aspect: "square" as const,
    hint: "The small icon in the browser tab. Square, and legible at 32 pixels.",
  },
  {
    slot: "cover",
    collection: "business.cover",
    label: "Storefront cover",
    aspect: "wide" as const,
    hint: "Used on the customer portal header and as the link preview image when you share an invoice.",
  },
] as const;

/**
 * Business branding.
 *
 * Every field uploads on selection rather than on a form submit — there is no other
 * state to save alongside an image, and deferring would surface an upload failure
 * long after the user picked the file.
 */
export function BrandingCard() {
  const { can } = useAuth();
  const editable = can("settings.update");

  const { data, isLoading, isError, refetch } = useQuery<{ data: { media: BusinessMedia } }>({
    queryKey: ["business-settings"],
    queryFn: () => api<{ data: { media: BusinessMedia } }>("/settings/business"),
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle>Branding</CardTitle>
      </CardHeader>
      <CardContent className="space-y-6">
        <p className="text-sm text-[var(--muted)]">
          Images are resized and converted on upload, so you can drop in whatever the designer
          sent without preparing sizes yourself.
        </p>

        {isLoading && (
          <div className="space-y-6">
            {SLOTS.map((s) => (
              <div key={s.slot} className="flex items-start gap-3">
                <Skeleton className="h-24 w-24 rounded-[var(--radius)]" />
                <div className="flex-1 space-y-2">
                  <Skeleton className="h-4 w-32" />
                  <Skeleton className="h-3 w-full max-w-sm" />
                </div>
              </div>
            ))}
          </div>
        )}

        {isError && (
          <div className="space-y-2 text-sm">
            <p className="text-[var(--color-danger)]">Could not load your branding.</p>
            <button
              type="button"
              onClick={() => refetch()}
              className="text-[var(--primary)] underline underline-offset-4"
            >
              Try again
            </button>
          </div>
        )}

        {data &&
          SLOTS.map((s) => (
            <MediaField
              key={s.slot}
              collection={s.collection}
              endpoint={`/settings/business/media/${s.slot}`}
              label={s.label}
              hint={s.hint}
              aspect={s.aspect}
              url={data.data.media?.[s.slot]?.url ?? null}
              disabled={!editable}
              invalidate={[["business-settings"], ["me"]]}
            />
          ))}

        {!editable && (
          <p className="text-xs text-[var(--muted)]">
            You need the “settings.update” permission to change branding.
          </p>
        )}
      </CardContent>
    </Card>
  );
}
