import { useState } from "react";
import { useNavigate, useSearchParams, Link } from "react-router";
import { api, ApiError } from "@/lib/api";
import { AuthShell } from "@/components/layout/auth-shell";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { useTranslation } from "@/i18n/context";

export default function ResetPasswordPage() {
  const t = useTranslation();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const token = searchParams.get("token");
  const email = searchParams.get("email");

  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);

  if (!token || !email) {
    return (
      <AuthShell title={t("auth.resetPasswordTitle")} subtitle={t("auth.resetPasswordSubtitle")}>
        <p className="text-sm text-[var(--color-danger)]">{t("auth.resetPasswordMissingLink")}</p>
        <p className="mt-6 text-center text-sm text-[var(--muted)]">
          <Link to="/forgot-password" className="font-medium text-[var(--primary)]">
            {t("auth.forgotPassword")}
          </Link>
        </p>
      </AuthShell>
    );
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      await api("/auth/reset-password", {
        method: "POST",
        body: { token, email, password, password_confirmation: passwordConfirmation },
      });
      setSuccess(true);
      setTimeout(() => navigate("/login", { replace: true }), 2000);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Reset failed");
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthShell title={t("auth.resetPasswordTitle")} subtitle={t("auth.resetPasswordSubtitle")}>
      {success ? (
        <p className="text-sm font-medium text-[var(--color-success)]">
          {t("auth.resetPasswordSuccess")}
        </p>
      ) : (
        <form onSubmit={onSubmit} className="space-y-4">
          <div>
            <Label>{t("auth.newPassword")}</Label>
            <Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required autoFocus />
          </div>
          <div>
            <Label>{t("auth.confirmPassword")}</Label>
            <Input
              type="password"
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              required
            />
          </div>
          {error && <p className="text-sm text-[var(--color-danger)]">{error}</p>}
          <Button type="submit" size="lg" className="w-full" disabled={loading}>
            {loading && <Spinner />} {loading ? t("auth.resettingPassword") : t("auth.resetPassword")}
          </Button>
        </form>
      )}
    </AuthShell>
  );
}
