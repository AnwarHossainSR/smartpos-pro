"use client";

import { useState, useCallback, useEffect, useRef, useMemo } from "react";
import { api } from "@/lib/api";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslation } from "@/i18n/provider";
import { useAuth } from "@/stores/auth";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, CardTitle, CardContent, CardFooter } from "@/components/ui/card";
import { Table as TableIcon, X } from "lucide-react";
import { useDrag } from "react-use";
import { Skeleton } from "@/components/ui/skeleton";

interface Table {
  id: string;
  name: string;
  seats: number;
  status: "available" | "occupied" | "reserved";
  x: number;
  y: number;
  area?: string;
}

interface TableCardProps {
  table: Table;
  onSelect: (table: Table) => void;
  onDragEnd: (tableId: string, x: number, y: number) => void;
}

function TableCard({ table, onSelect, onDragEnd }: TableCardProps) {
  const [isDragging, setIsDragging] = useState(false);
  const dragRef = useRef<HTMLDivElement>(null);
  const {
    state: { clientX, clientY },
    handleDragEnd,
    isDragging: isDragState,
  } = useDrag({
    element: dragRef,
    onDragEnd: (provided, monitor) => {
      const { deltaX, deltaY } = monitor;
      const rect = dragRef.current?.getBoundingClientRect();
      if (rect) {
        onDragEnd(table.id, rect.x + deltaX, rect.y + deltaY);
      }
      setIsDragging(false);
    },
  });

  const statusClass = {
    available: "bg-green-100 text-green-800",
    occupied: "bg-yellow-100 text-yellow-800",
    reserved: "bg-red-100 text-red-800",
  }[table.status];

  return (
    <div
      ref={dragRef}
      className={cn(
        "table-card relative group cursor-pointer select-none flex-shrink-0 w-48 h-64 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] transition-all duration-200 " + statusClass,
        "shadow-sm",
        isDragging ? "opacity-50" : "",
        isDragState ? "opacity-80" : "",
      )}
      style={{ transform: `translate(${isDragging ? clientX - 24 : 0}px, ${isDragging ? clientY - 32 : 0}px)` }}
    >
      <div className="absolute top-2 right-2">
        <Button variant="ghost" size="icon" className="text-[var(--muted)] hover:text-[var(--color-danger)]">
          <X className="h-3.5 w-3.5" />
        </Button>
      </div>
      <div className="p-4 flex flex-col h-full">
        <div className="flex items-center justify-between mb-3">
          <span className="font-medium truncate">{table.name}</span>
          <span className="text-xs text-[var(--muted)]">{table.seats} {t("tables.seats")}</span>
        </div>
        <div className="h-24 flex items-center justify-center">
          <span className="text-xs capitalize">{t(`tables.${table.status}`)}</span>
        </div>
        <p className="mt-auto text-sm text-[var(--muted)] text-center">
          {table.area || "-"}
        </p>
      </div>
    </div>
  );
}

export default function TablesPage() {
  const { user } = useAuth();
  const { t, r: translate } = useTranslation();
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const [selectedTable, setSelectedTable] = useState<Table | null>(null);

  const { data: tablesData, isLoading } = useQuery<Table[]>({
    queryKey: ["dining-tables", search],
    queryFn: () => {
      const params = new URLSearchParams();
      if (search) params.set("search", search);
      if (user?.branch_id) params.set("branch_id", user.branch_id);
      return api<Table[]>(`/v1/tables?${params.toString()}`);
    },
    enabled: !!user,
  });

  const tables = (tablesData ?? []).filter((t) => {
    const searchLower = search.toLowerCase();
    return t.name.toLowerCase().includes(searchLower) || `${t.seats}`.includes(searchLower);
  });

  const handleTableSelect = useCallback((table: Table) => {
    setSelectedTable(table);
  }, []);

  const handleDragEnd = useCallback((tableId: string, x: number, y: number) => {
    qc.setQueryData<Table[]>(["dining-tables"], (old) => {
      if (!old) return old;
      return old.map((t) => (t.id === tableId ? { ...t, x, y } : t));
    });
  }, [qc]);

  useEffect(() => {
    setSearch("");
  }, []);

  if (!user) {
    return null;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold">{t("tables.title")}</h2>
        <Button
          variant="outline"
          onClick={() => setSelectedTable(null)}
          disabled={isLoading}
        >
          {t("common.close")}
        </Button>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
          {Array.from({ length: 12 }).map((_, i) => (
            <Skeleton key={i} className="h-48 rounded-[var(--radius)]" />
          ))}
        </div>
      ) : tables.length === 0 ? (
        <div className="text-center py-12 text-sm text-[var(--muted)]">
          {t("common.no_data_found")}
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
          {tables.map((table) => (
            <TableCard
              key={table.id}
              table={table}
              onSelect={() => handleTableSelect(table)}
              onDragEnd={handleDragEnd}
            />
          ))}
        </div>
      )}

      {selectedTable && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center">
          <div className="bg-[var(--surface)] rounded-[var(--radius)] p-6 w-full max-w-sm shadow-2l">
            <h3 className="text-xl font-semibold mb-4">{selectedTable.name}</h3>
            <p className="text-[var(--muted)] mb-4">{t(`tables.${selectedTable.status}`)}</p>
            <p className="text-lg font-medium mb-6">{selectedTable.seats} {t("tables.seats")}</p>
            {selectedTable.status === "available" && (
              <Button
                onClick={() => {
                  // Open sale with this table pre-selected
                  // This would integrate with POS flow
                  qc.setQueryData(["pos", "selected-table"], selectedTable);
                  setSelectedTable(null);
                }}
                className="w-full"
              >
                {t("pos.table_selector")}
              </Button>
            )}
            <Button
              variant="outline"
              onClick={() => setSelectedTable(null)}
              className="mt-3 w-full"
            >
              {t("common.close")}
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}