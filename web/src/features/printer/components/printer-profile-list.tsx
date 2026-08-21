import { useCallback, useState } from "react";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Input, Label } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "@/components/ui/toast";
import { useTranslation } from "@/i18n/context";
import { api, ApiError } from "@/lib/api";
import { connectUsbPrinter, getConnectedPrinter, isWebUsbAvailable } from "@/lib/escpos-printer";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Printer, Plus, Pencil, Trash2, Usb, Wifi, Cable } from "lucide-react";
import { useForm } from "react-hook-form";

interface PrinterProfile {
  id: string;
  name: string;
  connection_type: "usb" | "network" | "serial";
  ip_address: string | null;
  port: number | null;
  paper_width: number;
  auto_cut: boolean;
  open_drawer: boolean;
}

export function PrinterProfileList() {
  const t = useTranslation();
  const qc = useQueryClient();
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<PrinterProfile | null>(null);
  const [deleting, setDeleting] = useState<PrinterProfile | null>(null);
  const [testing, setTesting] = useState(false);

  const { data, isLoading } = useQuery<{ data: PrinterProfile[] }>({
    queryKey: ["printer-profiles"],
    queryFn: () => api<{ data: PrinterProfile[] }>("/printer-profiles"),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api(`/printer-profiles/${id}`, { method: "DELETE" }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["printer-profiles"] });
      toast(t("common.delete") + "d");
      setDeleting(null);
    },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  const handleTestPrint = useCallback(async () => {
    setTesting(true);
    try {
      if (isWebUsbAvailable()) {
        const printer = getConnectedPrinter() ?? await connectUsbPrinter();
        if (printer) {
          toast(t("settings.printer.testSent"));
        } else {
          toast(t("pos.printerNotFound"), "error");
        }
      } else {
        toast(t("pos.printNoUsb"), "error");
      }
    } catch {
      toast(t("pos.printerError"), "error");
    } finally {
      setTesting(false);
    }
  }, [t]);

  const profiles = data?.data ?? [];
  const connIcon = (type: string) => {
    if (type === "usb") return <Usb className="h-4 w-4" />;
    if (type === "network") return <Wifi className="h-4 w-4" />;
    return <Cable className="h-4 w-4" />;
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-[var(--muted)]">
          {profiles.length} {profiles.length === 1 ? "printer" : "printers"} configured
        </p>
        <Button size="sm" onClick={() => { setEditing(null); setShowForm(true); }} className="gap-1">
          <Plus className="h-4 w-4" /> {t("settings.printer.add")}
        </Button>
      </div>

      {isLoading ? (
        <div className="space-y-2">
          <Skeleton className="h-16 w-full" />
          <Skeleton className="h-16 w-full" />
        </div>
      ) : profiles.length === 0 ? (
        <div className="rounded-lg border border-dashed border-[var(--border)] p-8 text-center">
          <Printer className="mx-auto mb-3 h-8 w-8 text-[var(--muted)]" />
          <p className="text-sm text-[var(--muted)]">{t("settings.printer.none")}</p>
          <Button size="sm" className="mt-3" onClick={() => { setEditing(null); setShowForm(true); }}>
            {t("settings.printer.add")}
          </Button>
        </div>
      ) : (
        <div className="space-y-2">
          {profiles.map((p) => (
            <div key={p.id} className="flex items-center gap-3 rounded-lg border border-[var(--border)] p-3">
              <div className="flex h-9 w-9 items-center justify-center rounded bg-[var(--surface-2)]">
                {connIcon(p.connection_type)}
              </div>
              <div className="min-w-0 flex-1">
                <p className="text-sm font-medium">{p.name}</p>
                <p className="text-xs text-[var(--muted)]">
                  {t(`settings.printer.${p.connection_type}`)} · {p.paper_width}mm
                  {p.auto_cut && ` · ${t("settings.printer.auto_cut")}`}
                  {p.open_drawer && ` · ${t("settings.printer.open_drawer")}`}
                </p>
              </div>
              <div className="flex gap-1">
                <Button variant="ghost" size="sm" onClick={() => { setEditing(p); setShowForm(true); }}>
                  <Pencil className="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="sm" className="text-[var(--color-danger)]" onClick={() => setDeleting(p)}>
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>
            </div>
          ))}
        </div>
      )}

      <Button variant="outline" onClick={handleTestPrint} disabled={testing} className="w-full">
        {testing ? t("common.loading") : t("settings.printer.test")}
      </Button>

      <PrinterProfileForm
        open={showForm}
        profile={editing}
        onClose={() => { setShowForm(false); setEditing(null); }}
      />

      <Dialog
        open={!!deleting}
        onClose={() => setDeleting(null)}
        title={t("common.delete")}
        description={t("common.delete") + "?"}
        confirmLabel={t("common.delete")}
        confirmVariant="danger"
        loading={deleteMutation.isPending}
        onConfirm={() => deleting && deleteMutation.mutate(deleting.id)}
      />
    </div>
  );
}

function PrinterProfileForm({
  open,
  profile,
  onClose,
}: {
  open: boolean;
  profile: PrinterProfile | null;
  onClose: () => void;
}) {
  const t = useTranslation();
  const qc = useQueryClient();

  const { register, handleSubmit, watch, reset, formState: { errors } } = useForm<Partial<PrinterProfile>>({
    defaultValues: profile ?? {
      name: "",
      connection_type: "usb",
      paper_width: 80,
      auto_cut: true,
      open_drawer: false,
    },
    values: profile ?? undefined,
  });

  const connType = watch("connection_type");

  const mutation = useMutation({
    mutationFn: (d: Partial<PrinterProfile>) =>
      profile
        ? api(`/printer-profiles/${profile.id}`, { method: "PUT", body: d })
        : api("/printer-profiles", { method: "POST", body: d }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["printer-profiles"] });
      toast(profile ? t("common.save") + "d" : t("common.add") + "d");
      reset();
      onClose();
    },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  return (
    <Dialog open={open} onClose={onClose} title={profile ? t("common.edit") : t("settings.printer.add")}>
      <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-4">
        <div>
          <Label>{t("settings.printer.name")} *</Label>
          <Input {...register("name", { required: true })} />
          {errors.name && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.name.message}</p>}
        </div>
        <div>
          <Label>{t("settings.printer.type")} *</Label>
          <Select {...register("connection_type")}>
            <option value="usb">{t("settings.printer.usb")}</option>
            <option value="network">{t("settings.printer.network")}</option>
            <option value="serial">{t("settings.printer.serial")}</option>
          </Select>
        </div>
        {connType === "network" && (
          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label>IP Address</Label>
              <Input {...register("ip_address")} placeholder="192.168.1.100" />
            </div>
            <div>
              <Label>Port</Label>
              <Input type="number" {...register("port")} placeholder="9100" />
            </div>
          </div>
        )}
        <div>
          <Label>{t("settings.printer.paper_width")} *</Label>
          <Select {...register("paper_width", { valueAsNumber: true })}>
            <option value={58}>58mm</option>
            <option value={80}>80mm</option>
          </Select>
        </div>
        <div className="flex gap-4">
          <label className="flex items-center gap-2">
            <input type="checkbox" {...register("auto_cut")} className="rounded" />
            <span className="text-sm">{t("settings.printer.auto_cut")}</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" {...register("open_drawer")} className="rounded" />
            <span className="text-sm">{t("settings.printer.open_drawer")}</span>
          </label>
        </div>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={onClose}>{t("common.cancel")}</Button>
          <Button type="submit" disabled={mutation.isPending}>{mutation.isPending ? t("common.saving") : t("common.save")}</Button>
        </div>
      </form>
    </Dialog>
  );
}
