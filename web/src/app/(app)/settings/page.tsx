import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { LucideIcon } from "lucide-react";
import {
  Building2,
  ChevronRight,
  Mail,
  Percent,
  Printer,
  Shield,
  ShieldCheck,
  Webhook,
} from "lucide-react";
import { useForm } from "react-hook-form";
import { Link } from "react-router";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input, Label } from "@/components/ui/input";
import { PalettePicker } from "@/components/ui/palette-picker";
import { Select } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "@/components/ui/toast";
import { BrandingCard } from "@/features/settings/branding-card";
import { LOCALE_LABELS, LOCALES, type Locale } from "@/i18n/config";
import { useTranslation } from "@/i18n/context";
import { api, type Envelope } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { cn } from "@/lib/utils";
import { useAuth } from "@/stores/auth";
import { useLocaleStore } from "@/stores/locale";

function Section({
  title,
  description,
  className,
  children,
}: {
  title: string;
  description?: string;
  className?: string;
  children: React.ReactNode;
}) {
  return (
    <section className={cn("space-y-3", className)}>
      <div>
        <h2 className="text-base font-semibold tracking-tight">{title}</h2>
        {description && <p className="mt-0.5 text-sm text-[var(--muted)]">{description}</p>}
      </div>
      {children}
    </section>
  );
}

function SettingRow({
  label,
  hint,
  control,
  stacked = false,
}: {
  label: string;
  hint: string;
  control: React.ReactNode;
  stacked?: boolean;
}) {
  return (
    <div
      className={
        stacked ? "space-y-3 p-5" : "flex flex-wrap items-center justify-between gap-4 p-5"
      }
    >
      <div className="min-w-0 max-w-prose">
        <p className="text-sm font-medium">{label}</p>
        <p className="mt-0.5 text-xs leading-relaxed text-[var(--muted)]">{hint}</p>
      </div>
      <div className={stacked ? undefined : "w-full shrink-0 sm:w-56"}>{control}</div>
    </div>
  );
}

function SettingsLink({
  to,
  icon: Icon,
  title,
  description,
}: {
  to: string;
  icon: LucideIcon;
  title: string;
  description: string;
}) {
  return (
    <Link
      to={to}
      className="group flex items-start gap-3 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-4 shadow-sm transition-colors hover:border-[var(--primary)]/40 hover:bg-[var(--surface-2)]"
    >
      <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-[var(--radius)] bg-[var(--primary)]/10 text-[var(--primary)]">
        <Icon className="h-4 w-4" />
      </span>
      <span className="min-w-0 flex-1">
        <span className="block text-sm font-medium">{title}</span>
        <span className="mt-0.5 block text-xs leading-relaxed text-[var(--muted)]">
          {description}
        </span>
      </span>
      <ChevronRight className="mt-1 h-4 w-4 shrink-0 text-[var(--muted)] transition-transform group-hover:translate-x-0.5 rtl:scale-x-[-1]" />
    </Link>
  );
}

function Toggle({ checked }: { checked: boolean }) {
  return (
    <span
      className={`relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors ${
        checked ? "bg-[var(--primary)]" : "bg-[var(--border)]"
      }`}
    >
      <span
        className={`pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-lg transition-transform ${
          checked ? "translate-x-5" : "translate-x-0"
        }`}
      />
    </span>
  );
}

const CURRENCIES = [
  { code: "USD", name: "US Dollar" },
  { code: "EUR", name: "Euro" },
  { code: "GBP", name: "British Pound" },
  { code: "BDT", name: "Bangladeshi Taka" },
  { code: "JPY", name: "Japanese Yen" },
  { code: "SAR", name: "Saudi Riyal" },
  { code: "AED", name: "UAE Dirham" },
];

function PreferencesCard({
  autoPostJournals,
  baseCurrency,
}: {
  autoPostJournals: boolean;
  baseCurrency: string;
}) {
  const t = useTranslation();
  const qc = useQueryClient();
  const locale = useLocaleStore((s) => s.locale);
  const setLocale = useLocaleStore((s) => s.setLocale);

  const save = useMutation({
    mutationFn: (body: Record<string, unknown>) =>
      api("/settings/business", { method: "PUT", body }),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.businessSettings }),
  });

  return (
    <Card>
      <CardContent className="divide-y divide-[var(--border)] p-0">
        <button
          type="button"
          disabled={save.isPending}
          onClick={() =>
            save.mutate(
              { auto_post_journals: !autoPostJournals },
              { onSuccess: () => toast(t("settings.autoBookkeepingSaved")) },
            )
          }
          className="flex w-full flex-wrap items-center justify-between gap-4 p-5 text-start transition-colors hover:bg-[var(--surface-2)] disabled:opacity-60"
        >
          <span className="min-w-0 max-w-prose">
            <span className="block text-sm font-medium">{t("settings.autoBookkeeping")}</span>
            <span className="mt-0.5 block text-xs leading-relaxed text-[var(--muted)]">
              {t("settings.autoBookkeepingHint")}
            </span>
          </span>
          <Toggle checked={autoPostJournals} />
        </button>

        <SettingRow
          label={t("currency.base")}
          hint={t("currency.baseHint")}
          control={
            <Select
              aria-label={t("currency.base")}
              value={baseCurrency}
              disabled={save.isPending}
              onChange={(e) =>
                save.mutate(
                  { base_currency: e.target.value },
                  { onSuccess: () => toast(t("currency.saved")) },
                )
              }
            >
              {CURRENCIES.map((c) => (
                <option key={c.code} value={c.code}>
                  {c.code} — {c.name}
                </option>
              ))}
            </Select>
          }
        />

        <SettingRow
          label={t("settings.language")}
          hint={t("settings.localizationHint")}
          control={
            <Select
              aria-label={t("settings.language")}
              value={locale}
              onChange={(e) => setLocale(e.target.value as Locale)}
            >
              {LOCALES.map((code) => (
                <option key={code} value={code}>
                  {LOCALE_LABELS[code]}
                </option>
              ))}
            </Select>
          }
        />

        <SettingRow
          stacked
          label={t("settings.appearance")}
          hint={t("settings.appearanceHint")}
          control={<PalettePicker />}
        />
      </CardContent>
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
  auto_post_journals: boolean;
  tenant?: { base_currency?: string };
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

  // One key for the whole `/settings/business` document: this form, the preferences
  // card's toggles and <BrandingCard>'s media slots all read the same fetch.
  const { data, isLoading } = useQuery<Envelope<BusinessSettings>>({
    queryKey: queryKeys.businessSettings,
    queryFn: () => api<Envelope<BusinessSettings>>("/settings/business"),
  });

  const {
    register,
    handleSubmit,
    formState: { errors, isDirty },
  } = useForm<FormValues>({
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
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.businessSettings });
      toast(t("settings.saved"));
    },
  });

  if (isLoading) {
    return (
      <div className="space-y-8">
        <div className="space-y-3">
          <Skeleton className="h-5 w-48" />
          <Skeleton className="h-72 w-full rounded-[var(--radius)]" />
        </div>
        <div className="space-y-3">
          <Skeleton className="h-5 w-40" />
          <Skeleton className="h-48 w-full rounded-[var(--radius)]" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-8 pb-4">
      <Section
        title={t("settings.businessInfo")}
        description="Your legal details and the formats used on receipts, invoices and reports."
      >
        <Card>
          <form onSubmit={handleSubmit((d) => mutation.mutate(d))}>
            <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
              <div className="sm:col-span-2 xl:col-span-1">
                <Label>{t("settings.businessName")} *</Label>
                <Input {...register("name")} />
                {errors.name && (
                  <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.name.message}</p>
                )}
              </div>
              <div>
                <Label>{t("settings.email")}</Label>
                <Input type="email" {...register("email")} />
                {errors.email && (
                  <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.email.message}</p>
                )}
              </div>
              <div>
                <Label>{t("settings.phone")}</Label>
                <Input {...register("phone")} />
              </div>
              <div>
                <Label>{t("settings.address")}</Label>
                <Input {...register("address")} />
              </div>
              <div>
                <Label>{t("settings.taxNumber")}</Label>
                <Input {...register("tax_number")} />
              </div>

              <div className="border-t border-[var(--border)] pt-4 sm:col-span-2 xl:col-span-3">
                <p className="text-xs font-medium uppercase tracking-wide text-[var(--muted)]">
                  Regional formats
                </p>
              </div>
              <div className="grid grid-cols-1 gap-4 sm:col-span-2 sm:grid-cols-3 xl:col-span-3">
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
            </CardContent>

            <div className="flex flex-wrap items-center justify-end gap-3 border-t border-[var(--border)] px-5 py-3">
              {isDirty && <p className="text-xs text-[var(--muted)]">You have unsaved changes.</p>}
              <Button type="submit" disabled={mutation.isPending || !isDirty}>
                {mutation.isPending ? t("settings.saving") : t("settings.save")}
              </Button>
            </div>
          </form>
        </Card>
      </Section>

      {/* Sits outside the profile form on purpose: images upload on selection, so
          there is nothing for the form's submit button to save. */}
      <Section
        title="Branding"
        description="Images are resized and converted on upload, so you can drop in whatever the designer sent without preparing sizes yourself."
      >
        <BrandingCard />
      </Section>

      <Section title="Configuration" description="Settings with a page of their own.">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <SettingsLink
            to="/settings/tax"
            icon={Percent}
            title="Tax"
            description="Rates, groups, jurisdictions & price display"
          />
          <SettingsLink
            to="/settings/locations"
            icon={Building2}
            title={t("nav.locations")}
            description="Stores, outlets and stock locations"
          />
          <SettingsLink
            to="/settings/printer"
            icon={Printer}
            title={t("settings.printer.title")}
            description="ESC/POS receipt printers"
          />
          <SettingsLink
            to="/settings/approvals"
            icon={ShieldCheck}
            title="Manager approvals"
            description="Discount limits, price override & void PIN rules"
          />
          <SettingsLink
            to="/settings/security"
            icon={Shield}
            title={t("security.title")}
            description={`${t("security.sessions.title")}, ${t("security.2fa.title")}, ${t("security.password.title")}`}
          />
          <SettingsLink
            to="/settings/email-templates"
            icon={Mail}
            title={t("emailTemplates.title")}
            description={t("emailTemplates.settingsHint")}
          />
          <SettingsLink
            to="/settings/webhooks"
            icon={Webhook}
            title={t("webhooks.title")}
            description={t("webhooks.settingsHint")}
          />
        </div>
      </Section>

      <div className="grid grid-cols-1 gap-8 xl:grid-cols-3">
        <Section
          title="Preferences"
          description="Saved as soon as you change them."
          className="xl:col-span-2"
        >
          <PreferencesCard
            autoPostJournals={data?.data?.auto_post_journals ?? false}
            baseCurrency={data?.data?.tenant?.base_currency ?? data?.data?.currency ?? "USD"}
          />
        </Section>

        <Section title={t("settings.account")} description="The account you are signed in with.">
          <Card>
            <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-3 xl:grid-cols-1">
              <div className="min-w-0">
                <p className="text-xs text-[var(--muted)]">{t("settings.name")}</p>
                <p className="truncate text-sm font-medium">{user?.name ?? "—"}</p>
              </div>
              <div className="min-w-0">
                <p className="text-xs text-[var(--muted)]">{t("settings.email")}</p>
                <p className="truncate text-sm font-medium">{user?.email ?? "—"}</p>
              </div>
              <div className="min-w-0">
                <p className="text-xs text-[var(--muted)]">{t("settings.roles")}</p>
                <p className="truncate text-sm font-medium">{user?.roles?.join(", ") ?? "—"}</p>
              </div>
            </CardContent>
          </Card>
        </Section>
      </div>
    </div>
  );
}
