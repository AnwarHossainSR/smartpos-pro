import { Download, Plus, RotateCw, Search } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

interface ListToolbarProps {
  search: string;
  onSearchChange: (value: string) => void;
  searchPlaceholder?: string;
  /** Extra filter controls (selects, date pickers, …) rendered next to search. */
  filters?: React.ReactNode;
  onExport?: () => void;
  exportLabel?: string;
  addLabel?: string;
  onAdd?: () => void;
  onRefresh?: () => void;
}

export function ListToolbar({
  search,
  onSearchChange,
  searchPlaceholder = "Search…",
  filters,
  onExport,
  exportLabel = "Export",
  addLabel,
  onAdd,
  onRefresh,
}: ListToolbarProps) {
  const [spinning, setSpinning] = useState(false);

  const handleRefresh = () => {
    if (!onRefresh) return;
    setSpinning(true);
    onRefresh();
    setTimeout(() => setSpinning(false), 600);
  };

  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
        <div className="relative sm:max-w-xs sm:flex-1">
          <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" />
          <Input
            className="ps-9"
            placeholder={searchPlaceholder}
            value={search}
            onChange={(e) => onSearchChange(e.target.value)}
          />
        </div>
        {filters}
      </div>
      <div className="flex items-center gap-2">
        {onRefresh && (
          <Button variant="outline" size="sm" onClick={handleRefresh}>
            <RotateCw className={`h-4 w-4 ${spinning ? "animate-spin" : ""}`} />
          </Button>
        )}
        {onExport && (
          <Button variant="outline" size="sm" onClick={onExport}>
            <Download className="h-4 w-4" />
            {exportLabel}
          </Button>
        )}
        {onAdd && addLabel && (
          <Button onClick={onAdd} size="sm">
            <Plus className="h-4 w-4" />
            {addLabel}
          </Button>
        )}
      </div>
    </div>
  );
}
