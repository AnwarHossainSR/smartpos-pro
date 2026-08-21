/**
 * The dining-table shape, declared once for both consumers: the floor-plan screen
 * (`/tables`) and the POS table selector, which previously each had their own near-identical
 * copy — one with `area`, one with `x`/`y`, both drifting from the same API resource.
 */

export type TableStatus = "available" | "occupied" | "reserved";

export interface DiningTable {
  id: string;
  name: string;
  seats: number;
  status: TableStatus;
  /** Free-text zone ("Terrace", "First floor") — optional, only the floor-plan screen edits it. */
  area?: string | null;
  /** Floor-plan coordinates; returned by the API but not rendered anywhere yet. */
  x?: number;
  y?: number;
}
