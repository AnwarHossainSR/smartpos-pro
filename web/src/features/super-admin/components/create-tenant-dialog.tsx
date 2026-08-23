import { useState } from "react";
import { Dialog } from "@/components/ui/dialog";
import { Input, Label } from "@/components/ui/input";
import { Select } from "@/components/ui/select";

export interface CreateTenantFormData {
  tenant_name: string;
  slug: string;
  business_name: string;
  owner_name: string;
  owner_email: string;
  owner_password: string;
  owner_password_confirmation: string;
  base_currency?: string;
}

interface CreateTenantDialogProps {
  open: boolean;
  onClose: () => void;
  onSubmit: (data: CreateTenantFormData) => void;
  loading?: boolean;
}

export function CreateTenantDialog({
  open,
  onClose,
  onSubmit,
  loading,
}: CreateTenantDialogProps) {
  const [form, setForm] = useState({
    tenant_name: "",
    slug: "",
    business_name: "",
    owner_name: "",
    owner_email: "",
    owner_password: "",
    owner_password_confirmation: "",
    base_currency: "USD",
  });

  const set = (key: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm((prev) => ({ ...prev, [key]: e.target.value }));

  const handleTenantName = (e: React.ChangeEvent<HTMLInputElement>) => {
    const name = e.target.value;
    setForm((prev) => ({
      ...prev,
      tenant_name: name,
      slug: name.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, ""),
    }));
  };

  const valid =
    form.tenant_name &&
    form.slug &&
    form.business_name &&
    form.owner_name &&
    form.owner_email &&
    form.owner_password &&
    form.owner_password === form.owner_password_confirmation;

  const handleClose = () => {
    setForm({
      tenant_name: "",
      slug: "",
      business_name: "",
      owner_name: "",
      owner_email: "",
      owner_password: "",
      owner_password_confirmation: "",
      base_currency: "USD",
    });
    onClose();
  };

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      title="Create Tenant"
      description="Onboard a new business to the platform."
      confirmLabel="Create Tenant"
      loading={loading}
      onConfirm={() => {
        if (valid) onSubmit(form);
      }}
    >
      <div className="space-y-3">
        <div>
          <Label>Tenant Name</Label>
          <Input value={form.tenant_name} onChange={handleTenantName} placeholder="Acme Corp" />
        </div>
        <div>
          <Label>Slug</Label>
          <Input value={form.slug} onChange={set("slug")} placeholder="acme-corp" />
        </div>
        <div>
          <Label>Business Name</Label>
          <Input value={form.business_name} onChange={set("business_name")} placeholder="Acme Store" />
        </div>
        <div>
          <Label>Owner Name</Label>
          <Input value={form.owner_name} onChange={set("owner_name")} placeholder="John Doe" />
        </div>
        <div>
          <Label>Owner Email</Label>
          <Input type="email" value={form.owner_email} onChange={set("owner_email")} placeholder="john@acme.com" />
        </div>
        <div>
          <Label>Owner Password</Label>
          <Input type="password" value={form.owner_password} onChange={set("owner_password")} />
        </div>
        <div>
          <Label>Confirm Password</Label>
          <Input type="password" value={form.owner_password_confirmation} onChange={set("owner_password_confirmation")} />
        </div>
        <div>
          <Label>Currency</Label>
          <Select className="mt-1.5" value={form.base_currency} onChange={set("base_currency")}>
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
