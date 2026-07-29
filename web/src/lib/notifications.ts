import { formatCurrency } from "@/lib/utils";

export interface Notification {
  id: string;
  type: string;
  data: Record<string, unknown>;
  read_at: string | null;
  created_at: string;
}

export interface NotificationResponse {
  data: Notification[];
  unread_count: number;
}

export function notifTitle(n: Notification): string {
  const d = n.data;
  if (typeof d.title === "string") return d.title;
  if (typeof d.message === "string") return d.message;
  return n.type.split("\\").pop() ?? "Notification";
}

export function notifBody(n: Notification): string {
  const d = n.data;
  if (typeof d.body === "string") return d.body;
  if (typeof d.amount === "number") return `Amount: ${formatCurrency(d.amount)}`;
  return "";
}
