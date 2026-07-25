---
name: project-status
description: >-
  Report SmartPOS Pro build progress. Trigger when the user asks "project status",
  "what's left", "what's done", "roadmap", "progress", or invokes /project-status.
  Summarises docs/PROGRESS.md and suggests the next tasks to run.
---

# Project status

1. Read `docs/PROGRESS.md` (and `docs/REQUIREMENTS.md` for task detail).
2. Report a concise summary:
   - the Summary table counts (done / in progress / todo per phase),
   - anything currently 🚧 in progress,
   - the next 3–5 ⬜ todo tasks, by id and title, that are the highest-value to do next
     (prefer unblocking order: backend before its frontend screen).
3. Remind the user they can run `/implement-task <id>` to build any task.
Do not modify files — this is a read-only report.
