import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Dialog } from "@/components/ui/dialog";
import { Label } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { queryKeys } from "@/lib/query-keys";
import { superAdminApi } from "../api";
import type { SuperAdminPlan } from "../types";

interface AssignPlanDialogProps {
  open: boolean;
  onClose: () => void;
  onSubmit: (planId: string) => void;
  loading?: boolean;
}

export function AssignPlanDialog({ open, onClose, onSubmit, loading }: AssignPlanDialogProps) {
  const [selectedPlanId, setSelectedPlanId] = useState("");

  const plansQuery = useQuery({
    queryKey: queryKeys.superAdmin.plans(),
    queryFn: () => superAdminApi.plans.list({ search: "", sort: undefined, page: 1, perPage: 200, filters: {} }),
  });

  const plans: SuperAdminPlan[] = plansQuery.data?.data ?? [];

  const handleClose = () => {
    setSelectedPlanId("");
    onClose();
  };

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      title="Assign Plan"
      description="Assign a subscription plan to this tenant."
      confirmLabel="Assign"
      confirmVariant="default"
      loading={loading}
      onConfirm={() => {
        if (selectedPlanId) onSubmit(selectedPlanId);
      }}
    >
      <div className="space-y-3">
        <div>
          <Label>Plan</Label>
          <Select value={selectedPlanId} onValueChange={(v) => setSelectedPlanId(v)}>
            <SelectTrigger>
              <SelectValue placeholder="Select a plan…" />
            </SelectTrigger>
            <SelectContent>
              {plans.map((plan) => (
                <SelectItem key={plan.id} value={plan.id}>
                  {plan.name} — {plan.currency} {plan.price.toFixed(2)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>
    </Dialog>
  );
}
