import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { api, type Paginated } from "@/lib/api";
import { useDebouncedValue } from "@/lib/use-debounced-value";
import { useQuery } from "@tanstack/react-query";
import { Check, ChevronDown, X } from "lucide-react";
import { useEffect, useId, useRef, useState } from "react";

interface ComboboxProps<T extends { id: string }> {
  endpoint: string;
  queryKey: string;
  value: string | undefined;
  onChange: (id: string | undefined, option: T | undefined) => void;
  label: (option: T) => string;
  hint?: (option: T) => string | null;
  placeholder?: string;
  emptyLabel?: string;
  perPage?: number;
  disabled?: boolean;
  clearOnSelect?: boolean;
}

export function Combobox<T extends { id: string }>({
  endpoint,
  queryKey,
  value,
  onChange,
  label,
  hint,
  placeholder = "Search…",
  emptyLabel = "No matches",
  perPage = 20,
  disabled,
  clearOnSelect = false,
}: ComboboxProps<T>) {
  const listId = useId();
  const wrapper = useRef<HTMLDivElement>(null);

  const [open, setOpen] = useState(false);
  const [term, setTerm] = useState("");
  const [selected, setSelected] = useState<T | null>(null);
  const [highlight, setHighlight] = useState(0);

  const search = useDebouncedValue(term, 300);

  const { data, isFetching } = useQuery({
    queryKey: [queryKey, "combobox", search, perPage],
    queryFn: () => {
      const params = new URLSearchParams({ per_page: String(perPage) });
      if (search) params.set("search", search);
      return api<Paginated<T>>(`${endpoint}?${params.toString()}`);
    },
    enabled: open,
    staleTime: 30_000,
  });

  const options = data?.data ?? [];

  useEffect(() => {
    if (!open) return;
    const onPointerDown = (event: MouseEvent) => {
      if (!wrapper.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener("mousedown", onPointerDown);
    return () => document.removeEventListener("mousedown", onPointerDown);
  }, [open]);

  const choose = (option: T) => {
    setSelected(clearOnSelect ? null : option);
    setTerm("");
    setOpen(false);
    onChange(option.id, option);
  };

  const clear = () => {
    setSelected(null);
    setTerm("");
    onChange(undefined, undefined);
  };

  return (
    <div ref={wrapper} className="relative">
      <div className="relative">
        <Input
          role="combobox"
          aria-expanded={open}
          aria-controls={listId}
          autoComplete="off"
          disabled={disabled}
          placeholder={selected ? label(selected) : placeholder}
          value={open ? term : selected ? label(selected) : ""}
          onFocus={() => setOpen(true)}
          onChange={(e) => {
            setTerm(e.target.value);
            setHighlight(0);
            setOpen(true);
          }}
          onKeyDown={(e) => {
            if (e.key === "ArrowDown") {
              e.preventDefault();
              setOpen(true);
              setHighlight((h) => Math.min(h + 1, Math.max(options.length - 1, 0)));
            } else if (e.key === "ArrowUp") {
              e.preventDefault();
              setHighlight((h) => Math.max(h - 1, 0));
            } else if (e.key === "Enter" && open && options[highlight]) {
              e.preventDefault();
              choose(options[highlight]);
            } else if (e.key === "Escape") {
              setOpen(false);
            }
          }}
          className="pe-16"
        />
        <div className="absolute end-2 top-1/2 flex -translate-y-1/2 items-center gap-1">
          {isFetching && <Spinner className="h-3.5 w-3.5" />}
          {(selected || term) && !disabled && (
            <button type="button" onClick={clear} aria-label="Clear" className="text-[var(--muted)] hover:text-[var(--fg)]">
              <X className="h-3.5 w-3.5" />
            </button>
          )}
          <ChevronDown className="h-4 w-4 text-[var(--muted)]" />
        </div>
      </div>

      {open && (
        <ul
          id={listId}
          role="listbox"
          className="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-md border border-[var(--border)] bg-[var(--surface)] py-1 shadow-lg"
        >
          {options.length === 0 && !isFetching && (
            <li className="px-3 py-2 text-sm text-[var(--muted)]">{emptyLabel}</li>
          )}
          {options.map((option, index) => {
            const extra = hint?.(option);
            return (
              <li key={option.id}>
                <button
                  type="button"
                  role="option"
                  aria-selected={option.id === value}
                  onMouseEnter={() => setHighlight(index)}
                  onClick={() => choose(option)}
                  className={`flex w-full items-center justify-between gap-2 px-3 py-2 text-start text-sm ${
                    index === highlight ? "bg-[var(--surface-2)]" : ""
                  }`}
                >
                  <span className="min-w-0 truncate">
                    {label(option)}
                    {extra && <span className="text-[var(--muted)]"> · {extra}</span>}
                  </span>
                  {option.id === value && <Check className="h-3.5 w-3.5 shrink-0" />}
                </button>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
