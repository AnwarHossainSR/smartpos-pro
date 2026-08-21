import { ApprovalPinCard } from "@/components/approval-pin-card";
import { useTranslation } from "@/i18n/context";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Dialog } from "@/components/ui/dialog";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "@/components/ui/toast";
import { api, errorMessage } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { useAuth } from "@/stores/auth";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, ShieldCheck, ShieldOff, Lock, KeyRound, Laptop, Smartphone, AlertTriangle } from "lucide-react";
import { Link } from "react-router";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { formatDateTime } from "@/lib/utils";

interface Session {
  id: string;
  name: string;
  ip_address: string | null;
  last_used_at: string | null;
  created_at: string;
  expires_at: string | null;
  current: boolean;
}

interface TwoFactorStatus {
  enabled: boolean;
  recovery_codes_remaining: number;
}

interface LockoutStatus {
  is_locked: boolean;
  locked_until: string | null;
  failed_attempts: number;
}

const passwordSchema = z.object({
  current_password: z.string().min(1),
  new_password: z.string().min(8),
  new_password_confirmation: z.string().min(1),
}).refine((d) => d.new_password === d.new_password_confirmation, {
  message: "Passwords don't match",
  path: ["new_password_confirmation"],
});
type PasswordForm = z.infer<typeof passwordSchema>;

function PasswordSection() {
  const t = useTranslation();
  const { register, handleSubmit, formState: { errors }, reset } = useForm<PasswordForm>({
    resolver: zodResolver(passwordSchema),
  });

  const mutation = useMutation({
    mutationFn: (d: PasswordForm) => api("/auth/change-password", { method: "POST", body: d }),
    onSuccess: () => { toast(t("security.password.success")); reset(); },
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <KeyRound className="h-4 w-4 text-[var(--primary)]" />
          {t("security.password.title")}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-4 max-w-md">
          <div>
            <Label>{t("security.password.current")}</Label>
            <Input type="password" autoComplete="current-password" {...register("current_password")} />
            {errors.current_password && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.current_password.message}</p>}
          </div>
          <div>
            <Label>{t("security.password.new")}</Label>
            <Input type="password" autoComplete="new-password" {...register("new_password")} />
            {errors.new_password && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.new_password.message}</p>}
          </div>
          <div>
            <Label>{t("security.password.confirm")}</Label>
            <Input type="password" autoComplete="new-password" {...register("new_password_confirmation")} />
            {errors.new_password_confirmation && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.new_password_confirmation.message}</p>}
          </div>
          <Button type="submit" disabled={mutation.isPending}>
            {mutation.isPending ? t("common.saving") : t("security.password.submit")}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}

function TwoFactorSection() {
  const t = useTranslation();
  const user = useAuth((s) => s.user);
  const qc = useQueryClient();
  const enabled = !!user?.two_factor_enabled;

  const [enrolling, setEnrolling] = useState(false);
  const [secret, setSecret] = useState<string | null>(null);
  const [qrDataUrl, setQrDataUrl] = useState<string | null>(null);
  const [code, setCode] = useState("");
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
  const [disabling, setDisabling] = useState(false);
  const [password, setPassword] = useState("");
  const [regenerating, setRegenerating] = useState(false);

  const { data: statusData } = useQuery<{ data: TwoFactorStatus }>({
    queryKey: queryKeys.twoFactorStatus,
    queryFn: () => api<{ data: TwoFactorStatus }>("/auth/2fa/status"),
  });

  const status = statusData?.data;

  /**
   * `two_factor_enabled` comes off the profile this section reads from the auth store,
   * so refetching AppShell's `me` query is the whole update — its `setUser` effect
   * writes the result into the store. Calling `/auth/me` here *and* invalidating the
   * query fetched the same profile twice per toggle.
   */
  const refetchUser = () => qc.refetchQueries({ queryKey: queryKeys.me });

  const enableMutation = useMutation({
    mutationFn: () => api<{ data: { secret: string; otpauth_url: string } }>("/auth/2fa/enable", { method: "POST" }),
    onSuccess: (res) => {
      setSecret(res.data.secret);
      setEnrolling(true);
      import("qrcode").then((QRCode) => {
        QRCode.default.toDataURL(res.data.otpauth_url, { width: 200 }).then(setQrDataUrl).catch(() => setQrDataUrl(null));
      });
    },
  });

  const confirmMutation = useMutation({
    mutationFn: () => api<{ data: { recovery_codes: string[] } }>("/auth/2fa/confirm", { method: "POST", body: { code } }),
    onSuccess: async (res) => {
      setRecoveryCodes(res.data.recovery_codes);
      setEnrolling(false);
      setCode("");
      await refetchUser();
      qc.invalidateQueries({ queryKey: queryKeys.twoFactorStatus });
      toast(t("security.2fa.enabled"));
    },
    // "Invalid code" is the useful fallback here; the generic one is not.
    onError: (e: unknown) => toast(errorMessage(e, "Invalid code"), "error"),
  });

  const disableMutation = useMutation({
    mutationFn: () => api("/auth/2fa/disable", { method: "POST", body: { password } }),
    onSuccess: async () => {
      setDisabling(false);
      setPassword("");
      await refetchUser();
      qc.invalidateQueries({ queryKey: queryKeys.twoFactorStatus });
      toast(t("security.2fa.disabled"));
    },
  });

  const regenerateMutation = useMutation({
    mutationFn: () => api<{ data: { recovery_codes: string[] } }>("/auth/2fa/recovery-codes/regenerate", { method: "POST", body: { password } }),
    onSuccess: (res) => {
      setRecoveryCodes(res.data.recovery_codes);
      setRegenerating(false);
      setPassword("");
    },
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          {enabled ? <ShieldCheck className="h-4 w-4 text-[var(--color-success)]" /> : <ShieldOff className="h-4 w-4 text-[var(--muted)]" />}
          {t("security.2fa.title")}
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <Badge tone={enabled ? "success" : "default"}>
              {enabled ? t("security.2fa.enable") : t("security.2fa.disable")}
            </Badge>
            {status && (
              <p className="mt-1 text-xs text-[var(--muted)]">
                {t("security.2fa.recovery")}: {status.recovery_codes_remaining}
              </p>
            )}
          </div>
          {enabled ? (
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={() => setRegenerating(true)}>
                {t("security.2fa.recovery")}
              </Button>
              <Button variant="outline" size="sm" className="text-[var(--color-danger)]" onClick={() => setDisabling(true)}>
                {t("security.2fa.disable")}
              </Button>
            </div>
          ) : (
            <Button size="sm" onClick={() => enableMutation.mutate()} disabled={enableMutation.isPending}>
              {t("security.2fa.enable")}
            </Button>
          )}
        </div>

        <Dialog
          open={enrolling}
          onClose={() => setEnrolling(false)}
          title={t("security.2fa.qr")}
          description="Scan with Google Authenticator, Authy, or any TOTP app, then enter the 6-digit code it shows."
          confirmLabel="Confirm"
          loading={confirmMutation.isPending}
          onConfirm={() => confirmMutation.mutate()}
        >
          <div className="space-y-3">
            {qrDataUrl && (
              <img src={qrDataUrl} alt="Two-factor QR code" className="mx-auto h-48 w-48" />
            )}
            {secret && (
              <p className="break-all rounded-[var(--radius)] bg-[var(--surface-2)] p-2 text-center font-mono text-xs">
                {secret}
              </p>
            )}
            <div>
              <Label>6-digit code</Label>
              <Input value={code} onChange={(e) => setCode(e.target.value)} inputMode="numeric" maxLength={6} autoFocus />
            </div>
          </div>
        </Dialog>

        <Dialog
          open={disabling}
          onClose={() => { setDisabling(false); setPassword(""); }}
          title={t("security.2fa.disable")}
          description="Enter your password to confirm."
          confirmLabel={t("security.2fa.disable")}
          confirmVariant="danger"
          loading={disableMutation.isPending}
          onConfirm={() => disableMutation.mutate()}
        >
          <div>
            <Label>{t("security.password.current")}</Label>
            <Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoFocus />
          </div>
        </Dialog>

        <Dialog
          open={regenerating}
          onClose={() => { setRegenerating(false); setPassword(""); }}
          title={t("security.2fa.recovery")}
          description={t("security.2fa.recovery_warning")}
          confirmLabel={t("security.2fa.recovery")}
          confirmVariant="danger"
          loading={regenerateMutation.isPending}
          onConfirm={() => regenerateMutation.mutate()}
        >
          <div>
            <Label>{t("security.password.current")}</Label>
            <Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoFocus />
          </div>
        </Dialog>

        <Dialog
          open={!!recoveryCodes}
          onClose={() => setRecoveryCodes(null)}
          title={t("security.2fa.recovery")}
          description={t("security.2fa.recovery_warning")}
        >
          <div className="grid grid-cols-2 gap-2 font-mono text-sm">
            {recoveryCodes?.map((c) => (
              <div key={c} className="rounded-[var(--radius)] bg-[var(--surface-2)] p-2 text-center">
                {c}
              </div>
            ))}
          </div>
          <Button className="mt-4 w-full" onClick={() => setRecoveryCodes(null)}>
            {t("common.close")}
          </Button>
        </Dialog>
      </CardContent>
    </Card>
  );
}

function SessionsSection() {
  const t = useTranslation();
  const qc = useQueryClient();
  const [revoking, setRevoking] = useState<Session | null>(null);
  const [revokingAll, setRevokingAll] = useState(false);

  const { data, isLoading } = useQuery<{ data: Session[] }>({
    queryKey: queryKeys.authSessions,
    queryFn: () => api<{ data: Session[] }>("/auth/sessions"),
  });

  const revokeMutation = useMutation({
    mutationFn: (id: string) => api(`/auth/sessions/${id}`, { method: "DELETE" }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: queryKeys.authSessions }); toast(t("security.sessions.revoked")); setRevoking(null); },
    // Keeps the confirm dialog from sticking open on a failed revoke.
    onError: (e: unknown) => { setRevoking(null); toast(errorMessage(e), "error"); },
  });

  const revokeAllMutation = useMutation({
    mutationFn: () => api("/auth/sessions/revoke-all", { method: "POST" }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: queryKeys.authSessions }); toast(t("security.sessions.revoked_all")); setRevokingAll(false); },
    onError: (e: unknown) => { setRevokingAll(false); toast(errorMessage(e), "error"); },
  });

  const sessions = data?.data ?? [];
  const otherSessions = sessions.filter((s) => !s.current);

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-2">
        <CardTitle>{t("security.sessions.title")}</CardTitle>
        {otherSessions.length > 0 && (
          <Button variant="outline" size="sm" onClick={() => setRevokingAll(true)}>
            {t("security.sessions.revoke_all")}
          </Button>
        )}
      </CardHeader>
      <CardContent className="space-y-3">
        {isLoading ? (
          <div className="space-y-2">
            <Skeleton className="h-12 w-full" />
            <Skeleton className="h-12 w-full" />
          </div>
        ) : sessions.length === 0 ? (
          <p className="text-sm text-[var(--muted)]">{t("security.sessions.empty")}</p>
        ) : (
          <ul className="divide-y divide-[var(--border)]">
            {sessions.map((s) => (
              <li key={s.id} className="flex items-center justify-between gap-3 py-2.5">
                <div className="flex items-center gap-3 min-w-0">
                  {/^(iphone|android|phone)/i.test(s.name) ? (
                    <Smartphone className="h-4 w-4 shrink-0 text-[var(--muted)]" />
                  ) : (
                    <Laptop className="h-4 w-4 shrink-0 text-[var(--muted)]" />
                  )}
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="truncate text-sm font-medium">{s.name}</span>
                      {s.current && <Badge tone="success">{t("security.sessions.current")}</Badge>}
                    </div>
                    <p className="truncate text-xs text-[var(--muted)]">
                      {s.ip_address ?? "—"} · {t("security.sessions.last_used")}{" "}
                      {s.last_used_at ? formatDateTime(s.last_used_at) : formatDateTime(s.created_at)}
                    </p>
                  </div>
                </div>
                {!s.current && (
                  <Button variant="ghost" size="sm" className="text-[var(--color-danger)]" onClick={() => setRevoking(s)}>
                    {t("security.sessions.revoke")}
                  </Button>
                )}
              </li>
            ))}
          </ul>
        )}
      </CardContent>

      <Dialog
        open={!!revoking}
        onClose={() => setRevoking(null)}
        title={t("security.sessions.revoke")}
        description={t("security.sessions.revoke_confirm")}
        confirmLabel={t("security.sessions.revoke")}
        confirmVariant="danger"
        loading={revokeMutation.isPending}
        onConfirm={() => revoking && revokeMutation.mutate(revoking.id)}
      />

      <Dialog
        open={revokingAll}
        onClose={() => setRevokingAll(false)}
        title={t("security.sessions.revoke_all")}
        description={t("security.sessions.revoke_all_confirm")}
        confirmLabel={t("security.sessions.revoke_all")}
        confirmVariant="danger"
        loading={revokeAllMutation.isPending}
        onConfirm={() => revokeAllMutation.mutate()}
      />
    </Card>
  );
}

function LockoutSection() {
  const t = useTranslation();

  const { data, isLoading } = useQuery<{ data: LockoutStatus }>({
    queryKey: queryKeys.lockoutStatus,
    queryFn: () => api<{ data: LockoutStatus }>("/auth/lockout-status"),
  });

  const status = data?.data;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Lock className="h-4 w-4 text-[var(--primary)]" />
          {t("security.lockout.title")}
        </CardTitle>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <Skeleton className="h-8 w-48" />
        ) : (
          <div className="space-y-2">
            <div className="flex items-center gap-2">
              {status?.is_locked ? (
                <Badge tone="danger">{t("security.lockout.locked")}</Badge>
              ) : (
                <Badge tone="success">{t("common.active")}</Badge>
              )}
            </div>
            <p className="text-sm text-[var(--muted)]">
              {t("security.lockout.attempts")}: {status?.failed_attempts ?? 0}
            </p>
            {status?.is_locked && status.locked_until && (
              <p className="text-sm text-[var(--muted)]">
                <AlertTriangle className="inline h-3 w-3 mr-1" />
                Locked until {formatDateTime(status.locked_until)}
              </p>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

export default function SecurityPage() {
  const t = useTranslation();

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-2">
        <Link to="/settings" className="text-[var(--muted)] hover:text-[var(--fg)]">
          <ArrowLeft className="h-4 w-4" />
        </Link>
        <h1 className="text-lg font-semibold">{t("security.title")}</h1>
      </div>

      <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
        <PasswordSection />
        <TwoFactorSection />
      </div>

      <SessionsSection />
      <LockoutSection />
      <ApprovalPinCard />
    </div>
  );
}
