import { zodResolver } from "@hookform/resolvers/zod";
import { ArrowDown, ArrowUp, Plus, X } from "lucide-react";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useAllTaxRates, useTaxGroupMutations } from "@/features/tax/hooks";
import type { TaxGroup, TaxRate } from "@/features/tax/types";

const schema = z.object({
  name: z.string().min(1, "Name is required"),
  code: z.string().optional(),
  is_default: z.boolean(),
  is_active: z.boolean(),
});
type FormValues = z.infer<typeof schema>;

/**
 * The ordered multi-component builder. Order matters — a compound component (e.g.
 * PST-on-GST) must apply after the components it compounds on, so this is a
 * reorderable list (up/down, no drag-and-drop dependency needed) rather than checkboxes.
 * `priority` sent to the API is just the resulting array index.
 */
export function TaxGroupForm({
  editing,
  onSuccess,
}: {
  editing?: TaxGroup | null;
  onSuccess: () => void;
}) {
  const { create, update } = useTaxGroupMutations();
  const { data: allRates } = useAllTaxRates();
  const isEdit = !!editing;

  const [components, setComponents] = useState<TaxRate[]>(() =>
    editing ? [...editing.rates].sort((a, b) => (a.priority ?? 0) - (b.priority ?? 0)) : [],
  );
  const [pickerValue, setPickerValue] = useState("");

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: editing
      ? {
          name: editing.name,
          code: editing.code ?? "",
          is_default: editing.is_default,
          is_active: editing.is_active,
        }
      : { name: "", code: "", is_default: false, is_active: true },
  });

  const pending = create.isPending || update.isPending;
  const availableRates = (allRates?.data ?? []).filter(
    (r) => !components.some((c) => c.id === r.id),
  );

  function addComponent() {
    const rate = availableRates.find((r) => String(r.id) === pickerValue);
    if (!rate) return;
    setComponents((prev) => [...prev, rate]);
    setPickerValue("");
  }

  function removeComponent(id: string) {
    setComponents((prev) => prev.filter((c) => c.id !== id));
  }

  function move(index: number, dir: -1 | 1) {
    setComponents((prev) => {
      const next = [...prev];
      const target = index + dir;
      if (target < 0 || target >= next.length) return prev;
      [next[index], next[target]] = [next[target], next[index]];
      return next;
    });
  }

  function onSubmit(values: FormValues) {
    const payload = {
      ...values,
      code: values.code || undefined,
      rates: components.map((c, i) => ({ tax_rate_id: c.id, priority: i })),
    };
    const mutation = isEdit
      ? update.mutateAsync({ id: editing!.id, data: payload })
      : create.mutateAsync(payload);
    mutation.then(onSuccess).catch(() => {
      /* ignore */
    });
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <div>
        <Label>Name *</Label>
        <Input {...register("name")} placeholder="e.g. Standard GST (CGST + SGST)" />
        {errors.name && (
          <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.name.message}</p>
        )}
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label>Code</Label>
          <Input {...register("code")} placeholder="Optional" />
        </div>
        <div>
          <Label>Status</Label>
          <Select {...register("is_active", { setValueAs: (v: string) => v === "true" })}>
            <option value="true">Active</option>
            <option value="false">Inactive</option>
          </Select>
        </div>
      </div>
      <label className="flex items-center gap-2 text-sm">
        <input
          type="checkbox"
          className="h-4 w-4 accent-[var(--primary)]"
          {...register("is_default")}
        />
        Default group for new products
      </label>

      <div className="rounded-[var(--radius)] border border-dashed border-[var(--border)] p-3 space-y-3">
        <div className="flex items-center justify-between">
          <p className="text-xs font-medium uppercase tracking-wide text-[var(--muted)]">
            Components ({components.length})
          </p>
        </div>

        {components.length === 0 ? (
          <p className="py-2 text-center text-sm text-[var(--muted)]">
            No components yet — add at least one tax rate below.
          </p>
        ) : (
          <ol className="space-y-1.5">
            {components.map((c, i) => (
              <li
                key={c.id}
                className="flex items-center gap-2 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] px-2.5 py-2 text-sm"
              >
                <span className="w-5 shrink-0 text-center text-xs text-[var(--muted)]">
                  {i + 1}
                </span>
                <span className="flex-1 truncate font-medium">{c.name}</span>
                <span className="text-xs text-[var(--muted)]">
                  {c.type === "percentage" ? `${c.rate}%` : c.rate.toFixed(2)}
                </span>
                {c.is_compound && <Badge tone="info">compound</Badge>}
                <div className="flex items-center gap-0.5">
                  <button
                    type="button"
                    disabled={i === 0}
                    onClick={() => move(i, -1)}
                    className="cursor-pointer rounded p-1 hover:bg-[var(--surface-2)] disabled:cursor-not-allowed disabled:opacity-30"
                    aria-label="Move up"
                  >
                    <ArrowUp className="h-3.5 w-3.5" />
                  </button>
                  <button
                    type="button"
                    disabled={i === components.length - 1}
                    onClick={() => move(i, 1)}
                    className="cursor-pointer rounded p-1 hover:bg-[var(--surface-2)] disabled:cursor-not-allowed disabled:opacity-30"
                    aria-label="Move down"
                  >
                    <ArrowDown className="h-3.5 w-3.5" />
                  </button>
                  <button
                    type="button"
                    onClick={() => removeComponent(c.id)}
                    className="cursor-pointer rounded p-1 text-[var(--color-danger)] hover:bg-[var(--surface-2)]"
                    aria-label="Remove component"
                  >
                    <X className="h-3.5 w-3.5" />
                  </button>
                </div>
              </li>
            ))}
          </ol>
        )}

        {components.some((c) => c.is_compound) && (
          <p className="text-xs text-[var(--muted)]">
            Compound components apply to the base plus every component listed above them — check the
            order.
          </p>
        )}

        <div className="flex gap-2">
          <Select
            value={pickerValue}
            onChange={(e) => setPickerValue(e.target.value)}
            className="flex-1"
          >
            <option value="">
              {availableRates.length === 0
                ? "No more rates available"
                : "Select a tax rate to add…"}
            </option>
            {availableRates.map((r) => (
              <option key={r.id} value={r.id}>
                {r.name} ({r.type === "percentage" ? `${r.rate}%` : r.rate.toFixed(2)})
              </option>
            ))}
          </Select>
          <Button type="button" variant="outline" disabled={!pickerValue} onClick={addComponent}>
            <Plus className="h-4 w-4" /> Add
          </Button>
        </div>
      </div>

      <div className="flex justify-end gap-2 pt-2">
        <Button type="submit" disabled={pending}>
          {pending ? "Saving…" : isEdit ? "Update group" : "Create group"}
        </Button>
      </div>
    </form>
  );
}
