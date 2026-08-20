import type { MetadataRoute } from "next";
import { PUBLIC_ROUTES, SITE_URL } from "@/lib/seo";

/**
 * Only the public, unauthenticated routes belong here — everything under `(app)`,
 * `/portal` and `/order` is per-tenant or token-addressed, noindex'd at the page
 * level, and would be a landing-page-shaped redirect to `/login` anyway.
 *
 * Driven off PUBLIC_ROUTES so this file and `robots.ts` cannot disagree about which
 * surface is public.
 */
export default function sitemap(): MetadataRoute.Sitemap {
  const now = new Date();

  return PUBLIC_ROUTES.map((route) => ({
    url: `${SITE_URL}${route.path}`,
    lastModified: now,
    changeFrequency: route.changeFrequency,
    priority: route.priority,
  }));
}
