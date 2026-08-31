import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "@/components/ui/toast";
import { queryKeys } from "@/lib/query-keys";
import {
  closeTicket,
  createTicket,
  getMyTicket,
  getMyTickets,
  reopenTicket,
  replyToTicket,
} from "./api";

export function useMyTickets(params?: Record<string, string | number | undefined>) {
  return useQuery({
    queryKey: queryKeys.support.tickets(params),
    queryFn: () => getMyTickets(params),
  });
}

export function useMyTicket(id: string | null) {
  return useQuery({
    queryKey: queryKeys.support.ticket(id ?? undefined),
    queryFn: () => getMyTicket(id!),
    enabled: !!id,
  });
}

export function useCreateTicket() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: {
      subject: string;
      message: string;
      category?: string;
      priority?: string;
    }) => createTicket(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.support.all });
      toast("Ticket created", "success");
    },
  });
}

export function useReplyToTicket() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, message }: { id: string; message: string }) => replyToTicket(id, message),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.support.all });
      toast("Reply sent", "success");
    },
  });
}

export function useCloseTicket() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => closeTicket(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.support.all });
      toast("Ticket closed", "success");
    },
  });
}

export function useReopenTicket() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => reopenTicket(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.support.all });
      toast("Ticket reopened", "success");
    },
  });
}
