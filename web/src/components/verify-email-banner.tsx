"use client";

import { useState, useRef } from "react";
import { useMutation } from "@tanstack/react-query";
import { MailWarning } from "lucide-react";
import { api, ApiError } from "@/lib/api";
import { useAuth } from "@/stores/auth";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Dialog } from "@/components/ui/dialog";
import { toast } from "@/components/ui/toast";
import { useTranslation } from "@/i18n/provider";

export function VerifyEmailBanner() {
  const t = useTranslation();
  const user = useAuth((s) => s.user);
  const [dismissed, setDismissed] = useState(false);
  const [showOtp, setShowOtp] = useState(false);
  const [code, setCode] = useState("");
  const [verifyError, setVerifyError] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const resend = useMutation({
    mutationFn: () => api("/auth/email/resend", { method: "POST" }),
    onSuccess: () => {
      toast(t("auth.verificationSent"));
      setShowOtp(true);
      setTimeout(() => inputRef.current?.focus(), 100);
    },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  const verify = useMutation({
    mutationFn: () =>
      api("/auth/email/verify", {
        method: "POST",
        body: { email: user?.email, code },
      }),
    onSuccess: () => {
      toast(t("auth.verificationSuccess") || "Email verified successfully");
      setShowOtp(false);
      setCode("");
      window.location.reload();
    },
    onError: (e: unknown) => {
      setVerifyError(e instanceof ApiError ? e.message : "Verification failed");
    },
  });

  if (!user || user.email_verified_at || dismissed) return null;

  return (
    <>
      <div className="flex items-center justify-between gap-3 border-b border-amber-500/30 bg-amber-500/10 px-4 py-2 text-sm text-amber-700 dark:text-amber-300 md:px-6">
        <div className="flex items-center gap-2">
          <MailWarning className="h-4 w-4 shrink-0" />
          <span>{t("auth.verifyBannerText")}</span>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="ghost" size="sm" onClick={() => setShowOtp(true)}>
            {t("auth.verifyEmail") || "Verify"}
          </Button>
          <Button variant="ghost" size="sm" onClick={() => resend.mutate()} disabled={resend.isPending}>
            {t("auth.resendVerification")}
          </Button>
          <button
            onClick={() => setDismissed(true)}
            className="text-xs text-amber-700/70 hover:text-amber-700 dark:text-amber-300/70 dark:hover:text-amber-300"
          >
            ×
          </button>
        </div>
      </div>

      <Dialog
        open={showOtp}
        onClose={() => { setShowOtp(false); setCode(""); setVerifyError(null); }}
        title={t("auth.verifyEmail") || "Verify your email"}
        description={`${t("auth.verificationSent") || "A verification code was sent to"} ${user.email}`}
      >
        <div className="space-y-4">
          <Input
            ref={inputRef}
            type="text"
            inputMode="numeric"
            maxLength={6}
            placeholder="000000"
            value={code}
            onChange={(e) => { setCode(e.target.value.replace(/\D/g, "")); setVerifyError(null); }}
            onKeyDown={(e) => { if (e.key === "Enter" && code.length === 6) verify.mutate(); }}
            className="text-center text-lg tracking-[0.5em]"
            autoFocus
          />
          {verifyError && <p className="text-sm text-red-500">{verifyError}</p>}
          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={() => { setShowOtp(false); setCode(""); setVerifyError(null); }}>
              {t("common.cancel")}
            </Button>
            <Button onClick={() => verify.mutate()} disabled={code.length !== 6 || verify.isPending}>
              {verify.isPending ? "…" : t("common.save") || "Verify"}
            </Button>
          </div>
          <button
            type="button"
            onClick={() => resend.mutate()}
            disabled={resend.isPending}
            className="text-xs text-[var(--muted)] hover:underline"
          >
            {t("auth.resendVerification")}
          </button>
        </div>
      </Dialog>
    </>
  );
}
