import { cn } from "@/lib/utils";
import { errorMessage } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";

export interface DataTableColumn<T> {
  key: string;
  header: React.ReactNode;
  align?: "left" | "right";
  className?: string;
  render: (row: T) => React.ReactNode;
}

interface DataTableProps<T> {
  columns: DataTableColumn<T>[];
  data: T[];
  rowKey: (row: T) => string | number;
  loading?: boolean;
  loadingRows?: number;
  emptyMessage: React.ReactNode;
  /** Render an inline error row instead of the empty state when the query failed. */
  error?: unknown;
  onRetry?: () => void;
  /** Row actions rendered as the last, unlabeled column (view/edit/delete buttons). */
  actions?: (row: T) => React.ReactNode;
  /** Enables the bulk-select checkbox column. */
  selectable?: boolean;
  selectedKeys?: Set<string | number>;
  onSelectionChange?: (keys: Set<string | number>) => void;
}

export function DataTable<T>({
  columns,
  data,
  rowKey,
  loading,
  loadingRows = 8,
  emptyMessage,
  error,
  onRetry,
  actions,
  selectable,
  selectedKeys,
  onSelectionChange,
}: DataTableProps<T>) {
  const colSpan = columns.length + (selectable ? 1 : 0) + (actions ? 1 : 0);
  const allKeys = data.map(rowKey);
  const allSelected = selectable && allKeys.length > 0 && allKeys.every((k) => selectedKeys?.has(k));
  const someSelected = selectable && allKeys.some((k) => selectedKeys?.has(k));

  function toggleAll() {
    if (!onSelectionChange) return;
    onSelectionChange(allSelected ? new Set() : new Set(allKeys));
  }

  function toggleRow(key: string | number) {
    if (!onSelectionChange || !selectedKeys) return;
    const next = new Set(selectedKeys);
    if (next.has(key)) next.delete(key);
    else next.add(key);
    onSelectionChange(next);
  }

  return (
    <Table>
      <TableHeader className="sticky top-0 z-10 bg-[var(--surface)]">
        <TableRow>
          {selectable && (
            <TableHead className="w-10">
              <input
                type="checkbox"
                className="h-4 w-4 accent-[var(--primary)]"
                checked={!!allSelected}
                ref={(el) => {
                  if (el) el.indeterminate = !allSelected && !!someSelected;
                }}
                onChange={toggleAll}
                aria-label="Select all rows"
              />
            </TableHead>
          )}
          {columns.map((col) => (
            <TableHead key={col.key} className={cn(col.align === "right" && "text-end", col.className)}>
              {col.header}
            </TableHead>
          ))}
          {actions && <TableHead className="w-20" />}
        </TableRow>
      </TableHeader>
      <TableBody>
        {loading ? (
          Array.from({ length: loadingRows }).map((_, i) => (
            <TableRow key={i}>
              {selectable && (
                <TableCell>
                  <Skeleton className="h-4 w-4" />
                </TableCell>
              )}
              {columns.map((col) => (
                <TableCell key={col.key}>
                  <Skeleton className="h-4 w-full" />
                </TableCell>
              ))}
              {actions && (
                <TableCell>
                  <Skeleton className="h-4 w-full" />
                </TableCell>
              )}
            </TableRow>
          ))
        ) : error ? (
          <TableRow>
            <TableCell colSpan={colSpan} className="py-12 text-center">
              <p className="text-sm text-[var(--muted)]">{errorMessage(error, "Could not load this list.")}</p>
              {onRetry && (
                <Button variant="outline" size="sm" className="mt-3" onClick={onRetry}>
                  Try again
                </Button>
              )}
            </TableCell>
          </TableRow>
        ) : data.length === 0 ? (
          <TableRow>
            <TableCell colSpan={colSpan} className="py-12 text-center text-[var(--muted)]">
              {emptyMessage}
            </TableCell>
          </TableRow>
        ) : (
          data.map((row, i) => {
            const key = rowKey(row);
            return (
              <TableRow key={key} className={cn(i % 2 === 1 && "bg-[var(--surface-2)]/40")}>
                {selectable && (
                  <TableCell>
                    <input
                      type="checkbox"
                      className="h-4 w-4 accent-[var(--primary)]"
                      checked={!!selectedKeys?.has(key)}
                      onChange={() => toggleRow(key)}
                      aria-label="Select row"
                    />
                  </TableCell>
                )}
                {columns.map((col) => (
                  <TableCell key={col.key} className={cn(col.align === "right" && "text-end", col.className)}>
                    {col.render(row)}
                  </TableCell>
                ))}
                {actions && (
                  <TableCell>
                    <div className="flex items-center gap-1">{actions(row)}</div>
                  </TableCell>
                )}
              </TableRow>
            );
          })
        )}
      </TableBody>
    </Table>
  );
}
