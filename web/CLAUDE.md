# SmartPOS Pro — Frontend Conventions

@AGENTS.md

## Additional Rules

- **Never push to `main`.** Use the dev branch and open a PR.
- **Commit messages** reference the task ID (e.g. `T43: …`).
- **API responses** are shaped as `{ data: ... }` for single resources or
  `{ data: [...], links: {...}, meta: {...} }` for paginated lists — typed as `Envelope<T>`
  and `Paginated<T>` in `src/lib/api.ts`.
- **Error handling:** `ApiError` from `src/lib/api.ts` carries `status`, `message`, an optional
  `errors` map and the raw `payload`. Use the `errorMessage(e, fallback)` helper rather than
  re-testing `e instanceof ApiError` at each call site. Mutation failures are toasted globally
  by `src/lib/query-client.ts` — write your own `onError` only for a specific message or real
  cleanup.
- **Design tokens** are in `src/app/globals.css` — use `var(--token)` not hardcoded colors.
- **Component naming:** PascalCase, one component per file, co-locate tests if any.
- **This is a Vite SPA.** No `"use client"`, no `next/*`, no server components. Routes are
  declared in `src/routes.tsx`.

## The four modules everything routes through

Adding a feature means using these, not re-implementing them next to them:

| Module | Owns |
|---|---|
| `src/lib/api.ts` | every authenticated request, the error envelope, `ApiError` |
| `src/lib/query-keys.ts` | every cache key — no inline key arrays |
| `src/lib/lookups.ts` | shared reference data (warehouses, branches, categories, …) |
| `src/lib/use-resource-list.ts` | every paginated list screen's search/sort/page/filter state |

`src/features/tax/` is the reference implementation of a feature module
(`api.ts` + `hooks.ts` + `components/`). Copy its shape.
