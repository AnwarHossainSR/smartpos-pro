import { useCallback, useRef, useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { ApiError, errorMessage } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { toast } from "@/components/ui/toast";
import { useTranslation } from "@/i18n/context";
import { approvalChallengeFrom, type ApprovalChallenge } from "@/components/approval-challenge";
import { posApi, type SaleDraft } from "@/features/pos/api";
import type { CartItem, Payment, Sale } from "@/features/pos/types";

/** A 422's first field error ("Insufficient stock for X") says far more than its summary. */
function validationDetail(e: unknown, fallback: string): string {
  if (e instanceof ApiError) return Object.values(e.errors ?? {})[0]?.[0] ?? e.message;
  return errorMessage(e, fallback);
}

/** What the cashier had already confirmed, replayed verbatim once a manager approves. */
type PendingAction =
  | { kind: "checkout"; payments: Payment[]; tenderCurrency?: string }
  | { kind: "park" };

export interface UseCheckoutOptions {
  items: CartItem[];
  customerId: string | null;
  tableId: string | null;
  branchId?: string;
  warehouseId?: string;
  /** Empties the basket and its customer/payment UI — shared by completed, parked and duplicate. */
  onSettled: () => void;
  onReceipt: (sale: Sale) => void;
}

export interface Checkout {
  checkout: (payments: Payment[], tenderCurrency?: string) => void;
  park: () => void;
  isCheckingOut: boolean;
  isParking: boolean;
  /** Non-null while the server is holding the sale for a supervisor PIN. */
  challenge: ApprovalChallenge | null;
  dismissChallenge: () => void;
  approve: (token: string) => void;
}

/**
 * The two ways a basket leaves the till: taking payment, and parking it for later.
 *
 * Both post to `/sales` with an Idempotency-Key (see `posApi.createSale`), and both can be
 * bounced by the supervisor gate — the server answers 403 with exactly what needs approving,
 * so the PIN dialog challenges on the server's numbers and the approved token is replayed
 * against the *same* request rather than a recomputed one. The action that raised the
 * challenge is remembered here, so approving a parked basket never accidentally charges it.
 */
export function useCheckout({
  items,
  customerId,
  tableId,
  branchId,
  warehouseId,
  onSettled,
  onReceipt,
}: UseCheckoutOptions): Checkout {
  const qc = useQueryClient();
  const t = useTranslation();
  const [challenge, setChallenge] = useState<ApprovalChallenge | null>(null);
  const pendingRef = useRef<PendingAction | null>(null);

  const draft = (extra: Partial<SaleDraft>): SaleDraft => ({
    branchId,
    warehouseId,
    customerId,
    tableId,
    items: items.map((i) => ({
      product_id: i.product.id,
      quantity: i.qty,
      unit_price: i.unit_price,
      discount_amount: i.discount * i.qty,
    })),
    payments: [],
    ...extra,
  });

  const checkoutMutation = useMutation({
    mutationFn: ({ payments, tenderCurrency, approvalToken }: { payments: Payment[]; tenderCurrency?: string; approvalToken?: string }) =>
      posApi.createSale(draft({ payments, tenderCurrency, approvalToken })),
    onSuccess: ({ data }) => {
      onReceipt(data);
      onSettled();
      setChallenge(null);
      pendingRef.current = null;
      // One key for the catalogue and the till, so a sale refreshes both screens' stock.
      qc.invalidateQueries({ queryKey: queryKeys.products.all });
      qc.invalidateQueries({ queryKey: queryKeys.dashboard.all });
    },
    onError: (e: unknown) => {
      // Over a till limit: the server hands back exactly what needs approving, so the
      // PIN dialog challenges on the server's numbers rather than the cart's.
      const needsApproval = approvalChallengeFrom(e);
      if (needsApproval) {
        setChallenge(needsApproval);
        return;
      }
      // The Idempotency-Key did its job: this basket was already charged, so treat the
      // replay as success rather than letting the cashier ring it up a second time.
      if (e instanceof ApiError && e.status === 409) {
        toast("This sale was already processed (duplicate)", "success");
        onSettled();
        qc.invalidateQueries({ queryKey: queryKeys.products.all });
        return;
      }
      toast(validationDetail(e, "Checkout failed"), "error");
    },
  });

  const parkMutation = useMutation({
    mutationFn: (approvalToken?: string) =>
      posApi.createSale(draft({ status: "held", approvalToken })),
    onSuccess: () => {
      onSettled();
      setChallenge(null);
      pendingRef.current = null;
      qc.invalidateQueries({ queryKey: queryKeys.heldSales.all });
      qc.invalidateQueries({ queryKey: queryKeys.sales.all });
      qc.invalidateQueries({ queryKey: queryKeys.products.all });
      toast(t("pos.parkSuccess"), "success");
    },
    onError: (e: unknown) => {
      const needsApproval = approvalChallengeFrom(e);
      if (needsApproval) {
        setChallenge(needsApproval);
        return;
      }
      toast(errorMessage(e, "Could not park sale"), "error");
    },
  });

  const checkout = useCallback((payments: Payment[], tenderCurrency?: string) => {
    pendingRef.current = { kind: "checkout", payments, tenderCurrency };
    checkoutMutation.mutate({ payments, tenderCurrency });
  }, [checkoutMutation]);

  const park = useCallback(() => {
    pendingRef.current = { kind: "park" };
    parkMutation.mutate(undefined);
  }, [parkMutation]);

  const approve = useCallback((token: string) => {
    const pending = pendingRef.current;
    setChallenge(null);
    if (!pending) return;
    if (pending.kind === "checkout") {
      // A manager never re-enters the tender — it is replayed exactly as confirmed.
      checkoutMutation.mutate({ payments: pending.payments, tenderCurrency: pending.tenderCurrency, approvalToken: token });
    } else {
      parkMutation.mutate(token);
    }
  }, [checkoutMutation, parkMutation]);

  return {
    checkout,
    park,
    isCheckingOut: checkoutMutation.isPending,
    isParking: parkMutation.isPending,
    challenge,
    dismissChallenge: () => setChallenge(null),
    approve,
  };
}
