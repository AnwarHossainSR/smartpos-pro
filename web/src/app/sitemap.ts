import type { MetadataRoute } from "next";

/** Only the public, unauthenticated routes belong here — everything under `(app)` is
 *  per-tenant and noindex'd (see `src/app/(app)/layout.tsx`), so it has nothing to offer a
 *  crawler and would just be a landing-page-shaped 302 to `/login` anyway. */
export default function sitemap(): MetadataRoute.Sitemap {
  const base = process.env.NEXT_PUBLIC_SITE_URL ?? "https://app.smartpospro.com";
  const now = new Date();

  return [
    { url: `${base}/`, lastModified: now, changeFrequency: "weekly", priority: 1 },
    { url: `${base}/login`, lastModified: now, changeFrequency: "yearly", priority: 0.3 },
    { url: `${base}/register`, lastModified: now, changeFrequency: "yearly", priority: 0.5 },
  ];
}
