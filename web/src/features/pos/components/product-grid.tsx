import { Star } from "lucide-react";
import { Skeleton } from "@/components/ui/skeleton";
import type { PosViewMode } from "@/features/pos/hooks/use-pos-catalog";
import type { Product } from "@/features/pos/types";
import { cn, formatCurrency } from "@/lib/utils";

interface ProductGridProps {
  products: Product[];
  isLoading: boolean;
  viewMode: PosViewMode;
  favorites: Set<string>;
  onSelect: (product: Product) => void;
  onToggleFavorite: (productId: string) => void;
}

const GRID_CLASS = "grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4";

function FavoriteToggle({
  active,
  className,
  onToggle,
}: {
  active: boolean;
  className?: string;
  onToggle: () => void;
}) {
  return (
    <button
      onClick={(e) => {
        e.stopPropagation();
        onToggle();
      }}
      className={cn("flex h-6 w-6 items-center justify-center cursor-pointer", className)}
    >
      <Star
        className={cn(
          "h-3.5 w-3.5",
          active ? "fill-amber-400 text-amber-400" : "text-[var(--muted)]",
        )}
      />
    </button>
  );
}

function StockLine({ product }: { product: Product }) {
  const stock = product.stock ?? 0;
  const low = (product.min_stock_level ?? 0) > 0 && stock <= (product.min_stock_level ?? 0);

  return (
    <div className="flex items-center gap-1.5">
      <p
        className={cn(
          "text-xs",
          stock <= 0
            ? "text-[var(--color-danger)]"
            : low
              ? "text-amber-600"
              : "text-[var(--muted)]",
        )}
      >
        Stock: {stock}
      </p>
      {low && stock > 0 && (
        <span className="inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
          Low
        </span>
      )}
    </div>
  );
}

/** The tappable product list — the cashier's fastest path to a line item after the scanner. */
export function ProductGrid({
  products,
  isLoading,
  viewMode,
  favorites,
  onSelect,
  onToggleFavorite,
}: ProductGridProps) {
  if (isLoading) {
    return (
      <div className={cn(viewMode === "grid" ? GRID_CLASS : "space-y-1")}>
        {Array.from({ length: 12 }).map((_, i) => (
          <Skeleton
            key={i}
            className={cn(
              viewMode === "grid" ? "h-24 rounded-[var(--radius)]" : "h-12 rounded-[var(--radius)]",
            )}
          />
        ))}
      </div>
    );
  }

  if (viewMode === "list") {
    return (
      <div className="space-y-1">
        {products.map((p) => (
          <div
            key={p.id}
            className="relative group flex items-center gap-3 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-2 cursor-pointer transition-colors hover:border-[var(--primary)] hover:bg-[var(--surface-2)]"
            onClick={() => onSelect(p)}
          >
            {p.image_url ? (
              <img src={p.image_url} alt="" className="h-10 w-10 rounded object-cover shrink-0" />
            ) : (
              <div className="h-10 w-10 rounded bg-[var(--surface-2)] shrink-0" />
            )}
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium truncate">{p.name}</p>
              {p.category && <p className="text-xs text-[var(--muted)]">{p.category.name}</p>}
            </div>
            <p className="text-sm font-semibold text-[var(--primary)] shrink-0">
              {formatCurrency(p.selling_price)}
            </p>
            <p
              className={cn(
                "text-xs shrink-0",
                (p.stock ?? 0) <= 0 ? "text-[var(--color-danger)]" : "text-[var(--muted)]",
              )}
            >
              Stock: {p.stock ?? 0}
            </p>
            <FavoriteToggle
              active={favorites.has(p.id)}
              className="shrink-0"
              onToggle={() => onToggleFavorite(p.id)}
            />
          </div>
        ))}
        {products.length === 0 && (
          <div className="py-12 text-center text-sm text-[var(--muted)]">No products found</div>
        )}
      </div>
    );
  }

  return (
    <div className={GRID_CLASS}>
      {products.map((p) => (
        <div key={p.id} className="relative group">
          <button
            onClick={() => onSelect(p)}
            disabled={(p.stock ?? 0) <= 0}
            className={cn(
              "flex w-full cursor-pointer flex-col rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-3 text-start",
              "transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary)]",
              "enabled:hover:-translate-y-0.5 enabled:hover:border-[var(--primary)] enabled:hover:bg-[var(--surface-2)] enabled:hover:shadow-md",
              "enabled:active:translate-y-0 enabled:active:scale-[0.98]",
              "disabled:cursor-not-allowed disabled:opacity-50",
            )}
          >
            {p.image_url && (
              <img src={p.image_url} alt="" className="mb-2 h-16 w-full rounded object-cover" />
            )}
            <p className="line-clamp-2 text-sm font-medium transition-colors group-enabled:group-hover:text-[var(--primary)]">
              {p.name}
            </p>
            {p.category && <p className="mt-0.5 text-xs text-[var(--muted)]">{p.category.name}</p>}
            <p className="mt-2 font-semibold text-[var(--primary)]">
              {formatCurrency(p.selling_price)}
            </p>
            <StockLine product={p} />
          </button>
          <FavoriteToggle
            active={favorites.has(p.id)}
            className="absolute top-1.5 right-1.5 rounded-full opacity-0 group-hover:opacity-100 transition-opacity bg-[var(--surface)]/80"
            onToggle={() => onToggleFavorite(p.id)}
          />
        </div>
      ))}
      {products.length === 0 && (
        <div className="col-span-4 py-12 text-center text-sm text-[var(--muted)]">
          No products found
        </div>
      )}
    </div>
  );
}
