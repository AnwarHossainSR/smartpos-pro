import { useMutation } from "@tanstack/react-query";
import { Delete, ShieldCheck } from "lucide-react";
import { useRef, useState } from "react";
import type { ApprovalAction, ApprovalChallenge } from "@/components/approval-challenge";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { api, errorMessage } from "@/lib/api";
import { cn, formatCurrency } from "@/lib/utils";

const TITLES: Record<ApprovalAction, string> = {
  discount: "Manager approval — discount",
  price_override: "Manager approval — price override",
  void: "Manager approval — void sale",
};

const BLURBS: Record<ApprovalAction, string> = {
  discount: "This discount is over the limit for this till.",
  price_override: "Selling below the catalogue price needs a supervisor.",
  void: "Voiding a completed sale needs a supervisor.",
};

interface ApprovalPinDialogProps {
  /** Null keeps the dialog closed; set it to the challenge the API returned to open it. */
  challenge: ApprovalChallenge | null;
  onCancel: () => void;
  /** Hands back the single-use token — retry the original request with it straight away. */
  onApproved: (token: string) => void;
}

const KEYS = ["1", "2", "3", "4", "5", "6", "7", "8", "9"];
const MAX_PIN = 8;

/**
 * The supervisor challenge at the till: a manager types their PIN on the terminal
 * without signing the cashier out. The token that comes back is single-use and
 * short-lived, and the server re-checks the limit on the retry — so nothing here
 * is a security boundary, it is just the fastest way to get past one.
 *
 * The form is a separate component that only exists while a challenge is open, so
 * closing the dialog unmounts it and the typed PIN never survives into the next one.
 */
export function ApprovalPinDialog({ challenge, onCancel, onApproved }: ApprovalPinDialogProps) {
  if (!challenge) return null;

  return <PinChallengeForm challenge={challenge} onCancel={onCancel} onApproved={onApproved} />;
}

function PinChallengeForm({
  challenge,
  onCancel,
  onApproved,
}: ApprovalPinDialogProps & { challenge: ApprovalChallenge }) {
  const [pin, setPin] = useState("");
  const [error, setError] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const mutation = useMutation({
    mutationFn: async () => {
      return api<{ data: { token: string } }>("/pos/approvals", {
        method: "POST",
        body: {
          action: challenge.action,
          pin,
          requested_value: challenge.requested_value,
          limit_value: challenge.limit_value,
          sale_id: challenge.sale_id ?? null,
        },
      });
    },
    onSuccess: (res) => {
      setPin("");
      onApproved(res.data.token);
    },
    // Reported inline under the keypad, never as a toast — the manager is looking at
    // the field they just mistyped, not at the corner of the screen.
    meta: { silent: true },
    onError: (e: unknown) => {
      setPin("");
      setError(errorMessage(e, "Could not verify that PIN"));
      inputRef.current?.focus();
    },
  });

  const submit = () => {
    if (pin.length < 4 || mutation.isPending) return;
    setError(null);
    mutation.mutate();
  };

  const append = (digit: string) => setPin((p) => (p.length >= MAX_PIN ? p : p + digit));

  return (
    <Dialog open onClose={onCancel} title={TITLES[challenge.action]}>
      <div className="space-y-4">
        <div className="rounded-[var(--radius)] bg-[var(--surface-2)] p-3 text-sm">
          <p className="text-[var(--muted)]">{BLURBS[challenge.action]}</p>
          <div className="mt-2 flex items-center justify-between gap-3">
            <span className="text-[var(--muted)]">
              {challenge.action === "void" ? "Sale total" : "Requested"}
            </span>
            <span className="font-semibold">{formatCurrency(challenge.requested_value)}</span>
          </div>
          {challenge.limit_value !== null && (
            <div className="mt-1 flex items-center justify-between gap-3">
              <span className="text-[var(--muted)]">Allowed without approval</span>
              <span className="font-medium">{formatCurrency(challenge.limit_value)}</span>
            </div>
          )}
        </div>

        <div>
          {/* The real focusable field: masked, numeric, and driven by either the
              physical keyboard or the keypad below. */}
          <input
            ref={inputRef}
            type="password"
            inputMode="numeric"
            autoComplete="off"
            aria-label="Manager PIN"
            autoFocus
            value={pin}
            onChange={(e) => setPin(e.target.value.replace(/\D/g, "").slice(0, MAX_PIN))}
            onKeyDown={(e) => {
              if (e.key === "Enter") {
                e.preventDefault();
                submit();
              }
            }}
            className={cn(
              "h-14 w-full rounded-[var(--radius)] border bg-[var(--surface)] text-center text-2xl tracking-[0.6em] outline-none transition",
              error
                ? "border-[var(--color-danger)] focus:ring-2 focus:ring-[var(--color-danger)]/20"
                : "border-[var(--border)] focus:border-[var(--primary)] focus:ring-2 focus:ring-[var(--primary)]/20",
            )}
            placeholder="••••"
          />
          <p
            className={cn(
              "mt-1.5 min-h-[1rem] text-xs",
              error ? "text-[var(--color-danger)]" : "text-[var(--muted)]",
            )}
            role={error ? "alert" : undefined}
          >
            {error ?? "4 to 8 digits"}
          </p>
        </div>

        <div className="grid grid-cols-3 gap-2">
          {KEYS.map((key) => (
            <button
              key={key}
              type="button"
              onClick={() => append(key)}
              className="h-12 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] text-lg font-medium transition-colors active:scale-95 active:bg-[var(--surface-2)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary)]"
            >
              {key}
            </button>
          ))}
          <button
            type="button"
            onClick={() => {
              setPin("");
              setError(null);
            }}
            className="h-12 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] text-sm font-medium text-[var(--muted)] transition-colors active:scale-95 active:bg-[var(--surface-2)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary)]"
          >
            Clear
          </button>
          <button
            type="button"
            onClick={() => append("0")}
            className="h-12 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] text-lg font-medium transition-colors active:scale-95 active:bg-[var(--surface-2)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary)]"
          >
            0
          </button>
          <button
            type="button"
            onClick={() => setPin((p) => p.slice(0, -1))}
            aria-label="Delete last digit"
            className="flex h-12 items-center justify-center rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] transition-colors active:scale-95 active:bg-[var(--surface-2)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary)]"
          >
            <Delete className="h-5 w-5" />
          </button>
        </div>

        <div className="flex gap-2">
          <Button
            variant="outline"
            className="flex-1"
            onClick={onCancel}
            disabled={mutation.isPending}
          >
            Cancel
          </Button>
          <Button
            className="flex-1"
            onClick={submit}
            disabled={pin.length < 4 || mutation.isPending}
          >
            <ShieldCheck className="h-4 w-4" />
            {mutation.isPending ? "Checking…" : "Approve"}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}
