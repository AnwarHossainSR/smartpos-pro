import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useTaxRateMutations } from "@/features/tax/hooks";
import type { TaxRate } from "@/features/tax/types";

const schema = z.object({
  name: z.string().min(1, "Name is required"),
  code: z.string().optional(),
  rate: z.number().min(0, "Must be ≥ 0"),
  type: z.enum(["percentage", "fixed"]),
  is_compound: z.boolean(),
  is_active: z.boolean(),
});
type FormValues = z.infer<typeof schema>;

export function TaxRateForm({ editing, onSuccess }: { editing?: TaxRate | null; onSuccess: () => void }) {
  const { create, update } = useTaxRateMutations();
  const isEdit = !!editing;

  const { register, handleSubmit, watch, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: editing
      ? { name: editing.name, code: editing.code ?? "", rate: editing.rate, type: editing.type, is_compound: editing.is_compound, is_active: editing.is_active }
      : { name: "", code: "", rate: 0, type: "percentage", is_compound: false, is_active: true },
  });

  const pending = create.isPending || update.isPending;
  const type = watch("type");

  function onSubmit(values: FormValues) {
    const payload = { ...values, code: values.code || undefined };
    const mutation = isEdit ? update.mutateAsync({ id: editing!.id, data: payload }) : create.mutateAsync(payload);
    mutation.then(onSuccess).catch(() => {});
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <div>
        <Label>Name *</Label>
        <Input {...register("name")} placeholder="e.g. CGST 9%" />
        {errors.name && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.name.message}</p>}
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
      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label>Type</Label>
          <Select {...register("type")}>
            <option value="percentage">Percentage</option>
            <option value="fixed">Fixed amount</option>
          </Select>
        </div>
        <div>
          <Label>Rate {type === "percentage" ? "(%)" : "(flat, per unit)"} *</Label>
          <Input type="number" step="0.0001" min="0" {...register("rate", { valueAsNumber: true })} />
          {errors.rate && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.rate.message}</p>}
        </div>
      </div>
      <label className="flex items-start gap-2 text-sm">
        <input type="checkbox" className="mt-0.5 h-4 w-4 accent-[var(--primary)]" {...register("is_compound")} />
        <span>
          Compound component
          <span className="block text-xs text-[var(--muted)]">
            Applies on top of the components before it in a group (e.g. PST calculated on GST), not on the
            product price alone.
          </span>
        </span>
      </label>
      <div className="flex justify-end gap-2 pt-2">
        <Button type="submit" disabled={pending}>
          {pending ? "Saving…" : isEdit ? "Update rate" : "Create rate"}
        </Button>
      </div>
    </form>
  );
}
