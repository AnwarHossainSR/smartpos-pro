import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useAllTaxGroups, useTaxZoneMutations } from "@/features/tax/hooks";
import type { TaxZone } from "@/features/tax/types";

const schema = z.object({
  name: z.string().min(1, "Name is required"),
  country_code: z.string().max(2).optional(),
  state: z.string().optional(),
  postcode_pattern: z.string().optional(),
  tax_group_id: z.string().nullable(),
  is_default: z.boolean(),
  priority: z.number().min(0),
  is_active: z.boolean(),
});
type FormValues = z.infer<typeof schema>;

/** A jurisdiction (country/state/postcode) mapped to the tax group that applies inside
 *  it. Matched most-specific-first by the tax engine, ties broken on `priority`. */
export function TaxZoneForm({ editing, onSuccess }: { editing?: TaxZone | null; onSuccess: () => void }) {
  const { create, update } = useTaxZoneMutations();
  const { data: groups } = useAllTaxGroups();
  const isEdit = !!editing;

  const { register, handleSubmit, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: editing
      ? {
          name: editing.name,
          country_code: editing.country_code ?? "",
          state: editing.state ?? "",
          postcode_pattern: editing.postcode_pattern ?? "",
          tax_group_id: editing.tax_group_id,
          is_default: editing.is_default,
          priority: editing.priority,
          is_active: editing.is_active,
        }
      : { name: "", country_code: "", state: "", postcode_pattern: "", tax_group_id: null, is_default: false, priority: 0, is_active: true },
  });

  const pending = create.isPending || update.isPending;

  function onSubmit(values: FormValues) {
    const payload = {
      ...values,
      country_code: values.country_code ? values.country_code.toUpperCase() : undefined,
      state: values.state || undefined,
      postcode_pattern: values.postcode_pattern || undefined,
    };
    const mutation = isEdit ? update.mutateAsync({ id: editing!.id, data: payload }) : create.mutateAsync(payload);
    mutation.then(onSuccess).catch(() => {});
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <div>
        <Label>Name *</Label>
        <Input {...register("name")} placeholder="e.g. West Bengal (India)" />
        {errors.name && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.name.message}</p>}
      </div>
      <div className="grid grid-cols-3 gap-3">
        <div>
          <Label>Country code</Label>
          <Input {...register("country_code")} placeholder="IN" maxLength={2} className="uppercase" />
        </div>
        <div>
          <Label>State / region</Label>
          <Input {...register("state")} placeholder="Optional" />
        </div>
        <div>
          <Label>Postcode pattern</Label>
          <Input {...register("postcode_pattern")} placeholder="e.g. 7*" />
        </div>
      </div>
      <div>
        <Label>Tax group *</Label>
        <Select {...register("tax_group_id", { setValueAs: (v) => (v ? String(v) : null) })}>
          <option value="">— Select —</option>
          {groups?.data?.map((g) => (
            <option key={g.id} value={g.id}>{g.name}</option>
          ))}
        </Select>
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label>Priority</Label>
          <Input type="number" min="0" {...register("priority", { valueAsNumber: true })} />
          <p className="mt-1 text-xs text-[var(--muted)]">Higher priority wins when zones tie on specificity.</p>
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
        <input type="checkbox" className="h-4 w-4 accent-[var(--primary)]" {...register("is_default")} />
        Default zone (fallback when nothing more specific matches)
      </label>
      <div className="flex justify-end gap-2 pt-2">
        <Button type="submit" disabled={pending}>
          {pending ? "Saving…" : isEdit ? "Update zone" : "Create zone"}
        </Button>
      </div>
    </form>
  );
}
