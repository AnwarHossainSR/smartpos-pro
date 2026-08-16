"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { api, ApiError } from "@/lib/api";
import { useAuth, type AuthUser } from "@/stores/auth";
import { AuthShell } from "@/components/layout/auth-shell";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { useTranslation } from "@/i18n/provider";

export default function RegisterPage() {
  const router = useRouter();
  const t = useTranslation();
  const setAuth = useAuth((s) => s.setAuth);
  const [form, setForm] = useState({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    business_name: "",
  });
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const res = await api<{ token: string; user: AuthUser }>("/auth/register", {
        method: "POST",
        body: form,
      });
      setAuth(res.token, res.user);
      if (res.user.email_verified_at) {
        router.replace("/dashboard");
      } else {
        router.replace(`/verify-email?email=${encodeURIComponent(res.user.email)}`);
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Registration failed");
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthShell title={t("auth.registerTitle")} subtitle={t("auth.registerSubtitle")}>
      <form onSubmit={onSubmit} className="space-y-4">
        <div>
          <Label>{t("auth.businessName")}</Label>
          <Input value={form.business_name} onChange={set("business_name")} required />
        </div>
        <div>
          <Label>{t("auth.yourName")}</Label>
          <Input value={form.name} onChange={set("name")} required />
        </div>
        <div>
          <Label>{t("auth.email")}</Label>
          <Input type="email" value={form.email} onChange={set("email")} required />
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <Label>{t("auth.password")}</Label>
            <Input type="password" value={form.password} onChange={set("password")} required />
          </div>
          <div>
            <Label>{t("auth.confirmPassword")}</Label>
            <Input type="password" value={form.password_confirmation} onChange={set("password_confirmation")} required />
          </div>
        </div>
        {error && <p className="text-sm text-[var(--color-danger)]">{error}</p>}
        <Button type="submit" size="lg" className="w-full" disabled={loading}>
          {loading && <Spinner />} {loading ? t("auth.creatingAccount") : t("auth.createAccount")}
        </Button>
      </form>
      <p className="mt-6 text-center text-sm text-[var(--muted)]">
        {t("auth.alreadyHaveAccount")}{" "}
        <Link href="/login" className="font-medium text-[var(--primary)]">
          {t("auth.signIn")}
        </Link>
      </p>
    </AuthShell>
  );
}
