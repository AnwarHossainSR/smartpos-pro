"use client";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { PrinterProfileList } from "@/features/printer/components/printer-profile-list";
import { useTranslation } from "@/i18n/provider";
import { useAuth } from "@/stores/auth";
import { ArrowLeft } from "lucide-react";
import Link from "next/link";

export default function PrinterSettingsPage() {
  const { can } = useAuth();
  const t = useTranslation();
  const canManage = can("settings.update");

  if (!canManage) {
    return (
      <div className="py-16 text-center text-[var(--muted)]">
        You don&apos;t have permission to view printer settings.
      </div>
    );
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-2">
        <Link href="/settings" className="text-[var(--muted)] hover:text-[var(--fg)]">
          <ArrowLeft className="h-4 w-4" />
        </Link>
      </div>

      <div className="grid grid-cols-1 gap-5 xl:grid-cols-4">
        <Card className="xl:col-span-3">
          <CardHeader>
            <CardTitle>{t("settings.printer.title")}</CardTitle>
          </CardHeader>
          <CardContent>
            <PrinterProfileList />
          </CardContent>
        </Card>

        <div className="space-y-5">
          <Card>
            <CardHeader>
              <CardTitle>{t("settings.printer.about")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm text-[var(--muted)]">
              <p>{t("settings.printer.aboutHint1")}</p>
              <p>{t("settings.printer.aboutHint2")}</p>
              <p>{t("settings.printer.aboutHint3")}</p>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
