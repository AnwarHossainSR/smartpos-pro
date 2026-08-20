# SmartPOS Pro — Migration Orchestrator

Drives the Next.js → Vite + React Router migration described in
[`MIGRATION_NEXT_TO_VITE.md`](MIGRATION_NEXT_TO_VITE.md) by dispatching parallel
sub-agents into isolated git worktrees, gating on dependencies, and running a review loop
after each wave.

## How to use

Open a fresh Claude Code session at the repo root and paste the orchestrator prompt
below. It expects `docs/MIGRATION_NEXT_TO_VITE.md` to be present — that file, not this
one, is the specification. This file is only the execution harness.

> **On `/goal`:** the review loop below names `/goal`, matching the vocabulary of the
> previous orchestrator. There is no `goal` skill installed in this environment
> (`~/.claude/skills`, the project's `.claude/skills`, and the enabled plugins were all
> checked). The loop is therefore written to be self-contained: if `/goal` resolves, use
> it; if it does not, run the checklist inline. Do not let a missing slash command stall a
> wave.

---

## Orchestrator prompt

````text
You are the SmartPOS Pro migration orchestrator. You are moving the frontend in `web/`
from Next.js 16 to Vite + React Router 8. The backend is not touched at all.

## Read first

1. `docs/MIGRATION_NEXT_TO_VITE.md` — the specification. Task scopes (F0–F11), the
   migration cookbook, the dependency graph, and the file-ownership table all live there.
   Do not re-derive any of it; it is the product of a survey of the actual codebase.
2. `CLAUDE.md` and `web/AGENTS.md` — repo conventions.

## Ground rules

- **Never push to `main`.** Work branches from `dev`; the final PR targets `main`.
- **The backend is out of scope.** If a task appears to need a backend change, stop and
  report it rather than making it. A backend diff at the end of this migration is a bug.
- **One writer per file.** The ownership table in the spec is binding. If a sub-agent
  reports needing to edit a file another task owns — `src/routes.tsx` above all — do not
  let it. Resolve the conflict at the orchestrator level.
- **`next` stays in `package.json` until F9.** This is deliberate and load-bearing: it
  keeps the project building throughout Wave 2 so each task can actually verify itself.
  Any sub-agent that "helpfully" removes it has broken every sibling worktree.
- **No new patterns.** Sub-agents apply the cookbook in the spec. A case that does not fit
  comes back to you; it does not get solved locally in one worktree.

## Wave schedule

    Wave 0   F0 (docs cleanup)      F1 (strip SEO layer)        ← parallel, no deps
    Wave 1   F2 (Vite + Router foundation)                      ← serial, needs F1
    Wave 2   F3 F4 F5 F6 F7 F8                                  ← parallel, all need F2
    Wave 3   F9 (remove Next, green build)                      ← serial, needs F3–F8
    Wave 4   F10 (tests/e2e)        F11 (docs/deploy)           ← parallel, both need F9

F0 is independent of the frontend chain entirely and may land at any point.

Wave 1 is one agent. Do not try to parallelise it — it is the gate that defines the
patterns every Wave 2 agent copies, and splitting it produces six inconsistent dialects.

## Per-wave procedure

### 1. Gate check

Before starting a wave, confirm its dependencies actually merged into `dev` — not that a
sub-agent *reported* success. Check the merge commit and re-run the checks yourself:

```bash
git -C . log --oneline dev -5
cd web && npm run build && npm run lint
```

A sub-agent reporting green is a claim, not evidence. Verify it.

### 2. Create worktrees

One worktree per task in the wave:

```bash
git worktree add ../smartpos-pro-{TASK_ID} -b migrate/{TASK_ID} dev
```

Branch from `dev` at wave start so each worktree already contains the previous wave's
merged work. Sub-agents work exclusively inside their own worktree path.

### 3. Dispatch

Spawn one sub-agent per task, in parallel, using the template below. Give each the task's
scope verbatim from the spec — do not paraphrase it.

### 4. Review loop

When a wave's sub-agents report back, review before merging. Run `/goal` if it resolves in
this environment; otherwise run this checklist yourself against each worktree:

- **Scope.** Every file in the task's scope migrated, nothing outside it touched.
  `git -C ../smartpos-pro-{TASK_ID} diff --stat dev` should contain no surprises, and
  no file owned by another task.
- **Checks.** `npm run build` exits 0. `npm run lint` shows no new errors against the
  baseline. `tsc --noEmit` is clean.
- **Residue.** `grep -rn "next/\|use client\|NEXT_PUBLIC" web/src/{scope}` is empty for
  the migrated scope.
- **Behaviour.** Boot the dev server and actually load the migrated routes. A page whose
  `useNavigate` is never wired still compiles and still typechecks; only clicking it
  proves anything. This step is not optional and not delegable back to the agent that
  wrote the code.
- **Cookbook fidelity.** The diff uses the spec's patterns, not a local invention. Six
  worktrees drifting apart is the main failure mode of this whole approach.

If issues are found, send the sub-agent back into the *same* worktree with the specific
findings — do not spawn a fresh agent, which would lose the context, and do not fix it
yourself, which hides the pattern from the agent that will repeat it. Re-review after.

### 5. Merge and record

When a task passes review:

```bash
git checkout dev && git merge --no-ff migrate/{TASK_ID}
git worktree remove ../smartpos-pro-{TASK_ID}
```

Then close its GitHub issue with a one-line summary of what changed, and tick it off in
the parent issue's task list.

### 6. Conflicts

If two worktrees in the same wave touched the same file, the ownership table was wrong or
was violated. Do not resolve it by picking a side — work out which task legitimately owns
the file, revert the other's edit, and note the correction so later waves inherit the fix.

## Sub-agent prompt template

```
You are migrating {TASK_ID}: {TASK_TITLE} for SmartPOS Pro, moving the frontend from
Next.js to Vite + React Router 8.

## Working directory
{WORKTREE_PATH} — work only here. Do not touch the main checkout.

## Read first
- docs/MIGRATION_NEXT_TO_VITE.md — especially "The migration cookbook" and the {TASK_ID}
  entry under "Task breakdown". The cookbook has the exact before/after for every pattern
  you will hit. Use it verbatim; do not invent alternatives.
- web/AGENTS.md — frontend conventions (these still apply; only the framework changed).
- The exemplar pages named in F2's PR description. Copy their structure.

## Your scope
{SCOPE — file list, verbatim from the spec}

## Hard constraints
- Do not edit `src/routes.tsx`. F2 owns it. If your scope seems to need a route-table
  change, stop and report it instead.
- Do not add, remove, or upgrade any dependency. Do not touch `package.json`.
  (Exception: F8, which adds vite-plugin-pwa.)
- Do not remove `next` from package.json. It is intentionally still installed so the
  project keeps building while other tasks migrate in parallel.
- Do not touch anything under the repo root outside `web/`. The backend is out of scope.
- Do not edit files owned by another task — see the ownership table in the spec.

## Definition of done
- Every file in your scope migrated per the cookbook.
- `cd web && npm run build` exits 0.
- `npm run lint` shows no new errors.
- `grep -rn "next/\|use client" {your scope}` returns nothing.
- You started the dev server and loaded every route in your scope. Navigation, query
  params, and route params behave as before. Compiling is not the bar.

## Commit
One commit per logical group, message prefixed with the task id:
`{TASK_ID}: migrate settings routes to react-router`
Push to your branch. Do not open a PR — the orchestrator merges.

## Report back
- Files changed, and any file you wanted to touch but could not because of ownership.
- Which cookbook patterns you applied, and anything that did not fit one.
- Routes you loaded and what you clicked.
- Anything you deleted that you were not certain was dead.
- Check results, pasted, not summarised.
```

## Final delivery

After Wave 4:

1. On `dev`, run the full suite: `npm run build`, `npm run lint`, `tsc --noEmit`,
   `npm run test`, `npm run test:e2e`, and `php artisan test` (to prove the backend is
   untouched — it should be, and `git diff main -- app/ routes/ database/` should be empty).
2. Load all 44 routes against the **built** output, not the dev server, including a hard
   refresh on a deep link like `/portal/invoices/3`. This is what catches a missing SPA
   fallback, and the dev server hides it.
3. Open a PR from `dev` to `main` with a summary: routes migrated, dependencies removed,
   bundle size before and after, and anything deferred.
4. Close the parent issue.

## To begin

Say: "Start the Vite migration."

You will then: verify the repo is clean and on `dev`, confirm the baseline checks pass so
later failures are attributable, create Wave 0 worktrees, and dispatch F0 and F1.
````

---

## Issue reference

| Task | Title | Wave | Depends on | Issue |
| --- | --- | --- | --- | --- |
| — | Migrate the frontend from Next.js to Vite + React Router | — | — | **#104** |
| F0 | Documentation cleanup | 0 | — | #105 |
| F1 | Strip the SEO layer | 0 | — | #106 |
| F2 | Vite + React Router foundation | 1 | F1 | #107 |
| F3 | Migrate auth, portal, and order routes | 2 | F2 | #108 |
| F4 | Migrate settings routes | 2 | F2 | #109 |
| F5 | Migrate POS, dashboard, products, reports | 2 | F2 | #110 |
| F6 | Migrate remaining app routes | 2 | F2 | #111 |
| F7 | Migrate shared chrome and components | 2 | F2 | #112 |
| F8 | PWA, service worker, and offline shell | 2 | F2 | #113 |
| F9 | Remove Next.js and make everything green | 3 | F3–F8 | #114 |
| F10 | Tests and e2e | 4 | F9 | #115 |
| F11 | Documentation and deployment | 4 | F9 | #116 |

All twelve are registered as native GitHub sub-issues of **#104**, so the parent
tracks completion automatically.
