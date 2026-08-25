import { useState } from "react";
import { Dialog } from "@/components/ui/dialog";
import { Input, Label } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
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

  const set = (key: string) => (e: React.ChangeEvent<HTMLInputElement>) =>
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
          <Select value={form.plan ?? ""} onValueChange={(v) => setForm((prev) => ({ ...prev, plan: v || undefined }))}>
            <SelectTrigger>
              <SelectValue placeholder="No plan" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">No plan</SelectItem>
              <SelectItem value="free">Free</SelectItem>
              <SelectItem value="starter">Starter</SelectItem>
              <SelectItem value="pro">Pro</SelectItem>
              <SelectItem value="enterprise">Enterprise</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label>Trial Ends</Label>
          <Input type="date" value={form.trial_ends_at ?? ""} onChange={set("trial_ends_at")} />
        </div>
        <div>
          <Label>Currency</Label>
          <Select value={form.base_currency ?? "USD"} onValueChange={(v) => setForm((prev) => ({ ...prev, base_currency: v || undefined }))}>
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="USD">USD — US Dollar</SelectItem>
              <SelectItem value="EUR">EUR — Euro</SelectItem>
              <SelectItem value="GBP">GBP — British Pound</SelectItem>
              <SelectItem value="BDT">BDT — Bangladeshi Taka</SelectItem>
              <SelectItem value="INR">INR — Indian Rupee</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
    </Dialog>
  );
}
