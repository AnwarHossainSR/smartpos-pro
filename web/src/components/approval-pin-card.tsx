import { useMutation, useQueryClient } from "@tanstack/react-query";
import { KeyRound, ShieldOff } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog } from "@/components/ui/dialog";
import { Input, Label } from "@/components/ui/input";
import { toast } from "@/components/ui/toast";
import { api } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { useAuth } from "@/stores/auth";

/**
 * The caller's own supervisor PIN. Setting or clearing it needs their password, and
 * the PIN itself is never read back — the API only ever reports whether one exists.
 */
export function ApprovalPinCard() {
  const user = useAuth((s) => s.user);
  const can = useAuth((s) => s.can);
  const qc = useQueryClient();

  const hasPin = !!user?.has_approval_pin;
  const canApprove = can("pos.approve");

  const [setting, setSetting] = useState(false);
  const [clearing, setClearing] = useState(false);
  const [password, setPassword] = useState("");
  const [pin, setPin] = useState("");
  const [confirmPin, setConfirmPin] = useState("");

  /**
   * `has_approval_pin` lives on the profile this card reads from the auth store, so a
   * refetch of AppShell's `me` query is the whole update — its `setUser` effect writes
   * the result into the store. Fetching `/auth/me` here as well only asked twice.
   */
  const refetchUser = () => qc.refetchQueries({ queryKey: queryKeys.me });

  function reset() {
    setPassword("");
    setPin("");
    setConfirmPin("");
  }

  const saveMutation = useMutation({
    mutationFn: () =>
      api("/auth/approval-pin", {
        method: "PUT",
        body: { current_password: password, pin, pin_confirmation: confirmPin },
      }),
    onSuccess: async () => {
      setSetting(false);
      reset();
      await refetchUser();
      toast("Approval PIN saved");
    },
  });

  const clearMutation = useMutation({
    mutationFn: () => api("/auth/approval-pin", { method: "DELETE", body: { current_password: password } }),
    onSuccess: async () => {
      setClearing(false);
      reset();
      await refetchUser();
      toast("Approval PIN removed");
    },
  });

  const pinValid = /^\d{4,8}$/.test(pin) && pin === confirmPin;

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-2">
        <CardTitle className="flex items-center gap-2">
          <KeyRound className="h-4 w-4 text-[var(--primary)]" />
          Approval PIN
        </CardTitle>
        <Badge tone={hasPin ? "success" : "default"}>{hasPin ? "Set" : "Not set"}</Badge>
      </CardHeader>
      <CardContent className="space-y-3">
        <p className="text-sm text-[var(--muted)]">
          {canApprove
            ? "Type this PIN on a terminal to authorise an over-limit discount, a price override or a void — without signing the cashier out."
            : "Your role cannot authorise till actions, so a PIN would not approve anything. Ask an owner for the approval permission first."}
        </p>

        <div className="flex flex-wrap gap-2">
          <Button size="sm" variant={hasPin ? "outline" : "primary"} onClick={() => { reset(); setSetting(true); }}>
            {hasPin ? "Change PIN" : "Set a PIN"}
          </Button>
          {hasPin && (
            <Button
              size="sm"
              variant="ghost"
              className="text-[var(--color-danger)]"
              onClick={() => { reset(); setClearing(true); }}
            >
              <ShieldOff className="h-3.5 w-3.5" />
              Remove
            </Button>
          )}
        </div>
      </CardContent>

      <Dialog
        open={setting}
        onClose={() => { setSetting(false); reset(); }}
        title={hasPin ? "Change your approval PIN" : "Set your approval PIN"}
        description="4 to 8 digits. Confirm with your account password."
      >
        <div className="space-y-3">
          <div>
            <Label>Current password</Label>
            <Input type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} />
          </div>
          <div>
            <Label>New PIN</Label>
            <Input
              type="password"
              inputMode="numeric"
              autoComplete="off"
              value={pin}
              onChange={(e) => setPin(e.target.value.replace(/\D/g, "").slice(0, 8))}
            />
          </div>
          <div>
            <Label>Confirm PIN</Label>
            <Input
              type="password"
              inputMode="numeric"
              autoComplete="off"
              value={confirmPin}
              onChange={(e) => setConfirmPin(e.target.value.replace(/\D/g, "").slice(0, 8))}
            />
            {pin.length > 0 && !pinValid && (
              <p className="mt-1 text-xs text-[var(--color-danger)]">
                {/^\d{4,8}$/.test(pin) ? "The two PINs don't match." : "The PIN must be 4 to 8 digits."}
              </p>
            )}
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="outline" onClick={() => { setSetting(false); reset(); }} disabled={saveMutation.isPending}>
              Cancel
            </Button>
            <Button
              onClick={() => saveMutation.mutate()}
              disabled={saveMutation.isPending || !pinValid || password.length === 0}
            >
              {saveMutation.isPending ? "Saving…" : "Save PIN"}
            </Button>
          </div>
        </div>
      </Dialog>

      <Dialog
        open={clearing}
        onClose={() => { setClearing(false); reset(); }}
        title="Remove your approval PIN"
        description="You will no longer be able to authorise till actions until you set a new one."
      >
        <div className="space-y-3">
          <div>
            <Label>Current password</Label>
            <Input type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} />
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="outline" onClick={() => { setClearing(false); reset(); }} disabled={clearMutation.isPending}>
              Cancel
            </Button>
            <Button
              variant="danger"
              onClick={() => clearMutation.mutate()}
              disabled={clearMutation.isPending || password.length === 0}
            >
              {clearMutation.isPending ? "Removing…" : "Remove PIN"}
            </Button>
          </div>
        </div>
      </Dialog>
    </Card>
  );
}
