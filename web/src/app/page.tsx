import type { Metadata } from "next";
import Link from "next/link";
import { Button } from "@/components/ui/button";
import { SessionLink } from "@/components/landing/session-link";
import { JsonLd } from "@/components/seo/json-ld";
import { faqLd } from "@/lib/seo";

export const metadata: Metadata = {
  title: "SmartPOS Pro — Run the whole shop from one counter",
  description:
    "Point of sale, live stock, purchasing, and double-entry books for independent retailers. One system, every branch — free to start.",
  alternates: { canonical: "/" },
  robots: { index: true, follow: true },
};

/** The hero receipt. Each row prints in sequence, like paper leaving a printer. */
const receipt = [
  { label: "Counter sales", value: "instant" },
  { label: "Stock on hand", value: "live" },
  { label: "Purchase orders", value: "tracked" },
  { label: "Customer credit", value: "watched" },
  { label: "Day-end books", value: "closed" },
];

/** Modules a shop actually gets, in the order they show up in the sidebar. */
const modules = [
  { name: "POS terminal", detail: "Barcode, split tender, held carts, offline queue", tag: "pos" },
  { name: "Inventory", detail: "Per-branch stock, transfers, adjustments, reorder points", tag: "stock" },
  { name: "Purchasing", detail: "Suppliers, purchase orders, goods receipt, returns", tag: "buy" },
  { name: "Accounting", detail: "Double-entry journals, ledgers, trial balance", tag: "books" },
  { name: "Reports", detail: "Sales, margin, stock valuation, cash position", tag: "data" },
  { name: "Loyalty", detail: "Points, coupons, customer history, store credit", tag: "crm" },
];

/**
 * Rendered on the page *and* emitted as FAQPage structured data below.
 *
 * The two must stay in step: schema describing answers a visitor cannot see is
 * cloaking, and gets the rich result revoked rather than granted. Driving both from
 * this one array is what guarantees they cannot drift.
 */
const faqs = [
  {
    q: "Does SmartPOS Pro work with more than one shop or branch?",
    a: "Yes. Stock, sales, and staff are tracked per branch, and transfers move stock between them. Every branch reports into one set of books, so you close the day once rather than per location.",
  },
  {
    q: "What happens when the internet goes down mid-sale?",
    a: "The till keeps selling. Sales are queued locally and sync automatically once the connection returns, so a dropped line never stops the queue at the counter.",
  },
  {
    q: "Do I need separate accounting software?",
    a: "No. Sales, purchases, and expenses post double-entry journals as they happen, and the ledgers, trial balance, profit and loss, and balance sheet are built in.",
  },
  {
    q: "Can it handle my country's tax rules?",
    a: "Tax is configured per market as profiles, rates, groups, and zones, including multi-component taxes such as CGST and SGST split, and prices can display inclusive or exclusive of tax.",
  },
  {
    q: "What hardware do I need?",
    a: "Any browser on a laptop, tablet, or till. Barcode scanners work as keyboard input, and receipts print to standard 80mm thermal printers.",
  },
  {
    q: "What does it cost?",
    a: "There is a free plan with unlimited products and no card required. Paid plans add branches, users, and higher limits.",
  },
] as const;

export default function Home() {
  return (
    <div className="min-h-screen">
      {/* Mirrors the FAQ rendered further down — same array, so the two cannot diverge. */}
      <JsonLd data={faqLd(faqs)} />

      {/* ---- Nav ---- */}
      <header className="sticky top-0 z-40 border-b border-[var(--border)] bg-[var(--bg)]/85 backdrop-blur">
        <nav className="mx-auto flex max-w-6xl items-center justify-between px-6 py-3.5">
          <Link href="/" className="flex items-center gap-2.5">
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-[var(--primary)] text-sm font-bold text-[var(--primary-fg)]">
              S
            </span>
            <span className="text-[0.95rem] font-semibold tracking-tight">SmartPOS Pro</span>
          </Link>
          <SessionLink />
        </nav>
      </header>

      <main>
        {/* ---- Hero: the argument, and the receipt that makes it ---- */}
        <section className="mx-auto grid max-w-6xl gap-14 px-6 pt-16 pb-20 md:pt-24 lg:grid-cols-[1.05fr_auto] lg:gap-20">
          <div className="max-w-xl">
            <p className="font-[family-name:var(--font-mono)] text-xs tracking-[0.18em] text-[var(--muted)] uppercase">
              Retail operating system
            </p>
            <h1 className="mt-5 text-4xl leading-[1.06] font-semibold tracking-[-0.03em] text-balance sm:text-5xl md:text-[3.4rem]">
              Run the whole shop from one counter.
            </h1>
            <p className="mt-6 text-lg leading-relaxed text-[var(--muted)] text-pretty">
              Sell, restock, pay suppliers, and close the books in the same system. Every
              branch reports to one set of numbers, so you stop reconciling spreadsheets at
              midnight.
            </p>

            <div className="mt-9 flex flex-wrap items-center gap-3">
              <Button asChild size="lg">
                <Link href="/register">Start free</Link>
              </Button>
              <Button asChild variant="outline" size="lg">
                <Link href="/login">Sign in</Link>
              </Button>
            </div>

            <p className="mt-5 font-[family-name:var(--font-mono)] text-xs text-[var(--muted)]">
              No card required · Unlimited products on every plan
            </p>
          </div>

          {/* The receipt */}
          <div className="w-full max-w-[21rem] justify-self-center lg:justify-self-end">
            <div className="rcpt-tear rcpt-tear--top" aria-hidden="true" />
            <div className="bg-[var(--surface)] px-7 py-8 shadow-[0_18px_50px_-24px_rgba(16,19,34,0.5)]">
              <div className="rcpt-line text-center" style={{ animationDelay: "60ms" }}>
                <p className="font-[family-name:var(--font-mono)] text-sm font-semibold tracking-[0.22em]">
                  SMARTPOS PRO
                </p>
                <p className="mt-1.5 font-[family-name:var(--font-mono)] text-[0.7rem] tracking-wide text-[var(--muted)]">
                  ONE COUNTER · EVERY BRANCH
                </p>
              </div>

              <div
                className="rcpt-line my-5 border-t border-dashed border-[var(--border)]"
                style={{ animationDelay: "160ms" }}
              />

              <dl className="space-y-3">
                {receipt.map((row, i) => (
                  <div
                    key={row.label}
                    className="rcpt-line flex items-baseline gap-2 font-[family-name:var(--font-mono)] text-[0.8rem]"
                    style={{ animationDelay: `${220 + i * 90}ms` }}
                  >
                    <dt className="whitespace-nowrap text-[var(--fg)]">{row.label}</dt>
                    <span
                      className="min-w-0 flex-1 translate-y-[-0.2em] border-b border-dotted border-[var(--border)]"
                      aria-hidden="true"
                    />
                    <dd className="whitespace-nowrap text-[var(--muted)]">{row.value}</dd>
                  </div>
                ))}
              </dl>

              <div
                className="rcpt-line my-5 border-t border-dashed border-[var(--border)]"
                style={{ animationDelay: `${220 + receipt.length * 90}ms` }}
              />

              <div
                className="rcpt-line flex items-baseline justify-between font-[family-name:var(--font-mono)]"
                style={{ animationDelay: `${290 + receipt.length * 90}ms` }}
              >
                <span className="text-sm font-semibold tracking-[0.14em]">EVENING ADMIN</span>
                <span className="text-xl font-semibold text-[var(--color-success)]">0:00</span>
              </div>

              <p
                className="rcpt-line mt-6 text-center font-[family-name:var(--font-mono)] text-[0.68rem] leading-relaxed tracking-wide text-[var(--muted)]"
                style={{ animationDelay: `${360 + receipt.length * 90}ms` }}
              >
                *** THANK YOU ***
                <br />
                CLOSED OUT BY SMARTPOS
              </p>
            </div>
            <div className="rcpt-tear" aria-hidden="true" />
          </div>
        </section>

        {/* ---- Modules, as ledger rows rather than cards ---- */}
        <section className="border-t border-[var(--border)] bg-[var(--surface)]">
          <div className="mx-auto max-w-6xl px-6 py-20">
            <h2 className="max-w-lg text-2xl font-semibold tracking-[-0.02em] text-balance sm:text-3xl">
              Everything the shop runs on, already included.
            </h2>
            <p className="mt-3 max-w-lg text-[var(--muted)] text-pretty">
              No add-on pricing per module. Turn on what you need as the business grows.
            </p>

            <ul className="mt-12 border-t border-[var(--border)]">
              {modules.map((m) => (
                <li
                  key={m.name}
                  className="grid gap-1.5 border-b border-[var(--border)] py-5 sm:grid-cols-[13rem_1fr_auto] sm:items-baseline sm:gap-6"
                >
                  <h3 className="font-medium tracking-tight">{m.name}</h3>
                  <p className="text-sm text-[var(--muted)]">{m.detail}</p>
                  <span className="font-[family-name:var(--font-mono)] text-[0.7rem] tracking-[0.16em] text-[var(--muted)] uppercase sm:text-end">
                    {m.tag}
                  </span>
                </li>
              ))}
            </ul>
          </div>
        </section>

        {/* ---- Questions people actually ask before signing up ---- */}
        <section aria-labelledby="faq-heading" className="mx-auto max-w-6xl px-6 py-20">
          <h2
            id="faq-heading"
            className="max-w-lg text-2xl font-semibold tracking-[-0.02em] text-balance sm:text-3xl"
          >
            Questions worth answering before you switch.
          </h2>

          <dl className="mt-12 border-t border-[var(--border)]">
            {faqs.map((item) => (
              <div
                key={item.q}
                className="grid gap-2 border-b border-[var(--border)] py-6 sm:grid-cols-[22rem_1fr] sm:gap-10"
              >
                <dt className="font-medium tracking-tight text-pretty">{item.q}</dt>
                <dd className="text-sm leading-relaxed text-[var(--muted)] text-pretty">
                  {item.a}
                </dd>
              </div>
            ))}
          </dl>
        </section>

        {/* ---- Close ---- */}
        <section className="mx-auto max-w-6xl px-6 py-24 text-center">
          <h2 className="text-2xl font-semibold tracking-[-0.02em] text-balance sm:text-3xl">
            Open the till tomorrow morning on SmartPOS.
          </h2>
          <p className="mx-auto mt-3 max-w-md text-[var(--muted)] text-pretty">
            Import your products, set up a branch, and start selling the same day.
          </p>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <Button asChild size="lg">
              <Link href="/register">Start free</Link>
            </Button>
            <Button asChild variant="outline" size="lg">
              <Link href="/login">Sign in</Link>
            </Button>
          </div>
        </section>
      </main>

      <footer className="border-t border-[var(--border)]">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-6 py-7">
          <p className="font-[family-name:var(--font-mono)] text-xs tracking-wide text-[var(--muted)]">
            SmartPOS Pro
          </p>
          <p className="font-[family-name:var(--font-mono)] text-xs tracking-wide text-[var(--muted)]">
            Point of sale · Inventory · Accounting
          </p>
        </div>
      </footer>
    </div>
  );
}
