# Migrating the frontend from Next.js to Vite + React Router

**Tracking:** [#104](https://github.com/AnwarHossainSR/smartpos-pro/issues/104) — the
parent issue, with all twelve tasks registered as native sub-issues.
**Execution:** [`ORCHESTRATOR.md`](ORCHESTRATOR.md) carries the orchestrator prompt and
the per-wave review loop.

## Why

SmartPOS Pro's frontend is a point-of-sale terminal behind a login. Marketing lives on a
separate site and demos are handed out personally, so nothing in `web/` needs to be
crawlable, and nothing in it is rendered on a server. Next.js is paying rent as a
full-stack framework in an app that only ever ships a client bundle.

Removing it drops a framework, a build server, a deployment process (`next start` under
PM2), and the entire SEO layer that only existed because the framework offered it.

## What the survey found

The app is already a single-page application wearing a framework costume:

| Signal | Count | Note |
| --- | --- | --- |
| Pages | 45 | 44 are `"use client"`; only the landing page is a Server Component |
| Layouts | 44 | **41 are SEO-only segment layouts** that exist solely to export `metadata` |
| Files importing `next/*` at runtime | 33 | excluding those 41 layouts |
| `next/navigation` call sites | 15 files | `useRouter` 14, `useSearchParams` 5, `usePathname` 3, `useParams` 1 |
| `next/link` | 22 files | only `href` and `className` props are ever used |
| Router methods used | 2 | `router.push` (12), `router.replace` (14) — no `back`, `refresh`, or `prefetch` |
| Route handlers | 1 | `/llms.txt`, deleted with the SEO layer |
| Middleware | 0 | route guarding is client-side in `AppShell` |
| Dynamic routes | 2 | `/order/[token]`, `/portal/invoices/[id]` |
| Server-only APIs | 3 | `next/font/google`, `next/og`, `next/dynamic` — one use each |
| `src` LOC | ~24.8k | |

There is no SSR, no server data fetching, no server actions, no ISR, and no middleware.
The Next-specific surface area is roughly **two hooks and one component**.

## Target stack

| Concern | Now | After |
| --- | --- | --- |
| Build / dev server | Next 16 (Turbopack) | Vite 8 |
| Routing | App Router (file-based) | React Router 8 (`createBrowserRouter`, central route table) |
| Styling | Tailwind v4 via PostCSS | Tailwind v4 via `@tailwindcss/vite` |
| Fonts | `next/font/google` | `@fontsource-variable/*` (self-hosted, offline-safe) |
| Code splitting | `next/dynamic` | `React.lazy` + `Suspense` |
| Env vars | `process.env.NEXT_PUBLIC_*` | `import.meta.env.VITE_*` |
| Output | Node server | Static `dist/`, served by nginx with SPA fallback |
| SEO | 41 layouts, JSON-LD, robots, sitemap, llms.txt, OG image | *(removed)* |

Everything else — React 19, TypeScript strict, TanStack Query, zustand, React Hook Form,
Zod, Tailwind tokens, Lucide, Recharts, the entire `src/components`, `src/features`,
`src/lib`, `src/stores`, `src/i18n` tree — is untouched by the framework swap.

**The backend is not touched at all.**

### Verified package versions

Checked against the registry, not assumed:

```
vite                              8.2.2
react-router                      8.3.0   (peer: react >=19.2.7 — repo has 19.2.8 ✓)
@tailwindcss/vite                 4.3.3
@vitejs/plugin-react              6.1.0   (already a devDependency)
@fontsource-variable/geist        5.3.0
@fontsource-variable/geist-mono   5.3.0
@fontsource-variable/noto-sans-bengali | -jp | -arabic   5.3.0
vite-plugin-pwa                   1.3.0
```

React Router is at **v8**, not v7. The core API (`createBrowserRouter`, `Link`,
`useNavigate`, `useLocation`, `useParams`, `useSearchParams`, `Outlet`, `Navigate`) has
been stable since v6, but agents should check the installed `.d.ts` rather than trust
this document if something does not typecheck. Note the package is `react-router` —
`react-router-dom` is a legacy re-export and must not be installed.

## Decisions taken

**The landing page is deleted, not ported.** Marketing is a separate site. `/` becomes a
redirect: `/dashboard` when a token is present, `/login` otherwise. This removes
`src/app/page.tsx`, `src/components/landing/`, and the FAQ/JSON-LD work with it.

**The route directory structure stays put.** `src/app/(app)/pos/page.tsx` keeps its path;
only the 41 SEO layouts are deleted. A new `src/routes.tsx` becomes the single source of
truth for the route tree. Renaming 45 files to `src/pages/*` would make every diff look
like a rewrite and would collide constantly across parallel worktrees, for no functional
gain.

**No permanent compatibility shim.** A `useRouter()` shim would make the page migrations
one-line import swaps, but it would leave a Next-shaped API permanently in a codebase
that no longer has Next. With only `push` and `replace` in use across 15 files, the real
rewrite is mechanical. Consistency across parallel agents comes from the cookbook below
plus exemplar pages migrated in the foundation task, not from a shim.

**`next` stays installed until the final cutover task.** This is the load-bearing trick
that makes parallel migration verifiable. If the dependency were removed in the
foundation task, every not-yet-migrated file would fail to resolve and *no* intermediate
task could produce a green `tsc` or build — each agent would be checking its work against
a project that is already broken for unrelated reasons. Leaving the dead dependency in
place means every task in Wave 2 can run a real full build. The cutover task removes it,
and the build then fails loudly if any import was missed.

## The migration cookbook

Every page migration is some subset of these seven patterns. Agents must not invent
alternatives; if a case does not fit, it goes back to the orchestrator rather than being
solved locally in one worktree.

### 1. `"use client"` — delete the directive

Every module is a client module now. Delete the line. Do not leave it as a comment.

### 2. Links

```tsx
// before
import Link from "next/link";
<Link href="/products" className="...">Products</Link>

// after
import { Link } from "react-router";
<Link to="/products" className="...">Products</Link>
```

Only `href` and `className` are used anywhere in the app, so this is a pure rename. Watch
for `<Link>` wrapping a `<Button asChild>`-style pattern and keep the existing structure.

### 3. Navigation

```tsx
// before
import { useRouter } from "next/navigation";
const router = useRouter();
router.push("/pos");
router.replace("/login");

// after
import { useNavigate } from "react-router";
const navigate = useNavigate();
navigate("/pos");
navigate("/login", { replace: true });
```

### 4. Current path

```tsx
// before
import { usePathname } from "next/navigation";
const pathname = usePathname();

// after
import { useLocation } from "react-router";
const { pathname } = useLocation();
```

### 5. Query string

Next returns a read-only `URLSearchParams`. React Router returns a `[params, setParams]`
pair. Reads are identical; writes go through the setter.

```tsx
// before
import { useSearchParams } from "next/navigation";
const searchParams = useSearchParams();
const token = searchParams.get("token");

// after
import { useSearchParams } from "react-router";
const [searchParams] = useSearchParams();
const token = searchParams.get("token");
```

**Delete the Suspense boundaries that only exist to satisfy Next's prerender rule.**
`useSearchParams` opts a Next route out of static prerendering unless it sits under
`<Suspense>`; there is no such rule here. Six pages have a boundary and an accompanying
comment that exist purely for that reason (`(auth)/reset-password`, `(auth)/verify-email`,
`(app)/subscription/checkout`, `portal/auth`, and the two tax pages). Remove the boundary
*and* the comment. Keep any `<Suspense>` that wraps a genuinely lazy component.

### 6. Route params

```tsx
// before  — src/app/portal/invoices/[id]/page.tsx
import { useParams } from "next/navigation";
const params = useParams();
const id = params.id as string;

// after
import { useParams } from "react-router";
const { id } = useParams<{ id: string }>();
```

The directory keeps its `[id]` name; the route table maps `:id` to it. React Router types
params as `string | undefined`, so handle the undefined branch rather than casting it
away — the cast was hiding a real case where the URL does not match.

### 7. Lazy components

```tsx
// before
import dynamic from "next/dynamic";
const TaxReportChart = dynamic(() => import("@/features/tax/components/tax-report-chart"), {
  ssr: false,
  loading: () => <ChartSkeleton />,
});

// after
import { lazy, Suspense } from "react";
const TaxReportChart = lazy(() => import("@/features/tax/components/tax-report-chart"));
// at the call site:
<Suspense fallback={<ChartSkeleton />}><TaxReportChart ... /></Suspense>
```

`ssr: false` has no equivalent and no meaning here. If the module has a named export
rather than a default, `lazy` needs `.then(m => ({ default: m.X }))`.

### 8. Environment variables

```ts
// before
process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8080/api/v1"
// after
import.meta.env.VITE_API_URL ?? "http://localhost:8080/api/v1"

// before
process.env.NODE_ENV === "production"
// after
import.meta.env.PROD
```

## Task breakdown

Eleven tasks in four waves. Wave boundaries are dependency gates, not schedule
suggestions — everything within a wave is genuinely independent and runs in parallel.

### Wave 0 — no dependencies (parallel, can start immediately)

**F0 · Documentation cleanup.** ([#105](https://github.com/AnwarHossainSR/smartpos-pro/issues/105))
Delete `docs/PROGRESS.md`, `docs/REQUIREMENTS.md`, and
`docs/GAP_ANALYSIS_AND_ROADMAP.md`. (`docs/ORCHESTRATOR.md` has already been replaced
with the migration orchestrator, so it stays.) Remove the `.claude/skills/implement-task` and
`.claude/skills/project-status` skills, which read the deleted files and would otherwise
fail on invocation. Update the "Task-driven workflow" section of `CLAUDE.md` and its
backend definition-of-done, both of which point at `docs/PROGRESS.md`.

**Before deleting the roadmap**, copy the specs for the two still-open issues that cite it
as their source — **#65 (G11 Server-Side Exports)** and **#63 (G19 Purchase Edit / Cancel
/ Return)** — into the issue bodies themselves. Deleting a file that two open issues
depend on for their acceptance criteria would silently strand them.

Touches: `docs/`, `CLAUDE.md`, `.claude/skills/`. No overlap with `web/`.

**F1 · Strip the SEO layer.** ([#106](https://github.com/AnwarHossainSR/smartpos-pro/issues/106))
Delete all 41 SEO-only segment layouts, `src/app/robots.ts`, `src/app/sitemap.ts`,
`src/app/llms.txt/route.ts`, `src/app/opengraph-image.tsx`, `src/lib/seo.ts`,
`src/components/seo/`, `src/app/page.tsx`, and `src/components/landing/`. Reduce
`src/app/(app)/layout.tsx` and `src/app/(auth)/layout.tsx` to their functional content
(the former renders `AppShell`; the latter renders its children and can go entirely).
Strip the `metadata`/`viewport` exports and the `JsonLd` block from the root layout.

This runs first because it deletes roughly half the files the foundation task would
otherwise have to port. It touches `src/app/layout.tsx`, which F2 rewrites — so F1 must
land before F2 starts.

Touches: `web/src/app/**` (deletions), `web/src/lib/seo.ts`, `web/src/components/{seo,landing}/`.

### Wave 1 — the foundation gate (serial, single agent)

**F2 · Vite + React Router foundation.** ([#107](https://github.com/AnwarHossainSR/smartpos-pro/issues/107)) *Depends on F1.*

This is the only task that cannot be parallelised, and everything in Wave 2 blocks on it.

- `vite.config.ts`: React plugin, `@tailwindcss/vite`, `@` → `src` alias, dev server on
  port 3000 (Sanctum's allowed origins and the Playwright `baseURL` both assume it).
- `index.html` at `web/`, with the theme/locale bootstrap that currently lives in the root
  layout, so there is no flash of the wrong theme before hydration.
- `src/main.tsx`: `createRoot` → `RouterProvider` → the existing `Providers` tree.
- `src/routes.tsx`: **the complete route table for all 44 routes**, `React.lazy` per page,
  `:token` and `:id` for the two dynamic routes, and the `/` redirect
  (`/dashboard` when a token is present, `/login` otherwise).
- Fonts: swap `next/font/google` for `@fontsource-variable/*` imports, keeping the
  existing `--font-*` CSS variable names so `globals.css` needs no changes.
- `src/lib/api.ts`: `NEXT_PUBLIC_API_URL` → `VITE_API_URL`; update `.env.example` and
  `.env.local`.
- Migrate `src/lib/use-resource-list.ts` — it is imported by most list pages and every
  Wave 2 agent will read it as the reference for query-string handling. Note that
  `{ scroll: false }` is dropped, not translated: React Router does not scroll on
  navigation unless `<ScrollRestoration>` is mounted.
- Migrate `src/components/layout/app-shell.tsx` (the auth guard every `(app)` route
  renders inside) and add `<Outlet />` where it currently renders `children`.
- **Migrate three exemplar pages** and name them in the PR description, one per pattern
  family: a list page using `useResourceList` (`(app)/customers`), a form page that
  navigates on submit (`(auth)/login`), and a dynamic-param page
  (`portal/invoices/[id]`). Wave 2 agents copy these rather than inventing their own.
- Keep `next` in `package.json`. Verify that `vite build` tolerates the remaining
  `next/*` imports in unmigrated files. **If it does not**, add temporary aliases in
  `vite.config.ts` mapping `next/link` and `next/navigation` to a stub module under
  `src/compat/`, with a `TODO(F9)` comment; the cutover task deletes both.

Done when: `npm run dev` boots, `/login` renders and can sign in, `/dashboard` renders
inside the shell, `/customers` lists and paginates, and `npm run build` exits 0.
Other pages may be broken.

Owns: `vite.config.ts`, `index.html`, `src/main.tsx`, `src/routes.tsx`, `package.json`,
`src/lib/api.ts`, `src/lib/use-resource-list.ts`, `src/components/layout/app-shell.tsx`,
`src/app/layout.tsx`, the three exemplar pages.

### Wave 2 — page migrations (parallel, all depend on F2)

Each task applies the cookbook to a disjoint set of files. **No Wave 2 task may edit
`src/routes.tsx`** — F2 finalises it, and a second writer would conflict in every
worktree. Anything that seems to need a route-table change is a signal to stop and report.

**F3 · Auth, portal, and order routes** ([#108](https://github.com/AnwarHossainSR/smartpos-pro/issues/108)) — 9 pages.
`(auth)/{login,register,forgot-password,reset-password,verify-email}`,
`portal/{page,auth,dashboard}`, `portal/invoices/[id]`, `order/[token]`.
Concentrates the `useSearchParams` and `useParams` cases (patterns 5 and 6), including
four of the six removable Suspense boundaries. Skip whatever F2 already migrated.

**F4 · Settings routes** ([#109](https://github.com/AnwarHossainSR/smartpos-pro/issues/109)) — 8 pages.
`(app)/settings/{page,approvals,email-templates,locations,printer,security,tax,webhooks}`.
Mostly patterns 2 and 3. `settings/tax` has a Suspense boundary to assess.

**F5 · POS, dashboard, products, reports** ([#110](https://github.com/AnwarHossainSR/smartpos-pro/issues/110)) — 6 pages.
`(app)/{dashboard,pos,products,products/labels,reports,reports/tax}`.
The heaviest screens and the only `next/dynamic` use (`reports/tax`, pattern 7). Verify
the recharts bundle still splits — check `dist/assets` for a separate chart chunk, since
that split is the whole reason the indirection exists.

**F6 · Remaining app routes** ([#111](https://github.com/AnwarHossainSR/smartpos-pro/issues/111)) — ~18 pages.
`(app)/{accounting,audit-log,cash-register,catalog,customers,expenses,inventory,loyalty,notifications,purchases,reorder-suggestions,sales,stocktakes,subscription,subscription/checkout,suppliers,support,tables,team,test-data}`.
Most have no `next/*` imports left once their layout is deleted, so much of this is
verification rather than editing. `subscription/checkout` uses `useSearchParams`.

**F7 · Shared chrome and components** ([#112](https://github.com/AnwarHossainSR/smartpos-pro/issues/112)) — 5 files.
`src/components/layout/{header,sidebar}.tsx`, `src/components/require-permission.tsx`,
`src/components/ui/command-palette.tsx`, `src/features/onboarding/wizard.tsx`.
The sidebar is the largest `next/link` consumer. `command-palette.tsx` has a test that
will need its Next mocks replaced with a `<MemoryRouter>` wrapper.

**F8 · PWA, service worker, and the offline shell.** ([#113](https://github.com/AnwarHossainSR/smartpos-pro/issues/113))
`public/sw.js` caches `/_next/static` paths that will not exist; Vite emits `/assets/*`
with content hashes. Decide between hand-maintaining `sw.js` and adopting
`vite-plugin-pwa` with `injectManifest` — the latter is recommended, because the offline
POS sale queue in `sw.js` is real logic worth keeping while the precache list is exactly
the part that should be generated rather than hand-written. Preserve the IndexedDB
background-sync code verbatim; it is the offline sale path. Update `public/manifest.json`
and `public/offline.html`, and the `process.env.NODE_ENV` check in `pwa-register.tsx`.

This is the only Wave 2 task permitted to add a dependency or edit `vite.config.ts`.

### Wave 3 — cutover (serial, depends on F3–F8)

**F9 · Remove Next and make everything green.** ([#114](https://github.com/AnwarHossainSR/smartpos-pro/issues/114))
Remove `next`, `eslint-config-next`, `next-env.d.ts`, `next.config.ts`, and any F2 compat
stub. Replace the ESLint config with a flat config using `typescript-eslint`,
`eslint-plugin-react-hooks`, and `eslint-plugin-react-refresh`. Clean `tsconfig.json`: drop
the `next` plugin and the `.next/types` includes. Update `package.json` scripts
(`dev`/`build`/`preview`) and `.gitignore` (`.next` → `dist`).

Done when `npm run build`, `npm run lint`, `tsc --noEmit`, and `npm run test` are all
clean, and `grep -r "next/" src/` returns nothing.

### Wave 4 — parallel, both depend on F9

**F10 · Tests and e2e.** ([#115](https://github.com/AnwarHossainSR/smartpos-pro/issues/115)) Playwright `webServer` command and `baseURL`; replace Next mocks
in `command-palette.test.tsx` with a router wrapper; confirm `vitest.config.ts` still
resolves (its `@` alias and React plugin already match the new setup). Run the full
`pos-checkout.spec.ts` against the Vite build, not just the dev server.

**F11 · Documentation and deployment.** ([#116](https://github.com/AnwarHossainSR/smartpos-pro/issues/116)) `docs/ARCHITECTURE.md` and `docs/DEPLOYMENT.md`
(§3 becomes a static build: no PM2, no `next start`; nginx serves `dist/` with
`try_files $uri $uri/ /index.html` and caches `/assets/` immutably instead of
`/_next/static/`), `web/AGENTS.md`, `web/CLAUDE.md`, the frontend section of the root
`CLAUDE.md`, and `docs/PROJECT_SPEC.md` where it names the framework.

The SPA fallback is the one deployment detail that silently breaks everything if missed:
without `try_files`, every URL except `/` returns 404 on refresh.

## Dependency graph

```
Wave 0   F0 (docs)          F1 (strip SEO)
                                  │
Wave 1                            └──► F2 (Vite + Router foundation)
                                            │
Wave 2      ┌───────────┬───────────┬───────┴───┬───────────┬───────────┐
            F3          F4          F5          F6          F7          F8
          auth/       settings    pos/dash    remaining   shared      PWA /
          portal                  products    app pages   chrome      sw.js
            └───────────┴───────────┴───────────┴───────────┴───────────┘
                                            │
Wave 3                                      └──► F9 (remove Next, green build)
                                                      │
Wave 4                                          ┌─────┴─────┐
                                                F10        F11
                                              tests/e2e   docs/deploy
```

F0 is independent of the entire frontend chain and can land at any point.

## File ownership

One writer per file for the whole run. Conflicts in a parallel worktree migration come
almost entirely from shared files, so these are assigned rather than discovered:

| File | Owner | Note |
| --- | --- | --- |
| `src/routes.tsx` | **F2 only** | Finalised in Wave 1. No Wave 2 task edits it. |
| `vite.config.ts` | F2, then F8 | F8 adds the PWA plugin. Nobody else. |
| `package.json` | F2, then F8, then F9 | F8 adds one dependency; F9 removes Next. |
| `src/lib/api.ts` | F2 | Env var rename. |
| `src/lib/use-resource-list.ts` | F2 | Every list page reads it; must be settled early. |
| `src/components/layout/app-shell.tsx` | F2 | The auth guard. |
| `src/components/layout/{header,sidebar}.tsx` | F7 | |
| `src/app/layout.tsx` | F1, then F2 | F1 strips metadata; F2 folds it into `index.html`. |
| `public/**` | F8 | |
| `docs/**`, `CLAUDE.md` | F0, then F11 | F0 deletes; F11 rewrites what remains. |
| `eslint.config.mjs`, `tsconfig.json` | F9 | |

## Definition of done

Per task: the scope migrated, `npm run build` exits 0, `npm run lint` reports no new
errors, and every touched route renders and navigates in a running dev server. "It
compiles" is not sufficient — a `useNavigate` that is never called still compiles.

For the migration as a whole:

- `npm run build` · `npm run lint` · `tsc --noEmit` · `npm run test` · `npm run test:e2e` all clean
- `grep -r "next/" web/src/` returns nothing, and `next` is absent from `package.json`
- Every one of the 44 routes loads, including a hard refresh on a deep link (this is what
  catches a missing SPA fallback)
- Login, a POS sale end to end, and a list page's search/sort/pagination round-trip
  through the URL
- Offline: the service worker serves the shell and queues a sale
- The backend has no diff

## Risks and contingencies

**Vite fails to bundle the unmigrated `next/*` imports during Wave 2.** The whole
verification strategy rests on this working. F2 checks it first, before anything else is
built on top; the fallback is stub aliases, described in F2. If neither works, Wave 2
tasks fall back to per-file ESLint plus a manual smoke test, and only F9 is fully green.

**Theme or locale flash on first paint.** The root layout currently sets these before
paint. Moving the bootstrap into `index.html` is easy to overlook because it only shows
up as a flicker, not an error. F2 owns it; check it against a hard reload in dark mode.

**The recharts split silently collapses.** `next/dynamic` currently keeps the app's
heaviest dependency out of the main graph. If `React.lazy` is applied at the wrong level
the chunk merges back in and every page pays for it. F5 verifies by inspecting
`dist/assets`.

**`react-router` v8 API drift.** This document was written against 8.3.0 and the core
API has been stable since v6, but agents should trust the installed types over this file.

**Deep links 404 after deploy.** Covered by the nginx `try_files` line in F11, and worth
testing on the built output rather than the dev server, which papers over it.
