export const SITE_NAME = "SmartPOS Pro";
export const SITE_DESCRIPTION =
  "SmartPOS Pro is a multi-tenant point-of-sale and retail management platform: sell fast, track stock, manage suppliers and keep your books in one place.";
export const SITE_OG_IMAGE = "/og-image.png";

export interface RouteSeo {
  /** Page title, without the site-name suffix. */
  title: string;
  description?: string;
  /** Keep the page out of search results — true for anything behind auth. */
  noIndex?: boolean;
}

export interface RouteHandle {
  seo?: RouteSeo;
}

export function formatTitle(title?: string): string {
  if (!title || title === SITE_NAME) return `${SITE_NAME} — Point of Sale, Inventory & Accounting`;
  return `${title} · ${SITE_NAME}`;
}

function setMeta(selector: string, attr: "name" | "property", key: string, content: string) {
  let el = document.head.querySelector<HTMLMetaElement>(selector);
  if (!el) {
    el = document.createElement("meta");
    el.setAttribute(attr, key);
    document.head.appendChild(el);
  }
  el.setAttribute("content", content);
}

function setLink(rel: string, href: string) {
  let el = document.head.querySelector<HTMLLinkElement>(`link[rel="${rel}"]`);
  if (!el) {
    el = document.createElement("link");
    el.setAttribute("rel", rel);
    document.head.appendChild(el);
  }
  el.setAttribute("href", href);
}

/**
 * Rewrites the document head in place so there is never more than one of each tag.
 * Absolute URLs are derived at runtime because the app is served from several hosts.
 */
export function applySeo(seo: RouteSeo, pathname: string): void {
  const title = formatTitle(seo.title);
  const description = seo.description ?? SITE_DESCRIPTION;
  const origin = window.location.origin;
  const canonical = `${origin}${pathname}`;

  document.title = title;

  setMeta('meta[name="description"]', "name", "description", description);
  setMeta(
    'meta[name="robots"]',
    "name",
    "robots",
    seo.noIndex ? "noindex, nofollow" : "index, follow",
  );

  setMeta('meta[property="og:title"]', "property", "og:title", title);
  setMeta('meta[property="og:description"]', "property", "og:description", description);
  setMeta('meta[property="og:url"]', "property", "og:url", canonical);
  setMeta('meta[property="og:image"]', "property", "og:image", `${origin}${SITE_OG_IMAGE}`);

  setMeta('meta[name="twitter:title"]', "name", "twitter:title", title);
  setMeta('meta[name="twitter:description"]', "name", "twitter:description", description);
  setMeta('meta[name="twitter:image"]', "name", "twitter:image", `${origin}${SITE_OG_IMAGE}`);

  setLink("canonical", canonical);
}
