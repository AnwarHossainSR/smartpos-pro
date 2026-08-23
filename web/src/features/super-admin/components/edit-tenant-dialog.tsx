import { useState } from "react";
import { Dialog } from "@/components/ui/dialog";
import { Input, Label } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import type { TenantDetail } from "../types";

export interface EditTenantFormData {
  name?: string;
  plan?: string;
  trial_ends_at?: string | null;
  base_currency?: string;
}

interface EditTenantDialogProps {
  tenant: TenantDetail | null;
  onClose: () => void;
  onSubmit: (data: EditTenantFormData) => void;
  loading?: boolean;
}

function initForm(tenant: TenantDetail | null): EditTenantFormData {
  if (!tenant) return { name: "", plan: "", trial_ends_at: "", base_currency: "USD" };
  return {
    name: tenant.name,
    plan: tenant.plan ?? "",
    trial_ends_at: tenant.trial_ends_at ? tenant.trial_ends_at.slice(0, 10) : "",
    base_currency: tenant.base_currency ?? "USD",
  };
}

export function EditTenantDialog({ tenant, onClose, onSubmit, loading }: EditTenantDialogProps) {
  const [form, setForm] = useState<EditTenantFormData>(() => initForm(tenant));

  const set = (key: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm((prev) => ({ ...prev, [key]: e.target.value || undefined }));

  return (
    <Dialog
      open={!!tenant}
      onClose={onClose}
      title="Edit Tenant"
      description="Update tenant settings and subscription details."
      confirmLabel="Save"
      loading={loading}
      onConfirm={() => onSubmit(form)}
    >
      <div className="space-y-3">
        <div>
          <Label>Tenant Name</Label>
          <Input value={form.name ?? ""} onChange={set("name")} placeholder="Acme Corp" />
        </div>
        <div>
          <Label>Plan</Label>
          <Select value={form.plan ?? ""} onChange={set("plan")}>
            <option value="">No plan</option>
            <option value="free">Free</option>
            <option value="starter">Starter</option>
            <option value="pro">Pro</option>
            <option value="enterprise">Enterprise</option>
          </Select>
        </div>
        <div>
          <Label>Trial Ends</Label>
          <Input type="date" value={form.trial_ends_at ?? ""} onChange={set("trial_ends_at")} />
        </div>
        <div>
          <Label>Currency</Label>
          <Select value={form.base_currency ?? "USD"} onChange={set("base_currency")}>
            <option value="USD">USD — US Dollar</option>
            <option value="EUR">EUR — Euro</option>
            <option value="GBP">GBP — British Pound</option>
            <option value="BDT">BDT — Bangladeshi Taka</option>
            <option value="INR">INR — Indian Rupee</option>
          </Select>
        </div>
      </div>
    </Dialog>
  );
}
