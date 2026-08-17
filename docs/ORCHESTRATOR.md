# SmartPOS Pro — Master Orchestrator Prompt

**Purpose:** Orchestrate parallel sub-agent execution of gap analysis items using worktrees,
with dependency-aware scheduling and review loops.

---

## How to Use

1. Copy this prompt into a new Claude Code session
2. The orchestrator will:
   - Read the dependency graph from `docs/GAP_ANALYSIS_AND_ROADMAP.md`
   - Spawn parallel sub-agents in isolated worktrees
   - Monitor progress and handle dependency gates
   - Run review loops after each wave completes
   - Merge completed work and open PRs

---

## Orchestrator Prompt

```
You are the SmartPOS Pro Orchestrator. Your job is to execute the gap analysis roadmap
by spawning parallel sub-agents in isolated worktrees, monitoring their progress, and
coordinating dependency gates.

## Context
- Repository: SmartPOS Pro (multi-tenant POS SaaS)
- Dev branch: claude/new-github-repo-setup-tb3viy
- Roadmap: docs/GAP_ANALYSIS_AND_ROADMAP.md
- Conventions: CLAUDE.md (backend + frontend)

## Execution Rules

### 1. Dependency-First Scheduling
Read the dependency graph from GAP_ANALYSIS_AND_ROADMAP.md. Execute in waves:

**Wave 1 (Independent — start immediately):**
- G1 (Receipt Download)
- G5 (Keyboard Shortcuts)
- G6 (Hold/Park UI)
- G8 (Numpad)
- G3 (Dashboard v2)
- G18 (Invoice Numbering)
- G9 (Onboarding Wizard)
- G16 (Shifts/Time Clock)
- G21 (Webhooks)
- G28 (Theme Sync)
- G30 (Backups)
- G34 (Observability)
- S1,S3-S6,S8-S15 (Simplicity batch)

**Wave 2 (After Wave 1 starts — some Wave 1 may still be finishing):**
- G2 (Barcode) — independent but complex
- G4 (Product Images) — independent but complex
- G7 (Customer Quick-Attach) — independent
- G17 (Auto-Journals) — independent but XL
- G13 (Z-Report) — independent
- G14 (PO Workflow) — independent
- G10 (CSV Import) — independent
- G23 (Kitchen Display) — independent
- G24 (Tables) — independent
- G25 (Modifiers) — independent
- G29 (Cache Layer) — independent
- G31 (GDPR) — independent

**Wave 3 (Dependent — must wait for Wave 2):**
- S2 (Thumbnails → needs G4)
- S7 (Unified Search → needs G2)
- G12 (Grid View → needs G4, G8)
- G22 (P&L + Balance Sheet → needs G17)
- G15 (Supplier Payments → needs G17 for journal hook)
- G19 (Purchase Returns → needs G14)
- G11 (Server Exports → needs G10)
- G33 (Test Suite → needs G17 for assertions)

**Wave 4 (Revenue — after Wave 2-3):**
- G20 (Stripe — 3 phases, complex)

### 2. Worktree Isolation
For each independent item (or small batch of related items):
1. Create a worktree: `git worktree add ../smartpos-pro-work-{item_id} -b feature/{item_id}`
2. The sub-agent works exclusively in that worktree
3. Each worktree has full repo access but isolated changes

### 3. Sub-Agent Dispatch
For each item, spawn a sub-agent with this template:

---
**Sub-Agent Prompt Template:**

```
You are implementing {ITEM_ID}: {ITEM_TITLE} for SmartPOS Pro.

## Context
- Working directory: {WORKTREE_PATH}
- Read CLAUDE.md for conventions
- Read docs/GAP_ANALYSIS_AND_ROADMAP.md for the full spec of this item
- Dev branch: claude/new-github-repo-setup-tb3viy

## Your Task
Implement the item described in the gap analysis. Follow these steps:

1. **Read the spec** from GAP_ANALYSIS_AND_ROADMAP.md (the section for {ITEM_ID})
2. **Implement backend** (if applicable):
   - Create/modify migrations, models, services, controllers, routes
   - Add permission slugs to PermissionCatalog
   - Write feature tests under tests/Feature/Api/
   - Run `php artisan test` and `vendor/bin/pint --test`
3. **Implement frontend** (if applicable):
   - Create/modify pages, components, hooks, stores
   - Add i18n keys to en.json + translate to de/bn/ar/ja
   - Add query keys to src/lib/query-keys.ts
   - Run `bun run lint` and `bun run build`
4. **Update docs**:
   - Document new endpoints in docs/API.md
   - Update docs/PROGRESS.md with completion status

## Definition of Done
- [ ] All acceptance criteria from the spec met
- [ ] `php artisan test` green (backend)
- [ ] `vendor/bin/pint --test` clean (backend)
- [ ] `bun run lint` clean (frontend)
- [ ] `bun run build` succeeds (frontend)
- [ ] i18n in all 5 languages
- [ ] docs/API.md updated
- [ ] docs/PROGRESS.md updated

## Commit
When done, commit with message: `{ITEM_ID}: {description}`
Push to your feature branch.

## Return
When complete, return:
- Files created/modified
- Tests added
- Any blockers or issues encountered
- Confirmation that all checks pass
```
---

### 4. Review Loop (using /goal)
After each wave completes:

1. **Collect results** from all sub-agents in the wave
2. **Run /goal** to verify:
   - All acceptance criteria met
   - Tests pass
   - Lint/build clean
   - Docs updated
3. **If issues found:**
   - Spawn a fix sub-agent in the same worktree
   - Re-run /goal after fixes
4. **If all clear:**
   - Merge worktree branch into dev branch
   - Close the GitHub issue
   - Move to next wave

### 5. Dependency Gate Checks
Before starting Wave 3:
- Verify G4 completed (for S2, G12)
- Verify G2 completed (for S7)
- Verify G17 completed (for G22, G15, G33)
- Verify G14 completed (for G19)
- Verify G10 completed (for G11)

If a dependency failed, skip its dependents and report to the user.

### 6. Conflict Resolution
If two sub-agents modify the same file:
1. The later wave takes precedence
2. Manual merge may be required
3. Report conflicts to the user for resolution

### 7. Progress Tracking
After each item completes:
1. Update docs/PROGRESS.md
2. Update the GitHub issue with completion status
3. Add issue number to the Progress Tracker table in GAP_ANALYSIS_AND_ROADMAP.md

### 8. Final Delivery
After all waves complete:
1. Run full test suite on dev branch
2. Open PR to main with all changes
3. Generate summary report of:
   - Items completed
   - Items skipped (dependencies)
   - Items with issues
   - Total time
   - Next steps

## Command to Start
To begin execution, say: "Start gap analysis execution"

The orchestrator will then:
1. Verify the repo state
2. Create worktrees for Wave 1
3. Spawn sub-agents
4. Monitor and coordinate
5. Report progress
```

---

## Quick Reference — Issue Numbers

| Sprint | Item | Issue |
|--------|------|-------|
| 1 | Sprint 1 Master | #41 |
| 1 | G1 Receipt Download | #48 |
| 1 | G2 Barcode | #49 |
| 1 | G5 Shortcuts | #50 |
| 1 | G6 Hold/Park | #51 |
| 1 | G8 Numpad | #52 |
| 2 | Sprint 2 Master | #42 |
| 2 | G3 Dashboard | #53 |
| 2 | G4 Product Images | #54 |
| 2 | G7 Customer Attach | #55 |
| 2 | G12 Grid View | #56 |
| 3 | Sprint 3 Master | #43 |
| 3 | G17 Auto-Journals | #57 |
| 3 | G18 Invoice Numbering | #58 |
| 3 | G22 P&L + Balance Sheet | #59 |
| 3 | G13 Z-Report | #60 |
| 4 | Sprint 4 Master | #44 |
| 4 | G14 PO Workflow | #61 |
| 4 | G15 Supplier Payments | #62 |
| 4 | G19 Purchase Returns | #63 |
| 4 | G10 CSV Import | #64 |
| 4 | G11 Server Exports | #65 |
| 5 | Sprint 5 Master | #45 |
| 5 | G20 Stripe | #66 |
| 5 | G9 Onboarding | #67 |
| 5 | G16 Shifts | #68 |
| 6 | Sprint 6 Master | #46 |
| 6 | G23 Kitchen Display | #69 |
| 6 | G24 Tables | #70 |
| 6 | G25 Modifiers | #73 |
| 7 | Sprint 7 Master | #47 |
| 7 | G29 Cache | #71 |
| 7 | G30 Backups | #72 |
| 7 | G31 GDPR | #74 |
| 7 | G33 Test Suite | #75 |
| 7 | G34 Observability | #76 |
| 7 | G21 Webhooks | #77 |
| 7 | G28 Theme Sync | #78 |

---

## Parallel Execution Summary

```
Wave 1 (12 items) ──────────────────────────────────────
  G1, G5, G6, G8, G3, G18, G9, G16, G21, G28, G30, G34, S1-S15

Wave 2 (12 items) ──────────────────────────────────────
  G2, G4, G7, G17, G13, G14, G10, G23, G24, G25, G29, G31

Wave 3 (8 items, dependent) ────────────────────────────
  S2(→G4), S7(→G2), G12(→G4,G8), G22(→G17),
  G15(→G17), G19(→G14), G11(→G10), G33(→G17)

Wave 4 (1 item) ────────────────────────────────────────
  G20 (Stripe — 3 phases)

Total: 33 items across 4 waves
Estimated parallel time: ~3-4 weeks (vs ~8-10 weeks sequential)
```
