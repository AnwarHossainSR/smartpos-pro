# SmartPOS Pro — Frontend Agent Rules

## Tech Stack

- **Framework:** Vite + React Router 8 (SPA — **not** Next.js; there is no server rendering,
  no `"use client"`, no `next/*` imports)
- **React:** 19
- **Language:** TypeScript (strict)
- **Styling:** Tailwind CSS v4 with semantic tokens in `src/app/globals.css`
- **State:** Zustand (auth, theme, locale, business, sidebar stores in `src/stores/`)
- **Server State:** TanStack Query
- **Forms:** React Hook Form + Zod validation
- **UI Components:** Custom primitives in `src/components/ui/`
- **Icons:** Lucide React

## Project Structure

```
web/src/
  main.tsx           # Entry: createBrowserRouter(routes) inside <Providers>
  routes.tsx         # The route table — every page is a lazy() import with an SEO `handle`
  app/
    (auth)/          # Public auth pages (login, register, …)
    (app)/           # Authenticated pages, rendered inside <AppShell> via <Outlet>
    portal/          # Customer portal (separate magic-link token)
    order/[token]/   # Public order-status page
    globals.css      # Design tokens
  components/
    ui/              # Reusable primitives (Button, Dialog, DataTable, ListToolbar, …)
    layout/          # AppShell, Sidebar, Header
    providers.tsx    # QueryClientProvider + I18nProvider + Toaster + PwaRegister
  features/          # Feature modules: api.ts + hooks.ts + components/ (see `features/tax/`)
  lib/
    api.ts           # THE API client — bearer token, error envelope, ApiError, errorMessage()
    query-client.ts  # QueryClient defaults + global mutation error toast
    query-keys.ts    # THE cache-key factory — no inline key arrays anywhere else
    lookups.ts       # Shared reference-data hooks (warehouses, branches, categories, …)
    use-resource-list.ts # THE list-screen hook (URL state, debounced search, keepPreviousData)
    utils.ts         # cn(), formatCurrency, exportToCsv, …
  types/
    domain.ts        # Entity types shared by more than one screen
  i18n/
    context.ts       # I18nContext + useI18n/useTranslation  (import hooks from HERE)
    provider.tsx     # <I18nProvider> only — a provider file must not export hooks
  stores/            # Zustand stores
```

The folder names in parentheses — `(app)`, `(auth)` — are a leftover of the Next.js route-group
convention. Routing is now declared explicitly in `routes.tsx`; the parentheses are only a
grouping hint for humans and carry no behaviour.

## Conventions

- **API calls** go through `src/lib/api.ts` — never raw `fetch` for authenticated requests.
  Use `errorMessage(e, fallback)` rather than re-testing `e instanceof ApiError` at each site.
- **Cache keys** come from `src/lib/query-keys.ts`. Never write an inline key array: two spellings
  of one resource double-fetch it, and one spelling covering two different fetches makes them
  clobber each other.
- **Reference data** (warehouses, branches, categories, brands, units, roles, plans) comes from
  `src/lib/lookups.ts`. Never re-fetch those endpoints per page.
- **Paginated list screens** use `useResourceList`. It owns search debouncing, URL-backed
  search/sort/page/filter state and `keepPreviousData`. Don't hand-roll
  `useState(search)` + `useDebouncedValue` + `useState(page)`.
- **Mutation errors** are toasted globally by the `MutationCache` in `src/lib/query-client.ts`.
  Only write `onError` for a specific message or real cleanup; set `meta: { silent: true }` when
  the failure is surfaced inline instead.
- **Shared entity types** live in `src/types/domain.ts`, not re-declared per page.
- **Auth token** stored in memory (zustand) + `Secure`/`SameSite=Strict` cookie.
- **Every list page** must have: search, filter, pagination, empty state, loading skeleton, error
  state. `DataTable` provides loading/empty/error out of the box.
- **Every mutation** must have: form validation, confirm-on-destructive, toast feedback.
- **Route guarding** via `useAuth().can('module.action')` for permission checks.
- **A module that exports a React component must not also export hooks or mutable helpers.**
  Vite re-executes such a module on every edit, re-creating module-level values — which is how a
  React context gets duplicated and every consumer starts reading `null`. Enforced by code review;
  Biome has no `react-refresh/only-export-components` equivalent (ESLint was replaced).
- **No emojis** in code or UI unless explicitly requested.

## Definition of Done

- `npx tsc --noEmit` — clean
- `npm run lint` — zero errors
- `npm test` — green
- `npm run build` — succeeds
- Loading/empty/error states handled for all data-fetching pages
- Permission checks via `useAuth().can()` where applicable

## Running Checks

```bash
npm run dev        # Dev server on http://localhost:3000
npx tsc --noEmit   # Typecheck
npm run lint       # Biome
npm test           # Vitest
npm run build      # Vite build
npm run test:e2e   # Playwright
```
