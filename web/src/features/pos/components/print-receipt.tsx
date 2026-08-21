import { useState, useCallback } from "react";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { toast } from "@/components/ui/toast";
import { useTranslation } from "@/i18n/context";
import { api, apiBlob } from "@/lib/api";
import {
  connectUsbPrinter,
  getConnectedPrinter,
  disconnectPrinter,
  printReceipt,
  isWebUsbAvailable,
  downloadPdfFallback,
  formatReceipt,
} from "@/lib/escpos-printer";
import { Printer, Download, Usb, X } from "lucide-react";

interface PrintReceiptProps {
  saleId: string;
  invoiceNumber?: string;
  saleData?: {
    id: string;
    invoice_number?: string;
    created_at: string;
    items: Array<{
      name: string;
      quantity: number;
      price: number;
      total: number;
    }>;
    subtotal: number;
    tax_amount: number;
    discount_amount: number;
    total: number;
    payments: Array<{ method: string; amount: number }>;
    change_amount?: number;
    customer_name?: string;
  };
  onSuccess?: () => void;
}

export function PrintReceipt({
  saleId,
  invoiceNumber,
  saleData,
  onSuccess,
}: PrintReceiptProps) {
  const t = useTranslation();
  const [showDialog, setShowDialog] = useState(false);
  const [connecting, setConnecting] = useState(false);
  const [printing, setPrinting] = useState(false);

  const hasUsb = isWebUsbAvailable();
  const printer = getConnectedPrinter();

  const handleConnect = useCallback(async () => {
    setConnecting(true);
    try {
      const result = await connectUsbPrinter();
      if (result) {
        toast(t("pos.printerConnected"));
      } else {
        toast(t("pos.printerNotFound"), "error");
      }
    } catch {
      toast(t("pos.printerError"), "error");
    } finally {
      setConnecting(false);
    }
  }, [t]);

  const handleDisconnect = useCallback(() => {
    disconnectPrinter();
    toast(t("pos.printerDisconnected"));
  }, [t]);

  const handlePrint = useCallback(async () => {
    if (!saleData) {
      toast(t("pos.printNoData"), "error");
      return;
    }

    setPrinting(true);
    try {
      const { data: business } = await api<{
        data: { name: string; address?: string; phone?: string; tax_number?: string };
      }>("/settings/business");

      const receiptBytes = formatReceipt(
        { ...saleData, invoice_number: invoiceNumber },
        business,
      );

      const result = await printReceipt(receiptBytes);

      if (result.printed) {
        toast(t("pos.printSuccess"));
        setShowDialog(false);
        onSuccess?.();
      } else if (result.fallback) {
        const blob = await apiBlob(`/sales/${saleId}/invoice`);
        downloadPdfFallback(blob, `receipt-${invoiceNumber ?? saleId}.pdf`);
        toast(t("pos.printFallback"));
        setShowDialog(false);
        onSuccess?.();
      }
    } catch {
      toast(t("pos.printError"), "error");
    } finally {
      setPrinting(false);
    }
  }, [saleData, saleId, invoiceNumber, t, onSuccess]);

  const handleDownloadPdf = useCallback(async () => {
    try {
      const blob = await apiBlob(`/sales/${saleId}/invoice`);
      downloadPdfFallback(blob, `receipt-${invoiceNumber ?? saleId}.pdf`);
      toast(t("pos.printFallback"));
      setShowDialog(false);
      onSuccess?.();
    } catch {
      toast(t("pos.printError"), "error");
    }
  }, [saleId, invoiceNumber, t, onSuccess]);

  return (
    <>
      <Button
        variant="outline"
        onClick={() => setShowDialog(true)}
        className="gap-2"
      >
        <Printer className="h-4 w-4" />
        {t("pos.printReceipt")}
      </Button>

      <Dialog
        open={showDialog}
        onClose={() => setShowDialog(false)}
        title={t("pos.printReceipt")}
      >
        <div className="space-y-4">
          {!hasUsb ? (
            <div className="rounded-lg border border-[var(--border)] p-4 text-center">
              <Download className="mx-auto mb-2 h-8 w-8 text-[var(--muted)]" />
              <p className="text-sm text-[var(--muted)]">
                {t("pos.printNoUsb")}
              </p>
            </div>
          ) : !printer ? (
            <div className="space-y-3">
              <div className="rounded-lg border border-[var(--border)] p-4 text-center">
                <Usb className="mx-auto mb-2 h-8 w-8 text-[var(--muted)]" />
                <p className="text-sm text-[var(--muted)]">
                  {t("pos.printConnect")}
                </p>
              </div>
              <Button
                onClick={handleConnect}
                disabled={connecting}
                className="w-full"
              >
                {connecting ? t("common.loading") : t("pos.printConnect")}
              </Button>
            </div>
          ) : (
            <div className="space-y-3">
              <div className="flex items-center gap-3 rounded-lg border border-[var(--border)] p-3">
                <Printer className="h-5 w-5 text-[var(--primary)]" />
                <div className="flex-1">
                  <p className="text-sm font-medium">
                    {printer.device.productName ?? "USB Printer"}
                  </p>
                  <p className="text-xs text-[var(--muted)]">Connected</p>
                </div>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={handleDisconnect}
                >
                  <X className="h-4 w-4" />
                </Button>
              </div>
              <Button
                onClick={handlePrint}
                disabled={printing || !saleData}
                className="w-full"
              >
                {printing ? t("common.loading") : t("pos.printReceipt")}
              </Button>
            </div>
          )}

          <Button
            variant="outline"
            onClick={handleDownloadPdf}
            className="w-full"
          >
            <Download className="mr-2 h-4 w-4" />
            {t("pos.printFallback")}
          </Button>
        </div>
      </Dialog>
    </>
  );
}
