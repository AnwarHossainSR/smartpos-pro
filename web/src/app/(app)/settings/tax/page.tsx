import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { TaxGroupsSection } from "@/features/tax/components/tax-groups-section";
import { TaxProfileCard } from "@/features/tax/components/tax-profile-card";
import { TaxRatesSection } from "@/features/tax/components/tax-rates-section";
import { TaxZonesSection } from "@/features/tax/components/tax-zones-section";
import { cn } from "@/lib/utils";
import { useAuth } from "@/stores/auth";
import { ArrowLeft } from "lucide-react";
import { Link } from "react-router";
import { useState } from "react";

type Tab = "rates" | "groups" | "zones";

const TABS: { id: Tab; label: string; hint: string }[] = [
  { id: "rates", label: "Tax rates", hint: "Single components — CGST, SGST, VAT…" },
  { id: "groups", label: "Tax groups", hint: "Ordered sets of components attached to products" },
  { id: "zones", label: "Tax zones", hint: "Which group applies in which jurisdiction" },
];

function TaxSettingsBody() {
  const { can } = useAuth();
  const [tab, setTab] = useState<Tab>("rates");
  const canView = can("taxes.view");
  const canManage = can("taxes.manage");

  if (!canView) {
    return (
      <div className="py-16 text-center text-[var(--muted)]">
        You don&apos;t have permission to view tax settings.
      </div>
    );
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-2">
        <Link to="/settings" className="text-[var(--muted)] hover:text-[var(--fg)]">
          <ArrowLeft className="h-4 w-4" />
        </Link>
      </div>

      <div className="grid grid-cols-1 gap-5 xl:grid-cols-4">
        <Card className="xl:col-span-3">
          <CardHeader>
            <CardTitle>Rates, groups & zones</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="mb-4 flex flex-wrap gap-1 border-b border-[var(--border)]">
              {TABS.map((t) => (
                <button
                  key={t.id}
                  onClick={() => setTab(t.id)}
                  className={cn(
                    "cursor-pointer border-b-2 px-3 py-2 text-sm font-medium transition-colors",
                    tab === t.id
                      ? "border-[var(--primary)] text-[var(--primary)]"
                      : "border-transparent text-[var(--muted)] hover:text-[var(--fg)]",
                  )}
                >
                  {t.label}
                </button>
              ))}
            </div>
            <p className="-mt-2 mb-4 text-xs text-[var(--muted)]">{TABS.find((t) => t.id === tab)?.hint}</p>

            {tab === "rates" && <TaxRatesSection canManage={canManage} />}
            {tab === "groups" && <TaxGroupsSection canManage={canManage} />}
            {tab === "zones" && <TaxZonesSection canManage={canManage} />}
          </CardContent>
        </Card>

        <div className="space-y-5">
          <Card>
            <CardHeader><CardTitle>Jurisdiction & display</CardTitle></CardHeader>
            <CardContent>
              <TaxProfileCard canManage={canManage} />
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle>How this fits together</CardTitle></CardHeader>
            <CardContent className="space-y-2 text-sm text-[var(--muted)]">
              <p>1. Create <strong className="text-[var(--fg)]">tax rates</strong> — single components like CGST 9% or VAT 15%.</p>
              <p>2. Combine rates into a <strong className="text-[var(--fg)]">tax group</strong>, ordered so compound components apply last.</p>
              <p>3. Assign a group to each product, or map a <strong className="text-[var(--fg)]">tax zone</strong> so the right group applies automatically by jurisdiction.</p>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}

export default function TaxSettingsPage() {
  return <TaxSettingsBody />;
}
