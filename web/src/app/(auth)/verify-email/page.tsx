"use client";

import { Suspense, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import Link from "next/link";
import { api, ApiError } from "@/lib/api";
import { AuthShell } from "@/components/layout/auth-shell";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";

function VerifyEmailForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const email = searchParams.get("email") ?? "";
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [loading, setLoading] = useState(false);
  const [resending, setResending] = useState(false);
  const [resendMessage, setResendMessage] = useState<string | null>(null);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      await api<{ message: string }>("/auth/email/verify", {
        method: "POST",
        body: { email, code },
      });
      setSuccess(true);
      setTimeout(() => router.replace("/login?verified=1"), 2000);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Verification failed");
    } finally {
      setLoading(false);
    }
  }

  async function onResend() {
    setResending(true);
    setResendMessage(null);
    setError(null);
    try {
      const token = document.cookie
        .split("; ")
        .find((c) => c.startsWith("smartpos_token="))
        ?.split("=")[1];
      if (token) {
        await api<{ message: string }>("/auth/email/resend", {
          method: "POST",
          headers: { Authorization: `Bearer ${token}` },
        });
        setResendMessage("A new verification code has been sent to your email.");
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to resend code");
    } finally {
      setResending(false);
    }
  }

  if (success) {
    return (
      <AuthShell title="Email verified" subtitle="Redirecting you to sign in...">
        <div className="text-center">
          <p className="text-sm text-[var(--color-success)]">
            Your email has been verified successfully.
          </p>
        </div>
      </AuthShell>
    );
  }

  return (
    <AuthShell title="Verify your email" subtitle={`Enter the 6-digit code sent to ${email || "your email"}`}>
      <form onSubmit={onSubmit} className="space-y-4">
        <div>
          <Label>Verification code</Label>
          <Input
            value={code}
            onChange={(e) => setCode(e.target.value)}
            inputMode="numeric"
            maxLength={6}
            placeholder="000000"
            autoFocus
            required
          />
        </div>
        {error && <p className="text-sm text-[var(--color-danger)]">{error}</p>}
        {resendMessage && <p className="text-sm text-[var(--color-success)]">{resendMessage}</p>}
        <Button type="submit" size="lg" className="w-full" disabled={loading}>
          {loading && <Spinner />} {loading ? "Verifying..." : "Verify email"}
        </Button>
      </form>
      <div className="mt-4 text-center text-sm text-[var(--muted)]">
        <p>
          Didn&apos;t receive the code?{" "}
          <button
            type="button"
            onClick={onResend}
            disabled={resending}
            className="font-medium text-[var(--primary)] hover:underline"
          >
            {resending ? "Sending..." : "Resend code"}
          </button>
        </p>
      </div>
      <p className="mt-4 text-center text-sm text-[var(--muted)]">
        <Link href="/login" className="font-medium text-[var(--primary)]">
          Back to sign in
        </Link>
      </p>
    </AuthShell>
  );
}

export default function VerifyEmailPage() {
  return (
    <Suspense fallback={
      <div className="flex min-h-screen items-center justify-center">
        <Spinner />
      </div>
    }>
      <VerifyEmailForm />
    </Suspense>
  );
}
