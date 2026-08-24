# shadcn/ui Migration Plan — SmartPOS Pro

## Current State
- 14 custom UI primitives in `web/src/components/ui/` — mostly plain HTML with `cn()`
- Zero Radix UI, zero `class-variance-authority` installed
- Custom CSS token names (`--bg`, `--surface`, `--fg`) that don't match shadcn conventions
- 6-palette theme system via `data-theme` attribute (indigo, amber, emerald, ocean, violet, rose)
- `clsx` + `tailwind-merge` + `cn()` already in place

---

## Phase 1: Foundation (theme + CLI setup)

### 1.1 Initialize shadcn CLI
```bash
cd web/
npx shadcn@latest init
```
Creates `components.json`, installs `class-variance-authority` + `@radix-ui/react-slot`.

### 1.2 Rename CSS variables in `globals.css`

| Current Token | shadcn Equivalent |
|---------------|-------------------|
| `--bg` | `--background` |
| `--surface` | `--card` |
| `--surface-2` | `--muted` |
| `--surface-3` | `--accent` |
| `--fg` | `--foreground` |
| `--muted` | `--muted-foreground` |
| `--border` | `--border` (keep) |
| `--primary` | `--primary` (keep) |
| `--primary-fg` | `--primary-foreground` |
| `--ring` | `--ring` (keep) |
| `--danger` | `--destructive` |

New tokens to add: `--popover`, `--popover-foreground`, `--secondary`, `--secondary-foreground`, `--input`

### 1.3 Update `@theme` block to bridge new names to Tailwind

### 1.4 Update `--radius` to use shadcn's radius scale (`--radius-sm`, `--radius-md`, `--radius-lg`, `--radius-xl`)

---

## Phase 2: Replace near-identical primitives (low risk)

Drop-in replacements — same API surface, just shadcn under the hood:

| Component | File | Action |
|-----------|------|--------|
| Button | `button.tsx` | Replace with shadcn Button (cva + Slot) |
| Badge | `badge.tsx` | Replace with shadcn Badge (cva) |
| Card | `card.tsx` | Replace with shadcn Card (adds CardDescription, CardFooter) |
| Input | `input.tsx` | Replace with shadcn Input |
| Label | `label.tsx` | Extract from input.tsx, replace with shadcn Label |
| Textarea | `textarea.tsx` | Replace with shadcn Textarea |
| Skeleton | `skeleton.tsx` | Replace with shadcn Skeleton |
| Table | `table.tsx` | Replace with shadcn Table |

---

## Phase 3: High-value Radix replacements (major UX upgrade)

| Component | Current | shadcn | Impact |
|-----------|---------|--------|--------|
| `dialog.tsx` | Monolithic confirm dialog | Composable Dialog + AlertDialog | Arbitrary content, focus trapping, a11y |
| `drawer.tsx` | Custom right-panel only | Sheet (all 4 sides) | Multi-side, composable, accessible |
| `select.tsx` | Native `<select>` | Radix Select | Custom rendering, search, animations |
| `pagination.tsx` | Prev/next only | Full Pagination | Numbered pages, ellipsis |

**Consumer migration required:**
- Dialog: ~20+ call sites use `<Dialog open onClose title description onConfirm>` → migrate to composable `<DialogContent><DialogHeader>...`
- Drawer: ~15+ call sites use `<Drawer open onClose title>` → migrate to `<Sheet>` composable API
- Select: ~10+ call sites use `<Select value onChange options>` → migrate to Radix Select

---

## Phase 4: New components (additive, no breaking changes)

| Component | Use Case |
|-----------|----------|
| Tabs | Settings pages, report views, loyalty page |
| DropdownMenu | Row action menus (edit/delete/more) |
| Checkbox | Replace raw `<input type="checkbox">` in tables |
| Switch | Boolean toggles in forms/settings |
| Separator | Visual dividers between sections |
| Tooltip | Icon button labels, help hints |
| Progress | Upload progress, subscription usage |
| Popover | Filter popovers, date pickers |
| ScrollArea | Custom scrollbar for panels |

---

## Phase 5: Refactor domain-specific components

| Component | Action |
|-----------|--------|
| `combobox.tsx` | Refactor to use Command + Popover pattern |
| `command-palette.tsx` | Refactor to use shadcn Command |
| `toast.tsx` | Consider `sonner` for richer notifications |
| `data-table.tsx` | Update to use shadcn Table + new Checkbox |
| `list-toolbar.tsx` | Update to use shadcn Input |
| `resource-manager.tsx` | Update internal primitives |

---

## Keep as Custom (no shadcn equivalent)

- `numpad.tsx` — POS-specific numeric keypad
- `palette-picker.tsx` — theme palette selector
- `logo.tsx` — brand SVG
- `locale-picker.tsx` — language selector
- `image-upload.tsx` — drag-and-drop image upload
- `media-field.tsx` — media slot with upload/replace/remove
- `spinner.tsx` — loading icon

---

## Risk Assessment

| Phase | Risk | Effort | Value |
|-------|------|--------|-------|
| 1 (Foundation) | Medium — token rename touches everything | Medium | High |
| 2 (Primitives) | Low — near-identical replacements | Low | Medium |
| 3 (Radix) | Medium — API changes in Dialog/Drawer consumers | High | High |
| 4 (New components) | Low — additive | Medium | Medium |
| 5 (Domain refactor) | Low — incremental | Medium | Medium |

## Recommended Order
Start with Phase 1 + Phase 2 (safest, establishes base), then Phase 3 (highest UX value), then Phase 4 + 5 (incremental).
