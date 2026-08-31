import { useState } from "react";
import { Dialog } from "@/components/ui/dialog";
import { Input, Label } from "@/components/ui/input";
import { Select } from "@/components/ui/select";

export interface CreatePlanFormData {
  name: string;
  slug: string;
  price: number;
  currency: string;
  trial_days?: number;
  limits?: Record<string, unknown>;
  features?: string[];
  is_active?: boolean;
}

interface CreatePlanDialogProps {
  open: boolean;
  onClose: () => void;
  onSubmit: (data: CreatePlanFormData) => void;
  loading?: boolean;
}

export function CreatePlanDialog({ open, onClose, onSubmit, loading }: CreatePlanDialogProps) {
  const [form, setForm] = useState({
    name: "",
    slug: "",
    price: 0,
    currency: "USD",
    trial_days: 0,
    is_active: true,
  });

  const set = (key: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm((prev) => ({
      ...prev,
      [key]: key === "price" || key === "trial_days" ? Number(e.target.value) : e.target.value,
    }));

  const handleName = (e: React.ChangeEvent<HTMLInputElement>) => {
    const name = e.target.value;
    setForm((prev) => ({
      ...prev,
      name,
      slug: name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/(^-|-$)/g, ""),
    }));
  };

  const valid = form.name && form.slug && form.price >= 0;

  const handleClose = () => {
    setForm({ name: "", slug: "", price: 0, currency: "USD", trial_days: 0, is_active: true });
    onClose();
  };

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      title="Create Plan"
      description="Add a new subscription plan to the platform."
      confirmLabel="Create Plan"
      loading={loading}
      onConfirm={() => {
        if (valid) {
          onSubmit({
            name: form.name,
            slug: form.slug,
            price: form.price,
            currency: form.currency,
            trial_days: form.trial_days || undefined,
            is_active: form.is_active,
          });
        }
      }}
    >
      <div className="space-y-3">
        <div>
          <Label>Plan Name</Label>
          <Input value={form.name} onChange={handleName} placeholder="Pro Plan" />
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
      </div>
    </Dialog>
  );
}
