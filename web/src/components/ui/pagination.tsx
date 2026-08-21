import { ChevronLeft, ChevronRight } from "lucide-react";
import { Button } from "@/components/ui/button";

interface PaginationProps {
  page: number;
  lastPage: number;
  onPageChange: (page: number) => void;
  /** Left-side text, e.g. "Page 2 of 5" or a translated equivalent — caller owns the copy. */
  label: React.ReactNode;
}

export function Pagination({ page, lastPage, onPageChange, label }: PaginationProps) {
  if (lastPage <= 1) return null;

  return (
    <div className="flex items-center justify-between border-t border-[var(--border)] px-4 py-3">
      <p className="text-sm text-[var(--muted)]">{label}</p>
      <div className="flex gap-1">
        <Button
          variant="outline"
          size="icon"
          className="h-8 w-8"
          disabled={page <= 1}
          onClick={() => onPageChange(page - 1)}
        >
          <ChevronLeft className="h-4 w-4 rtl:scale-x-[-1]" />
        </Button>
        <Button
          variant="outline"
          size="icon"
          className="h-8 w-8"
          disabled={page >= lastPage}
          onClick={() => onPageChange(page + 1)}
        >
          <ChevronRight className="h-4 w-4 rtl:scale-x-[-1]" />
        </Button>
      </div>
    </div>
  );
}
