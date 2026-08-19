"use client";

import { AuthShell } from "@/components/layout/auth-shell";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { useTranslation } from "@/i18n/provider";
import { api, ApiError } from "@/lib/api";
import { useAuth, type AuthUser } from "@/stores/auth";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

interface LoginResult {
  requires_2fa?: boolean;
  challenge_token?: string;
  token?: string;
  user?: AuthUser;
}

export default function LoginPage() {
  const router = useRouter();
  const t = useTranslation();
  const setAuth = useAuth((s) => s.setAuth);
  const hydrated = useAuth((s) => s.hydrated);
  const token = useAuth((s) => s.token);
  const [email, setEmail] = useState("admin@demostore.com");
  const [password, setPassword] = useState("password123");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const [challengeToken, setChallengeToken] = useState<string | null>(null);
  const [code, setCode] = useState("");
  const [useRecoveryCode, setUseRecoveryCode] = useState(false);

  // Someone who still has a valid session should not see the form again.
  useEffect(() => {
    if (hydrated && token) router.replace("/dashboard");
  }, [hydrated, token, router]);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const res = await api<LoginResult>("/auth/login", {
        method: "POST",
        body: { email, password, device_name: "web" },
      });
      if (res.requires_2fa && res.challenge_token) {
        setChallengeToken(res.challenge_token);
      } else if (res.token && res.user) {
        setAuth(res.token, res.user);
        if (res.user.email_verified_at) {
          router.replace("/dashboard");
        } else {
          router.replace(`/verify-email?email=${encodeURIComponent(res.user.email)}`);
        }
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Login failed");
    } finally {
      setLoading(false);
    }
  }

  async function onChallengeSubmit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const res = await api<{ token: string; user: AuthUser }>("/auth/2fa/challenge", {
        method: "POST",
        body: useRecoveryCode
          ? { challenge_token: challengeToken, recovery_code: code }
          : { challenge_token: challengeToken, code },
      });
      setAuth(res.token, res.user);
      router.replace("/dashboard");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Invalid code");
    } finally {
      setLoading(false);
    }
  }

  if (challengeToken) {
    return (
      <AuthShell title="Two-factor authentication" subtitle="Enter the code from your authenticator app">
        <form onSubmit={onChallengeSubmit} className="space-y-4">
          <div>
            <Label>{useRecoveryCode ? "Recovery code" : "6-digit code"}</Label>
            <Input
              value={code}
              onChange={(e) => setCode(e.target.value)}
              inputMode={useRecoveryCode ? "text" : "numeric"}
              maxLength={useRecoveryCode ? 9 : 6}
              autoFocus
              required
            />
          </div>
          {error && <p className="text-sm text-[var(--color-danger)]">{error}</p>}
          <Button type="submit" size="lg" className="w-full" disabled={loading}>
            {loading && <Spinner />} {loading ? t("auth.signingIn") : t("auth.signIn")}
          </Button>
          <button
            type="button"
            onClick={() => { setUseRecoveryCode((v) => !v); setCode(""); setError(null); }}
            className="w-full text-center text-xs font-medium text-[var(--primary)]"
          >
            {useRecoveryCode ? "Use an authenticator code instead" : "Use a recovery code instead"}
          </button>
        </form>
      </AuthShell>
    );
  }

  return (
    <AuthShell title={t("auth.loginTitle")} subtitle={t("auth.loginSubtitle")}>
      <form onSubmit={onSubmit} className="space-y-4">
        <div>
          <Label>{t("auth.email")}</Label>
          <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
        </div>
        <div>
          <div className="flex items-center justify-between">
            <Label>{t("auth.password")}</Label>
            <Link href="/forgot-password" className="text-xs font-medium text-[var(--primary)]">
              {t("auth.forgotPassword")}
            </Link>
          </div>
          <Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
        </div>
        {error && <p className="text-sm text-[var(--color-danger)]">{error}</p>}
        <Button type="submit" size="lg" className="w-full" disabled={loading}>
          {loading && <Spinner />} {loading ? t("auth.signingIn") : t("auth.signIn")}
        </Button>
      </form>
      <p className="mt-6 text-center text-sm text-[var(--muted)]">
        {t("auth.newBusiness")}{" "}
        <Link href="/register" className="font-medium text-[var(--primary)]">
          {t("auth.createAnAccount")}
        </Link>
      </p>
    </AuthShell>
  );
}
