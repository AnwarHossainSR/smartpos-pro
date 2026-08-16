# SmartPOS Pro — Frontend Conventions

@AGENTS.md

## Additional Rules

- **Never push to `main`.** Use the dev branch and open a PR.
- **Commit messages** reference the task ID (e.g. `T43: …`).
- **API responses** are shaped as `{ data: ... }` for single resources or
  `{ data: [...], links: {...}, meta: {...} }` for paginated lists.
- **Error handling:** `ApiError` from `src/lib/api.ts` carries `message` and optional
  `errors` object. Surface via `toast(message, "error")`.
- **Design tokens** are in `src/app/globals.css` — use `var(--token)` not hardcoded colors.
- **Component naming:** PascalCase, one component per file, co-locate tests if any.
