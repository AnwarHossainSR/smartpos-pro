import type { Metadata } from "next";

/**
 * One source of truth for everything that describes the site to a crawler, a link
 * unfurler, or an AI agent. Page files import from here rather than hand-rolling
 * metadata objects, so the title format, the OG defaults, and the canonical host
 * cannot drift apart across forty-odd routes.
 */

/** The public origin. Must be absolute — relative OG image URLs resolve against it. */
export const SITE_URL = (
  process.env.NEXT_PUBLIC_SITE_URL ?? "https://app.smartpospro.com"
).replace(/\/$/, "");

export const SITE = {
  name: "SmartPOS Pro",
  /** Appended after each page title. Kept short: search results truncate near 60 chars. */
  shortDescription:
    "Point of sale, live stock, purchasing, and double-entry books for independent retailers.",
  description:
    "SmartPOS Pro is a multi-branch retail operating system: a fast point-of-sale terminal, real-time inventory across every branch, supplier purchasing, and double-entry accounting that closes the books at day end — in one system.",
  locale: "en_US",
  twitter: "@smartpospro",
} as const;

/**
 * Routes a crawler may index. Everything else is either behind auth or reachable
 * only through a secret token, and is explicitly excluded in `robots.ts`.
 *
 * `changeFrequency` and `priority` are hints, largely ignored by Google now, but
 * still read by smaller crawlers — cheap to be honest about.
 */
export const PUBLIC_ROUTES = [
  { path: "/", changeFrequency: "weekly", priority: 1 },
  { path: "/register", changeFrequency: "monthly", priority: 0.8 },
  { path: "/login", changeFrequency: "yearly", priority: 0.3 },
] as const;

/**
 * Route prefixes that must never be indexed.
 *
 * `/order` and `/portal` matter most and are the least obvious: they are public in
 * the sense that they need no login, but they are reached through a secret token
 * and render a named customer's purchase history. An indexed one is a data leak,
 * not just noise — hence both a robots.txt disallow and a per-page `noindex`, since
 * robots.txt only asks politely and does nothing about a URL someone pastes into a
 * crawlable page.
 */
export const PRIVATE_PREFIXES = [
  "/dashboard", "/pos", "/products", "/sales", "/purchases", "/settings",
  "/reports", "/inventory", "/customers", "/suppliers", "/expenses",
  "/accounting", "/cash-register", "/team", "/loyalty", "/subscription",
  "/notifications", "/audit-log", "/catalog", "/stocktakes", "/tables",
  "/support", "/reorder-suggestions", "/test-data",
  "/order", "/portal",
] as const;

interface PageMetaOptions {
  title: string;
  description?: string;
  /** Path relative to the site root, e.g. `/register`. Sets the canonical URL. */
  path?: string;
  /** Pass `false` for anything behind auth or behind a secret token. */
  index?: boolean;
}

/**
 * Metadata for one page.
 *
 * Titles arrive bare ("Sign in") and are suffixed by the template on the root
 * layout, so callers never repeat the brand name.
 */
export function pageMetadata({
  title,
  description,
  path,
  index = false,
}: PageMetaOptions): Metadata {
  return {
    title,
    ...(description ? { description } : {}),
    ...(path ? { alternates: { canonical: path } } : {}),
    robots: index
      ? { index: true, follow: true }
      : // `nocache` and the googleBot block matter for the token-addressed pages:
        // they stop a URL that leaked into a crawl from being cached or shown as a
        // snippet even after the page itself is gone.
        {
          index: false,
          follow: false,
          nocache: true,
          googleBot: { index: false, follow: false, noimageindex: true },
        },
    ...(index && path
      ? {
          openGraph: {
            type: "website",
            url: `${SITE_URL}${path}`,
            siteName: SITE.name,
            title,
            description: description ?? SITE.shortDescription,
          },
        }
      : {}),
  };
}

/* ------------------------------------------------------------------ structured data */

/**
 * JSON-LD describing the product itself.
 *
 * `SoftwareApplication` is what earns a rich result for a SaaS product, and it is
 * also the block an AI agent is most likely to parse when asked what this site is:
 * it states the category, the platform, and the price in machine-readable form
 * rather than leaving them to be inferred from marketing prose.
 */
export function softwareApplicationLd() {
  return {
    "@context": "https://schema.org",
    "@type": "SoftwareApplication",
    name: SITE.name,
    applicationCategory: "BusinessApplication",
    applicationSubCategory: "Point of Sale",
    operatingSystem: "Web browser, iOS, Android",
    description: SITE.description,
    url: SITE_URL,
    offers: {
      "@type": "Offer",
      price: "0",
      priceCurrency: "USD",
      description: "Free plan with unlimited products; paid plans add branches and users.",
    },
    featureList: [
      "Point-of-sale terminal with barcode scanning, split tender, and held carts",
      "Real-time inventory across multiple branches and warehouses",
      "Supplier purchase orders, goods receipt, and returns",
      "Double-entry accounting with journals, ledgers, and trial balance",
      "Sales, margin, stock valuation, and cash position reporting",
      "Customer loyalty points, coupons, and store credit",
      "Multi-currency and configurable tax profiles per market",
    ],
  };
}

export function organizationLd() {
  return {
    "@context": "https://schema.org",
    "@type": "Organization",
    name: SITE.name,
    url: SITE_URL,
    logo: `${SITE_URL}/icon-512.png`,
    description: SITE.shortDescription,
  };
}

export function webSiteLd() {
  return {
    "@context": "https://schema.org",
    "@type": "WebSite",
    name: SITE.name,
    url: SITE_URL,
    inLanguage: ["en", "bn", "ar", "de", "ja"],
  };
}

/**
 * Only ever built from questions that are also rendered on the page. Structured
 * data that describes content a visitor cannot see is cloaking, and gets the rich
 * result revoked rather than granted.
 */
export function faqLd(faqs: ReadonlyArray<{ q: string; a: string }>) {
  return {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    mainEntity: faqs.map(({ q, a }) => ({
      "@type": "Question",
      name: q,
      acceptedAnswer: { "@type": "Answer", text: a },
    })),
  };
}
