import { EscPos, formatReceipt } from "./escpos";

interface UsbPrinter {
  device: USBDevice;
  endpointNumber: number;
}

let connectedPrinter: UsbPrinter | null = null;

const EPSON_VENDOR_ID = 0x04b8;

export async function connectUsbPrinter(): Promise<UsbPrinter | null> {
  if (typeof navigator === "undefined" || !navigator.usb) return null;

  try {
    const device = await navigator.usb.requestDevice({
      filters: [{ vendorId: EPSON_VENDOR_ID }],
    });

    await device.open();

    if (device.configuration === null) {
      await device.selectConfiguration(1);
    }

    const iface = device.configuration!.interfaces[0];
    await device.claimInterface(iface.interfaceNumber);

    const endpoint = iface.alternate.endpoints.find((e) => e.direction === "out");

    if (!endpoint) {
      await device.close();
      return null;
    }

    connectedPrinter = {
      device,
      endpointNumber: endpoint.endpointNumber,
    };

    return connectedPrinter;
  } catch {
    return null;
  }
}

export function getConnectedPrinter(): UsbPrinter | null {
  return connectedPrinter;
}

export function disconnectPrinter(): void {
  if (connectedPrinter) {
    connectedPrinter.device.close().catch(() => {
      /* ignore */
    });
    connectedPrinter = null;
  }
}

export async function print(data: Uint8Array): Promise<boolean> {
  if (!connectedPrinter) return false;

  try {
    const chunkSize = 64;
    for (let i = 0; i < data.length; i += chunkSize) {
      const chunk = data.slice(i, i + chunkSize);
      await connectedPrinter.device.transferOut(connectedPrinter.endpointNumber, chunk);
    }
    return true;
  } catch {
    disconnectPrinter();
    return false;
  }
}

export async function openDrawer(): Promise<boolean> {
  if (!connectedPrinter) return false;

  const drawer = new EscPos().drawer().byteArray();
  return print(drawer);
}

export async function printReceipt(
  receiptData: Uint8Array,
): Promise<{ printed: boolean; fallback: boolean }> {
  if (connectedPrinter) {
    const ok = await print(receiptData);
    if (ok) return { printed: true, fallback: false };
  }
  return { printed: false, fallback: true };
}

export function isWebUsbAvailable(): boolean {
  return typeof navigator !== "undefined" && !!navigator.usb;
}

export function downloadPdfFallback(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

export type { UsbPrinter };
export { formatReceipt };
