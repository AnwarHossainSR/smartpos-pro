# SmartPOS Pro — Frontend Agent Rules

## Tech Stack

- **Framework:** Next.js 16 (App Router)
- **React:** 19
- **Language:** TypeScript (strict)
- **Styling:** Tailwind CSS v4 with semantic tokens in `src/app/globals.css`
- **State:** Zustand (auth, theme, locale stores in `src/stores/`)
- **Server State:** TanStack Query
- **Forms:** React Hook Form + Zod validation
- **UI Components:** Custom primitives in `src/components/ui/`
- **Icons:** Lucide React

## Project Structure

```
web/src/
  app/
    (auth)/          # Public auth pages (login, register, etc.)
    (app)/           # Authenticated app pages (sidebar layout)
    layout.tsx       # Root layout
    globals.css      # Design tokens
  components/
    ui/              # Reusable UI primitives (Button, Dialog, Input, etc.)
    layout/          # App shell, sidebar, header
    providers.tsx    # QueryClientProvider, Toaster, etc.
  lib/
    api.ts           # API client (Bearer token, error handling)
    utils.ts         # cn(), formatCurrency, exportToCsv, etc.
  stores/
    auth.ts          # Auth user, token, login/logout
    theme.ts         # Light/dark mode + palette
    locale.ts        # UI language + number/date locale
  features/          # Feature modules (tax, etc.)
```

## Conventions

- **API calls** go through `src/lib/api.ts` — never raw `fetch` for authenticated requests.
- **Auth token** stored in memory (zustand) + `Secure`/`SameSite=Strict` cookie.
- **Every list page** must have: search, filter, pagination, empty state, loading skeleton.
- **Every mutation** must have: form validation, confirm-on-destructive, toast feedback.
- **Route guarding** via `useAuth().can('module.action')` for permission checks.
- **No comments** in code unless explicitly requested.
- **No emojis** in code or UI unless explicitly requested.

## Definition of Done

- `npm run lint` — zero errors
- `npm run build` — succeeds
- Loading/empty/error states handled for all data-fetching pages
- Permission checks via `useAuth().can()` where applicable

## Running Checks

```bash
npm run lint      # ESLint
npm run build     # Next.js build
npm run dev       # Dev server on http://localhost:3000
```
