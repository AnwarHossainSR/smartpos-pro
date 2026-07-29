"use client";

import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { api, ApiError } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Select } from "@/components/ui/select";
import { toast } from "@/components/ui/toast";
import { useAuth } from "@/stores/auth";
import { PalettePicker } from "@/components/ui/palette-picker";
import { useTranslation } from "@/i18n/provider";

interface BusinessSettings {
  name: string;
  email: string | null;
  phone: string | null;
  address: string | null;
  currency: string;
  timezone: string;
  date_format: string;
  tax_number: string | null;
  logo_path: string | null;
}

const schema = z.object({
  name: z.string().min(1, "Business name required"),
  email: z.string().email().optional().or(z.literal("")),
  phone: z.string().optional(),
  address: z.string().optional(),
  currency: z.string().min(1),
  timezone: z.string().min(1),
  date_format: z.string().min(1),
  tax_number: z.string().optional(),
});
type FormValues = z.infer<typeof schema>;

export default function SettingsPage() {
  const { user } = useAuth();
  const qc = useQueryClient();
  const t = useTranslation();

  const { data, isLoading } = useQuery<{ data: BusinessSettings }>({
    queryKey: ["business-settings"],
    queryFn: () => api<{ data: BusinessSettings }>("/settings/business"),
  });

  const { register, handleSubmit, formState: { errors, isDirty } } = useForm<FormValues>({
    values: data?.data
      ? {
          name: data.data.name,
          email: data.data.email ?? "",
          phone: data.data.phone ?? "",
          address: data.data.address ?? "",
          currency: data.data.currency,
          timezone: data.data.timezone,
          date_format: data.data.date_format,
          tax_number: data.data.tax_number ?? "",
        }
      : undefined,
  });

  const mutation = useMutation({
    mutationFn: (d: FormValues) => api("/settings/business", { method: "PUT", body: d }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["business-settings"] }); toast(t("settings.saved")); },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  if (isLoading) return <div className="py-12 text-center text-[var(--muted)]">{t("common.loading")}</div>;

  return (
    <div className="max-w-2xl space-y-5">
      <h1 className="text-xl font-semibold">{t("settings.title")}</h1>

      <Card>
        <CardHeader><CardTitle>{t("settings.businessInfo")}</CardTitle></CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-4">
            <div><Label>{t("settings.businessName")} *</Label><Input {...register("name")} />{errors.name && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.name.message}</p>}</div>
            <div className="grid grid-cols-2 gap-3">
              <div><Label>{t("settings.email")}</Label><Input type="email" {...register("email")} /></div>
              <div><Label>{t("settings.phone")}</Label><Input {...register("phone")} /></div>
            </div>
            <div><Label>{t("settings.address")}</Label><Input {...register("address")} /></div>
            <div><Label>{t("settings.taxNumber")}</Label><Input {...register("tax_number")} /></div>
            <div className="grid grid-cols-3 gap-3">
              <div>
                <Label>{t("settings.currency")}</Label>
                <Select {...register("currency")}>
                  <option value="USD">USD</option>
                  <option value="EUR">EUR</option>
                  <option value="GBP">GBP</option>
                  <option value="BDT">BDT</option>
                  <option value="INR">INR</option>
                  <option value="SAR">SAR</option>
                </Select>
              </div>
              <div>
                <Label>{t("settings.timezone")}</Label>
                <Select {...register("timezone")}>
                  <option value="UTC">UTC</option>
                  <option value="America/New_York">America/New_York</option>
                  <option value="America/Los_Angeles">America/Los_Angeles</option>
                  <option value="Europe/London">Europe/London</option>
                  <option value="Asia/Dhaka">Asia/Dhaka</option>
                  <option value="Asia/Kolkata">Asia/Kolkata</option>
                  <option value="Asia/Riyadh">Asia/Riyadh</option>
                </Select>
              </div>
              <div>
                <Label>{t("settings.dateFormat")}</Label>
                <Select {...register("date_format")}>
                  <option value="Y-m-d">YYYY-MM-DD</option>
                  <option value="d/m/Y">DD/MM/YYYY</option>
                  <option value="m/d/Y">MM/DD/YYYY</option>
                </Select>
              </div>
            </div>
            <Button type="submit" disabled={mutation.isPending || !isDirty} className="w-full">{mutation.isPending ? t("settings.saving") : t("settings.save")}</Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>{t("settings.appearance")}</CardTitle></CardHeader>
        <CardContent>
          <p className="mb-3 text-sm text-[var(--muted)]">{t("settings.appearanceHint")}</p>
          <PalettePicker />
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>{t("settings.account")}</CardTitle></CardHeader>
        <CardContent className="space-y-2 text-sm">
          <div className="flex justify-between"><span className="text-[var(--muted)]">{t("settings.name")}</span><span>{user?.name}</span></div>
          <div className="flex justify-between"><span className="text-[var(--muted)]">{t("settings.email")}</span><span>{user?.email}</span></div>
          <div className="flex justify-between"><span className="text-[var(--muted)]">{t("settings.roles")}</span><span>{user?.roles?.join(", ") ?? "—"}</span></div>
        </CardContent>
      </Card>
    </div>
  );
}
