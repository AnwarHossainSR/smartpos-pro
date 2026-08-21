import { ApiError } from "@/lib/api";

/**
 * The supervisor-gate vocabulary, kept out of `approval-pin-dialog.tsx` because a module
 * that exports a React component may not also export helpers — Vite re-executes it on
 * every edit and the re-created module-level values break Fast Refresh
 * (`react-refresh/only-export-components`).
 */
export type ApprovalAction = "discount" | "price_override" | "void";

/**
 * What the server said it wants approved. Always taken verbatim from the `403`
 * the API returned — never recomputed here — so the value the manager approves
 * is exactly the value the checkout endpoint will re-derive on the retry.
 */
export interface ApprovalChallenge {
  action: ApprovalAction;
  requested_value: number;
  limit_value: number | null;
  sale_id?: string | null;
  message?: string;
}

interface ApprovalErrorPayload {
  code?: string;
  message?: string;
  error?: { action?: string; requested_value?: number; limit_value?: number | null };
}

const ACTIONS: ApprovalAction[] = ["discount", "price_override", "void"];

/**
 * Turns a rejected request into a challenge, when the rejection was a supervisor
 * gate rather than a real failure. Returns null for anything else so the caller
 * falls through to its normal error handling.
 */
export function approvalChallengeFrom(error: unknown): ApprovalChallenge | null {
  if (!(error instanceof ApiError) || error.status !== 403) return null;

  const payload = error.payload as ApprovalErrorPayload | null;
  if (payload?.code !== "approval_required") return null;

  const action = payload.error?.action;
  if (!action || !ACTIONS.includes(action as ApprovalAction)) return null;

  return {
    action: action as ApprovalAction,
    requested_value: payload.error?.requested_value ?? 0,
    limit_value: payload.error?.limit_value ?? null,
    message: payload.message,
  };
}
