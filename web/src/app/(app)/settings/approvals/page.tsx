import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, ShieldCheck } from "lucide-react";
import { Link } from "react-router";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input, Label } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "@/components/ui/toast";
import { api, type Envelope } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { useAuth } from "@/stores/auth";

interface ApprovalPolicy {
  max_discount_percent: number;
  max_discount_amount: number | null;
  require_approval_for_price_override: boolean;
  require_approval_for_void: boolean;
  approval_ttl_minutes: number;
}

function Toggle({
  checked,
  disabled,
  onChange,
  label,
  hint,
}: {
  checked: boolean;
  disabled?: boolean;
  onChange: (v: boolean) => void;
  label: string;
  hint: string;
}) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      disabled={disabled}
      onClick={() => onChange(!checked)}
      className="flex w-full items-center gap-3 rounded-[var(--radius)] border border-[var(--border)] p-4 text-start transition-colors hover:bg-[var(--surface-2)] disabled:cursor-not-allowed disabled:opacity-60"
    >
      <div className="min-w-0 flex-1">
        <p className="font-medium">{label}</p>
        <p className="text-xs text-[var(--muted)]">{hint}</p>
      </div>
      <span
        className={`relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors ${
          checked ? "bg-[var(--primary)]" : "bg-[var(--border)]"
        }`}
      >
        <span
          className={`pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-lg transition-transform ${
            checked ? "translate-x-5" : "translate-x-0"
          }`}
        />
      </span>
    </button>
  );
}

/**
 * Mounted only once the saved policy has loaded, so the form seeds its state from
 * props on mount rather than syncing it from an effect on every fetch.
 */
function PolicyForm({ policy, canManage }: { policy: ApprovalPolicy; canManage: boolean }) {
  const qc = useQueryClient();
  const [percent, setPercent] = useState(String(policy.max_discount_percent));
  const [amount, setAmount] = useState(policy.max_discount_amount === null ? "" : String(policy.max_discount_amount));
  const [overrideNeedsApproval, setOverrideNeedsApproval] = useState(policy.require_approval_for_price_override);
  const [voidNeedsApproval, setVoidNeedsApproval] = useState(policy.require_approval_for_void);

  const mutation = useMutation({
    mutationFn: () =>
      api<Envelope<ApprovalPolicy>>("/settings/approvals", {
        method: "PUT",
        body: {
          max_discount_percent: Number(percent || 0),
          max_discount_amount: amount.trim() === "" ? null : Number(amount),
          require_approval_for_price_override: overrideNeedsApproval,
          require_approval_for_void: voidNeedsApproval,
        },
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.approvalPolicy });
      toast("Approval limits saved");
    },
  });

  const percentNumber = Number(percent);
  const percentInvalid =
    percent.trim() === "" || Number.isNaN(percentNumber) || percentNumber < 0 || percentNumber > 100;
  const amountInvalid = amount.trim() !== "" && (Number.isNaN(Number(amount)) || Number(amount) < 0);

  return (
    <>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <Label>Max discount without approval (%)</Label>
          <Input
            type="number"
            min={0}
            max={100}
            step="0.01"
            value={percent}
            disabled={!canManage}
            onChange={(e) => setPercent(e.target.value)}
          />
          <p className="mt-1 text-xs text-[var(--muted)]">
            Measured against the basket at catalogue prices. 100 means no limit.
          </p>
          {percentInvalid && (
            <p className="mt-1 text-xs text-[var(--color-danger)]">Enter a number between 0 and 100.</p>
          )}
        </div>
        <div>
          <Label>Max discount without approval (amount)</Label>
          <Input
            type="number"
            min={0}
            step="0.01"
            placeholder="No cap"
            value={amount}
            disabled={!canManage}
            onChange={(e) => setAmount(e.target.value)}
          />
          <p className="mt-1 text-xs text-[var(--muted)]">
            Optional hard ceiling in your currency. Leave blank for none.
          </p>
          {amountInvalid && (
            <p className="mt-1 text-xs text-[var(--color-danger)]">Enter a positive amount, or leave blank.</p>
          )}
        </div>
      </div>

      <div className="space-y-3">
        <Toggle
          checked={overrideNeedsApproval}
          disabled={!canManage}
          onChange={setOverrideNeedsApproval}
          label="Price overrides need approval"
          hint="Selling a line below its catalogue price raises the PIN prompt."
        />
        <Toggle
          checked={voidNeedsApproval}
          disabled={!canManage}
          onChange={setVoidNeedsApproval}
          label="Voids need approval"
          hint="Cancelling a completed sale raises the PIN prompt."
        />
      </div>

      {canManage && (
        <Button
          onClick={() => mutation.mutate()}
          disabled={mutation.isPending || percentInvalid || amountInvalid}
          className="w-full sm:w-auto"
        >
          {mutation.isPending ? "Saving…" : "Save limits"}
        </Button>
      )}
    </>
  );
}

/**
 * The till's supervisor limits. These are the numbers the checkout endpoint
 * re-derives on every sale — the POS terminal reads them only to raise the PIN
 * prompt early, so loosening them here is the one way to loosen them at all.
 */
export default function ApprovalSettingsPage() {
  const { can } = useAuth();
  const canView = can("settings.view");
  const canManage = can("settings.update");

  const { data, isLoading, isError, refetch } = useQuery<Envelope<ApprovalPolicy>>({
    queryKey: queryKeys.approvalPolicy,
    queryFn: () => api<Envelope<ApprovalPolicy>>("/settings/approvals"),
    enabled: canView,
  });

  if (!canView) {
    return (
      <div className="py-16 text-center text-[var(--muted)]">
        You don&apos;t have permission to view these settings.
      </div>
    );
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-2">
        <Link to="/settings" className="text-[var(--muted)] hover:text-[var(--fg)]">
          <ArrowLeft className="h-4 w-4" />
        </Link>
        <h1 className="text-xl font-semibold">Manager approvals</h1>
      </div>

      <div className="grid grid-cols-1 gap-5 xl:grid-cols-4">
        <Card className="xl:col-span-3">
          <CardHeader>
            <CardTitle>Till limits</CardTitle>
          </CardHeader>
          <CardContent className="space-y-5">
            {isLoading ? (
              <div className="space-y-3">
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-16 w-full" />
              </div>
            ) : isError || !data ? (
              <div className="space-y-2 py-8 text-center text-sm">
                <p className="text-[var(--color-danger)]">Could not load the approval limits.</p>
                <button
                  type="button"
                  onClick={() => refetch()}
                  className="text-[var(--primary)] underline underline-offset-4"
                >
                  Try again
                </button>
              </div>
            ) : (
              <PolicyForm policy={data.data} canManage={canManage} />
            )}
          </CardContent>
        </Card>

        <div className="space-y-5">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <ShieldCheck className="h-4 w-4 text-[var(--primary)]" />
                How it works
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm text-[var(--muted)]">
              <p>
                When a cashier goes over a limit the sale is refused and the terminal asks for a
                manager PIN. The approval it mints is single-use and expires in minutes.
              </p>
              <p>
                Only team members with the <strong className="text-[var(--fg)]">pos.approve</strong>{" "}
                permission can approve — owners, admins and branch managers by default. Everyone who
                approves sets their own PIN under Settings → Security.
              </p>
              <p>Every approval and every rejected PIN is written to the audit log.</p>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
