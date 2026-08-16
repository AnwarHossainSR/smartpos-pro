"use client";

import { TwoFactorCard } from "@/components/two-factor-card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog } from "@/components/ui/dialog";
import { Input, Label } from "@/components/ui/input";
import { LocalePicker } from "@/components/ui/locale-picker";
import { PalettePicker } from "@/components/ui/palette-picker";
import { Select } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "@/components/ui/toast";
import { useTranslation } from "@/i18n/provider";
import { api, ApiError } from "@/lib/api";
import { formatDateTime } from "@/lib/utils";
import { useAuth } from "@/stores/auth";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronRight, Laptop, Percent, Smartphone } from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";

interface Session {
  id: number;
  name: string;
  ip_address: string | null;
  last_used_at: string | null;
  created_at: string;
  expires_at: string | null;
  current: boolean;
}

function SessionsCard() {
  const t = useTranslation();
  const qc = useQueryClient();
  const [revoking, setRevoking] = useState<Session | null>(null);
  const [revokingAll, setRevokingAll] = useState(false);

  const { data, isLoading } = useQuery<{ data: Session[] }>({
    queryKey: ["auth-sessions"],
    queryFn: () => api<{ data: Session[] }>("/auth/sessions"),
  });

  const revokeMutation = useMutation({
    mutationFn: (id: number) => api(`/auth/sessions/${id}`, { method: "DELETE" }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["auth-sessions"] });
      toast(t("settings.sessionRevoked"));
      setRevoking(null);
    },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  const revokeAllMutation = useMutation({
    mutationFn: () => api("/auth/sessions/revoke-all", { method: "POST" }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["auth-sessions"] });
      toast(t("settings.sessionsRevokedAll"));
      setRevokingAll(false);
    },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  const sessions = data?.data ?? [];
  const otherSessions = sessions.filter((s) => !s.current);

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-2">
        <CardTitle>{t("settings.security")}</CardTitle>
        {otherSessions.length > 0 && (
          <Button variant="outline" size="sm" onClick={() => setRevokingAll(true)}>
            {t("settings.sessionRevokeAll")}
          </Button>
        )}
      </CardHeader>
      <CardContent className="space-y-3">
        <p className="text-sm text-[var(--muted)]">{t("settings.securityHint")}</p>

        {isLoading ? (
          <div className="space-y-2">
            <Skeleton className="h-12 w-full" />
            <Skeleton className="h-12 w-full" />
          </div>
        ) : sessions.length === 0 ? (
          <p className="text-sm text-[var(--muted)]">{t("settings.sessionsEmpty")}</p>
        ) : (
          <ul className="divide-y divide-[var(--border)]">
            {sessions.map((s) => (
              <li key={s.id} className="flex items-center justify-between gap-3 py-2.5">
                <div className="flex items-center gap-3 min-w-0">
                  {/^(iphone|android|phone)/i.test(s.name) ? (
                    <Smartphone className="h-4 w-4 shrink-0 text-[var(--muted)]" />
                  ) : (
                    <Laptop className="h-4 w-4 shrink-0 text-[var(--muted)]" />
                  )}
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="truncate text-sm font-medium">{s.name}</span>
                      {s.current && <Badge tone="success">{t("settings.sessionCurrent")}</Badge>}
                    </div>
                    <p className="truncate text-xs text-[var(--muted)]">
                      {s.ip_address ?? "—"} · {t("settings.sessionLastActive")}{" "}
                      {s.last_used_at ? formatDateTime(s.last_used_at) : formatDateTime(s.created_at)}
                    </p>
                  </div>
                </div>
                {!s.current && (
                  <Button variant="ghost" size="sm" className="text-[var(--color-danger)]" onClick={() => setRevoking(s)}>
                    {t("settings.sessionRevoke")}
                  </Button>
                )}
              </li>
            ))}
          </ul>
        )}
      </CardContent>

      <Dialog
        open={!!revoking}
        onClose={() => setRevoking(null)}
        title={t("settings.sessionRevokeConfirmTitle")}
        description={t("settings.sessionRevokeConfirmBody")}
        confirmLabel={t("settings.sessionRevoke")}
        confirmVariant="danger"
        loading={revokeMutation.isPending}
        onConfirm={() => revoking && revokeMutation.mutate(revoking.id)}
      />

      <Dialog
        open={revokingAll}
        onClose={() => setRevokingAll(false)}
        title={t("settings.sessionRevokeAllConfirmTitle")}
        description={t("settings.sessionRevokeAllConfirmBody")}
        confirmLabel={t("settings.sessionRevokeAll")}
        confirmVariant="danger"
        loading={revokeAllMutation.isPending}
        onConfirm={() => revokeAllMutation.mutate()}
      />
    </Card>
  );
}

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
    resolver: zodResolver(schema),
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
    <div className="max-w-5xl space-y-5">

      <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader><CardTitle>{t("settings.businessInfo")}</CardTitle></CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-4">
              <div><Label>{t("settings.businessName")} *</Label><Input {...register("name")} />{errors.name && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.name.message}</p>}</div>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div><Label>{t("settings.email")}</Label><Input type="email" {...register("email")} /></div>
                <div><Label>{t("settings.phone")}</Label><Input {...register("phone")} /></div>
              </div>
              <div><Label>{t("settings.address")}</Label><Input {...register("address")} /></div>
              <div><Label>{t("settings.taxNumber")}</Label><Input {...register("tax_number")} /></div>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
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
              <Button type="submit" disabled={mutation.isPending || !isDirty} className="w-full sm:w-auto">{mutation.isPending ? t("settings.saving") : t("settings.save")}</Button>
            </form>
          </CardContent>
        </Card>

        <div className="space-y-5">
          <Card>
            <CardContent className="p-0">
              <Link
                href="/settings/tax"
                className="flex items-center gap-3 p-5 transition-colors hover:bg-[var(--surface-2)]"
              >
                <Percent className="h-5 w-5 shrink-0 text-[var(--primary)]" />
                <div className="min-w-0 flex-1">
                  <p className="font-medium">Tax</p>
                  <p className="text-xs text-[var(--muted)]">Rates, groups, jurisdictions & price display</p>
                </div>
                <ChevronRight className="h-4 w-4 shrink-0 text-[var(--muted)] rtl:scale-x-[-1]" />
              </Link>
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle>{t("settings.appearance")}</CardTitle></CardHeader>
            <CardContent>
              <p className="mb-3 text-sm text-[var(--muted)]">{t("settings.appearanceHint")}</p>
              <PalettePicker compact />
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle>{t("settings.localization")}</CardTitle></CardHeader>
            <CardContent>
              <p className="mb-3 text-sm text-[var(--muted)]">{t("settings.localizationHint")}</p>
              <LocalePicker />
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle>{t("settings.account")}</CardTitle></CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div className="flex justify-between gap-3"><span className="text-[var(--muted)]">{t("settings.name")}</span><span className="truncate text-right">{user?.name}</span></div>
              <div className="flex justify-between gap-3"><span className="text-[var(--muted)]">{t("settings.email")}</span><span className="truncate text-right">{user?.email}</span></div>
              <div className="flex justify-between gap-3"><span className="text-[var(--muted)]">{t("settings.roles")}</span><span className="truncate text-right">{user?.roles?.join(", ") ?? "—"}</span></div>
            </CardContent>
          </Card>
        </div>
      </div>

      <TwoFactorCard />
      <SessionsCard />
    </div>
  );
}
