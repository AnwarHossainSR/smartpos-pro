import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { Info } from "lucide-react";
import { api, ApiError } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { Badge } from "@/components/ui/badge";
import { toast } from "@/components/ui/toast";
import { useTaxProfiles } from "@/features/tax/hooks";
import type { PriceDisplay, TaxProfile } from "@/features/tax/types";

/** The subset of `GET /settings/business` this card reads/writes — shares the
 *  `["business-settings"]` query key with the main Settings page so both stay in sync. */
interface BusinessTaxSettings {
  tax_number: string | null;
  tax_profile_id: string | null;
  tax_profile: TaxProfile | null;
  price_display: PriceDisplay;
}

interface FormValues {
  tax_profile_id: string;
  price_display: PriceDisplay;
  tax_number: string;
}

const PROFILE_INFO_ROWS: { key: keyof TaxProfile; label: string }[] = [
  { key: "sourcing", label: "Sourcing" },
  { key: "rounding_scope", label: "Rounding scope" },
  { key: "rounding_mode", label: "Rounding mode" },
];

export function TaxProfileCard({ canManage }: { canManage: boolean }) {
  const qc = useQueryClient();
  const { data: profilesData, isLoading: profilesLoading } = useTaxProfiles();
  const { data, isLoading } = useQuery({
    queryKey: queryKeys.businessSettings,
    queryFn: () => api<{ data: BusinessTaxSettings }>("/settings/business"),
  });

  // `values` (rather than `defaultValues` + a manual useEffect) keeps the form in sync whenever
  // the query resolves or refetches, without a setState-in-effect — same pattern the main
  // Settings page's business form uses.
  const { register, handleSubmit, watch, formState: { isDirty } } = useForm<FormValues>({
    values: data?.data
      ? {
          tax_profile_id: data.data.tax_profile_id ? String(data.data.tax_profile_id) : "",
          price_display: data.data.price_display ?? "exclusive",
          tax_number: data.data.tax_number ?? "",
        }
      : undefined,
  });

  const mutation = useMutation({
    mutationFn: (body: { tax_profile_id: string | null; price_display: PriceDisplay; tax_number: string | null }) =>
      api("/settings/business", { method: "PUT", body }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.businessSettings });
      toast("Tax settings saved");
    },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Could not save tax settings", "error"),
  });

  const profiles = profilesData?.data ?? [];
  const taxProfileId = watch("tax_profile_id");
  const selectedProfile = profiles.find((p) => String(p.id) === taxProfileId) ?? data?.data.tax_profile ?? null;

  function onSubmit(values: FormValues) {
    mutation.mutate({
      tax_profile_id: values.tax_profile_id || null,
      price_display: values.price_display,
      tax_number: values.tax_number || null,
    });
  }

  if (isLoading || profilesLoading) {
    return (
      <div className="space-y-3">
        <Skeleton className="h-10 w-full" />
        <Skeleton className="h-10 w-full" />
        <Skeleton className="h-24 w-full" />
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <div>
        <Label>Country tax profile</Label>
        <Select disabled={!canManage} {...register("tax_profile_id")}>
          <option value="">— None (no jurisdiction defaults) —</option>
          {profiles.map((p) => (
            <option key={p.id} value={p.id}>{p.name} ({p.code})</option>
          ))}
        </Select>
      </div>

      {selectedProfile && (
        <div className="rounded-[var(--radius)] border border-dashed border-[var(--border)] p-3 text-sm space-y-1.5">
          <p className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-[var(--muted)]">
            <Info className="h-3.5 w-3.5" /> Profile details (read-only)
          </p>
          {PROFILE_INFO_ROWS.map((row) => (
            <div key={row.key} className="flex justify-between gap-3">
              <span className="text-[var(--muted)] capitalize">{row.label}</span>
              <span className="capitalize">{String(selectedProfile[row.key]).replace(/_/g, " ")}</span>
            </div>
          ))}
          <div className="flex justify-between gap-3">
            <span className="text-[var(--muted)]">Profile price display</span>
            <Badge tone="default" className="capitalize">{selectedProfile.price_display}</Badge>
          </div>
          <div className="flex justify-between gap-3">
            <span className="text-[var(--muted)]">Tax number label</span>
            <span>{selectedProfile.tax_number_label}</span>
          </div>
          {selectedProfile.required_invoice_fields.length > 0 && (
            <div>
              <span className="text-[var(--muted)]">Required on invoice</span>
              <div className="mt-1 flex flex-wrap gap-1">
                {selectedProfile.required_invoice_fields.map((f) => (
                  <Badge key={f}>{f}</Badge>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      <div>
        <Label>Price display</Label>
        <Select disabled={!canManage} {...register("price_display")}>
          <option value="exclusive">Exclusive — prices shown before tax</option>
          <option value="inclusive">Inclusive — prices shown with tax included</option>
        </Select>
      </div>

      <div>
        <Label>{selectedProfile?.tax_number_label ?? "Tax number"}</Label>
        <Input disabled={!canManage} {...register("tax_number")} placeholder="e.g. registration / VAT number" />
      </div>

      {canManage && (
        <Button type="submit" className="w-full sm:w-auto" disabled={!isDirty || mutation.isPending}>
          {mutation.isPending ? "Saving…" : "Save tax settings"}
        </Button>
      )}
    </form>
  );
}
