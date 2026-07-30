"use client";

import { useState } from "react";
import { useMutation } from "@tanstack/react-query";
import { MailWarning } from "lucide-react";
import { api, ApiError } from "@/lib/api";
import { useAuth } from "@/stores/auth";
import { Button } from "@/components/ui/button";
import { toast } from "@/components/ui/toast";
import { useTranslation } from "@/i18n/provider";

export function VerifyEmailBanner() {
  const t = useTranslation();
  const user = useAuth((s) => s.user);
  const [dismissed, setDismissed] = useState(false);

  const resend = useMutation({
    mutationFn: () => api("/auth/email/resend", { method: "POST" }),
    onSuccess: () => toast(t("auth.verificationSent")),
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  if (!user || user.email_verified_at || dismissed) return null;

  return (
    <div className="flex items-center justify-between gap-3 border-b border-amber-500/30 bg-amber-500/10 px-4 py-2 text-sm text-amber-700 dark:text-amber-300 md:px-6">
      <div className="flex items-center gap-2">
        <MailWarning className="h-4 w-4 shrink-0" />
        <span>{t("auth.verifyBannerText")}</span>
      </div>
      <div className="flex items-center gap-2">
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
  );
}
