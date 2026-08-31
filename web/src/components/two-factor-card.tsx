import { useMutation, useQueryClient } from "@tanstack/react-query";
import { ShieldCheck, ShieldOff } from "lucide-react";
import QRCode from "qrcode";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog } from "@/components/ui/dialog";
import { Input, Label } from "@/components/ui/input";
import { toast } from "@/components/ui/toast";
import { api, errorMessage } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { exportRecoveryCodesAsCsv, exportRecoveryCodesAsPdf } from "@/lib/recovery-code-export";
import { useAuth } from "@/stores/auth";

export function TwoFactorCard() {
  const user = useAuth((s) => s.user);
  const qc = useQueryClient();
  const enabled = !!user?.two_factor_enabled;

  const [enrolling, setEnrolling] = useState(false);
  const [secret, setSecret] = useState<string | null>(null);
  const [otpauthUrl, setOtpauthUrl] = useState<string | null>(null);
  const [qrDataUrl, setQrDataUrl] = useState<string | null>(null);
  const [code, setCode] = useState("");
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
  const [disabling, setDisabling] = useState(false);
  const [password, setPassword] = useState("");
  const [regenerating, setRegenerating] = useState(false);

  useEffect(() => {
    if (otpauthUrl) {
      QRCode.toDataURL(otpauthUrl, { width: 200 })
        .then(setQrDataUrl)
        .catch(() => setQrDataUrl(null));
    }
  }, [otpauthUrl]);

  /**
   * Enabling or disabling 2FA changes `user.two_factor_enabled`, which this card reads
   * from the auth store. One refetch of AppShell's own `me` query is enough: its
   * `setUser` effect pushes the fresh profile into the store. Fetching `/auth/me` here
   * *and* invalidating the query hit the endpoint twice for the same answer.
   */
  const refetchUser = () => qc.refetchQueries({ queryKey: queryKeys.me });

  const enableMutation = useMutation({
    mutationFn: () =>
      api<{ data: { secret: string; otpauth_url: string } }>("/auth/2fa/enable", {
        method: "POST",
      }),
    onSuccess: (res) => {
      setSecret(res.data.secret);
      setOtpauthUrl(res.data.otpauth_url);
      setEnrolling(true);
    },
  });

  const confirmMutation = useMutation({
    mutationFn: () =>
      api<{ data: { recovery_codes: string[] } }>("/auth/2fa/confirm", {
        method: "POST",
        body: { code },
      }),
    onSuccess: async (res) => {
      setRecoveryCodes(res.data.recovery_codes);
      setEnrolling(false);
      setCode("");
      await refetchUser();
      toast("Two-factor authentication enabled.");
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
      toast("Two-factor authentication disabled.");
    },
  });

  const regenerateMutation = useMutation({
    mutationFn: () =>
      api<{ data: { recovery_codes: string[] } }>("/auth/2fa/recovery-codes/regenerate", {
        method: "POST",
        body: { password },
      }),
    onSuccess: (res) => {
      setRecoveryCodes(res.data.recovery_codes);
      setRegenerating(false);
      setPassword("");
    },
  });

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-2">
        <CardTitle>Two-factor authentication</CardTitle>
        {enabled ? (
          <span className="flex items-center gap-1.5 text-xs font-medium text-[var(--color-success)]">
            <ShieldCheck className="h-4 w-4" /> Enabled
          </span>
        ) : (
          <span className="flex items-center gap-1.5 text-xs font-medium text-[var(--muted)]">
            <ShieldOff className="h-4 w-4" /> Disabled
          </span>
        )}
      </CardHeader>
      <CardContent className="space-y-3">
        <p className="text-sm text-[var(--muted)]">
          Require a 6-digit code from an authenticator app in addition to your password.
        </p>
        {enabled ? (
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" size="sm" onClick={() => setRegenerating(true)}>
              Regenerate recovery codes
            </Button>
            <Button
              variant="outline"
              size="sm"
              className="text-[var(--color-danger)]"
              onClick={() => setDisabling(true)}
            >
              Disable
            </Button>
          </div>
        ) : (
          <Button
            size="sm"
            onClick={() => enableMutation.mutate()}
            disabled={enableMutation.isPending}
          >
            Enable two-factor authentication
          </Button>
        )}
      </CardContent>

      <Dialog
        open={enrolling}
        onClose={() => setEnrolling(false)}
        title="Scan this QR code"
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
            <Input
              value={code}
              onChange={(e) => setCode(e.target.value)}
              inputMode="numeric"
              maxLength={6}
              autoFocus
            />
          </div>
        </div>
      </Dialog>

      <Dialog
        open={disabling}
        onClose={() => {
          setDisabling(false);
          setPassword("");
        }}
        title="Disable two-factor authentication"
        description="Enter your password to confirm."
        confirmLabel="Disable"
        confirmVariant="danger"
        loading={disableMutation.isPending}
        onConfirm={() => disableMutation.mutate()}
      >
        <div>
          <Label>Password</Label>
          <Input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoFocus
          />
        </div>
      </Dialog>

      <Dialog
        open={regenerating}
        onClose={() => {
          setRegenerating(false);
          setPassword("");
        }}
        title="Regenerate recovery codes"
        description="This invalidates your existing recovery codes. Enter your password to confirm."
        confirmLabel="Regenerate"
        confirmVariant="danger"
        loading={regenerateMutation.isPending}
        onConfirm={() => regenerateMutation.mutate()}
      >
        <div>
          <Label>Password</Label>
          <Input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoFocus
          />
        </div>
      </Dialog>

      <Dialog
        open={!!recoveryCodes}
        onClose={() => setRecoveryCodes(null)}
        title="Save your recovery codes"
        description="Each code can be used once to sign in if you lose access to your authenticator app. They will not be shown again."
      >
        <div className="grid grid-cols-2 gap-2 font-mono text-sm">
          {recoveryCodes?.map((c) => (
            <div key={c} className="rounded-[var(--radius)] bg-[var(--surface-2)] p-2 text-center">
              {c}
            </div>
          ))}
        </div>
        <div className="mt-4 flex gap-2">
          <Button
            variant="outline"
            size="sm"
            className="flex-1"
            onClick={() => {
              try {
                exportRecoveryCodesAsCsv(recoveryCodes!);
                toast("Recovery codes downloaded as CSV.");
              } catch {
                toast("Failed to export CSV.", "error");
              }
            }}
          >
            Download CSV
          </Button>
          <Button
            variant="outline"
            size="sm"
            className="flex-1"
            onClick={() => {
              try {
                exportRecoveryCodesAsPdf(recoveryCodes!);
                toast("Recovery codes opened for printing.");
              } catch {
                toast("Failed to export PDF.", "error");
              }
            }}
          >
            Download PDF
          </Button>
        </div>
        <Button className="mt-3 w-full" onClick={() => setRecoveryCodes(null)}>
          I&apos;ve saved these codes
        </Button>
      </Dialog>
    </Card>
  );
}
