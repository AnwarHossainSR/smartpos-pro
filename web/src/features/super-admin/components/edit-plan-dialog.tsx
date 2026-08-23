import { useState } from "react";
import { Dialog } from "@/components/ui/dialog";
import { Input, Label } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import type { SuperAdminPlan } from "../types";

export interface EditPlanFormData {
  name: string;
  slug: string;
  price: number;
  currency: string;
  trial_days?: number;
  limits?: Record<string, unknown>;
  features?: string[];
  is_active?: boolean;
}

interface EditPlanDialogProps {
  plan: SuperAdminPlan | null;
  onClose: () => void;
  onSubmit: (data: EditPlanFormData) => void;
  loading?: boolean;
}

function initForm(plan: SuperAdminPlan | null): EditPlanFormData {
  if (!plan) return { name: "", slug: "", price: 0, currency: "USD", trial_days: 0, is_active: true };
  return {
    name: plan.name,
    slug: plan.slug,
    price: plan.price,
    currency: plan.currency,
    trial_days: plan.trial_days ?? 0,
    is_active: plan.is_active,
  };
}

export function EditPlanDialog({ plan, onClose, onSubmit, loading }: EditPlanDialogProps) {
  const [form, setForm] = useState<EditPlanFormData>(() => initForm(plan));

  const set = (key: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm((prev) => ({
      ...prev,
      [key]: key === "price" || key === "trial_days" ? Number(e.target.value) : e.target.value,
    }));

  const valid = form.name && form.slug && form.price >= 0;

  return (
    <Dialog
      open={!!plan}
      onClose={onClose}
      title="Edit Plan"
      description="Update plan details and pricing."
      confirmLabel="Save"
      loading={loading}
      onConfirm={() => {
        if (valid) onSubmit(form);
      }}
    >
      <div className="space-y-3">
        <div>
          <Label>Plan Name</Label>
          <Input value={form.name} onChange={set("name")} placeholder="Pro Plan" />
        </div>
        <div>
          <Label>Slug</Label>
          <Input value={form.slug} onChange={set("slug")} placeholder="pro-plan" />
        </div>
        <div>
          <Label>Price</Label>
          <Input type="number" min={0} step={0.01} value={form.price} onChange={set("price")} />
        </div>
        <div>
          <Label>Currency</Label>
          <Select value={form.currency} onChange={set("currency")}>
            <option value="USD">USD — US Dollar</option>
            <option value="EUR">EUR — Euro</option>
            <option value="GBP">GBP — British Pound</option>
            <option value="BDT">BDT — Bangladeshi Taka</option>
            <option value="INR">INR — Indian Rupee</option>
          </Select>
        </div>
        <div>
          <Label>Trial Days</Label>
          <Input type="number" min={0} value={form.trial_days} onChange={set("trial_days")} />
        </div>
        <div className="flex items-center gap-2">
          <input
            type="checkbox"
            id="plan-active"
            checked={form.is_active ?? true}
            onChange={(e) => setForm((prev) => ({ ...prev, is_active: e.target.checked }))}
            className="h-4 w-4 rounded border-[var(--border)]"
          />
          <Label htmlFor="plan-active">Active</Label>
        </div>
      </div>
    </Dialog>
  );
}
