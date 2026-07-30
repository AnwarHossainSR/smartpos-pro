"use client";

import { useState } from "react";
import Link from "next/link";
import { api, ApiError } from "@/lib/api";
import { AuthShell } from "@/components/layout/auth-shell";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { useTranslation } from "@/i18n/provider";

export default function ForgotPasswordPage() {
  const t = useTranslation();
  const [email, setEmail] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [sent, setSent] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      await api("/auth/forgot-password", { method: "POST", body: { email } });
      setSent(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Something went wrong");
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthShell title={t("auth.forgotPasswordTitle")} subtitle={t("auth.forgotPasswordSubtitle")}>
      {sent ? (
        <div className="space-y-4 text-center">
          <p className="text-sm font-medium">{t("auth.resetLinkSentTitle")}</p>
          <p className="text-sm text-[var(--muted)]">{t("auth.resetLinkSentBody")}</p>
        </div>
      ) : (
        <form onSubmit={onSubmit} className="space-y-4">
          <div>
            <Label>{t("auth.email")}</Label>
            <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoFocus />
          </div>
          {error && <p className="text-sm text-[var(--color-danger)]">{error}</p>}
          <Button type="submit" size="lg" className="w-full" disabled={loading}>
            {loading && <Spinner />} {loading ? t("auth.sendingResetLink") : t("auth.sendResetLink")}
          </Button>
        </form>
      )}
      <p className="mt-6 text-center text-sm text-[var(--muted)]">
        <Link href="/login" className="font-medium text-[var(--primary)]">
          {t("auth.backToLogin")}
        </Link>
      </p>
    </AuthShell>
  );
}
