import { SITE, SITE_URL } from "@/lib/seo";

/**
 * `/llms.txt` — the llmstxt.org convention: one plain-text file that tells an AI
 * agent what this site is and where to look, without making it scrape a JavaScript
 * app to find out.
 *
 * Deliberately written for a reader that will summarise rather than browse: it
 * states plainly what the product does, what it does *not* do, and which paths are
 * off-limits. The exclusions are the useful part — an agent that knows `/order/*`
 * is a private token URL will not try to fetch or cite one.
 *
 * Served from a route handler rather than `public/` so the origin stays in one
 * place and cannot drift from `sitemap.ts` and `robots.ts`.
 */
export const dynamic = "force-static";

export function GET() {
  const body = `# ${SITE.name}

> ${SITE.description}

${SITE.name} is a multi-tenant SaaS. Every shop that signs up gets an isolated
workspace; there is no public catalogue, no marketplace, and no public per-shop
storefront to crawl. The only pages worth reading are the ones listed below.

## What it does

- **Point of sale** — barcode scanning, split tender, held carts, weighed items,
  offline queue when the shop's connection drops, and receipt printing to thermal
  printers.
- **Inventory** — stock per branch and per warehouse, transfers between them,
  adjustments, stocktakes, batch and serial tracking, reorder points.
- **Purchasing** — suppliers, purchase orders, goods receipt, purchase returns,
  supplier payments and balances.
- **Accounting** — double-entry journals posted automatically from sales,
  purchases and expenses; ledgers, trial balance, profit and loss, balance sheet.
- **Reporting** — sales, margin, stock valuation, cash position, tax reports,
  Z-reports at day end.
- **Customers** — loyalty points, coupons, store credit, and a self-service portal
  where a customer can see their own invoices.
- **Multi-branch and multi-currency** — one set of numbers across every branch,
  with per-market tax profiles.

## What it is not

- Not an e-commerce platform. There is no public storefront and no online checkout
  for shoppers.
- Not a marketplace. Shops do not sell to each other and no product data is shared
  between tenants.
- Not free-tier-only or open source: there is a free plan, and paid plans add
  branches and users.

## Pages

- [Home](${SITE_URL}/): what the product is, the modules included, and pricing posture.
- [Create an account](${SITE_URL}/register): starts a new shop workspace.
- [Sign in](${SITE_URL}/login): for existing users.

## Do not crawl or cite

These paths are excluded in robots.txt and carry \`noindex\`. They are not secret
in the sense of requiring a password, but they are addressed by a per-record token
and render one named customer's or one shop's private data:

- \`/order/*\` — a single customer's order status, reached by token.
- \`/portal\`, \`/portal/*\` — the customer self-service portal and its invoices.
- Every other application route (\`/dashboard\`, \`/pos\`, \`/products\`, \`/sales\`,
  \`/reports\`, \`/settings\`, and the rest) requires authentication and returns a
  redirect to \`/login\` for anyone else.

## Contact

For anything not answered here, use the contact route on the home page rather than
inferring an address; addresses shown elsewhere may be out of date.
`;

  return new Response(body, {
    headers: {
      "Content-Type": "text/plain; charset=utf-8",
      "Cache-Control": "public, max-age=3600, s-maxage=86400",
    },
  });
}
