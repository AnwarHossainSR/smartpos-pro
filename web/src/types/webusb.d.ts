/**
 * Minimal WebUSB surface used by `src/lib/escpos-printer.ts`.
 *
 * WebUSB is not in TypeScript's DOM lib (it is a separate `@types/w3c-web-usb` package), and
 * the receipt printer path only touches a handful of members — declaring just those keeps the
 * printer code type-checked without pulling in another dependency for one file.
 */

interface USBEndpoint {
  endpointNumber: number;
  direction: "in" | "out";
}

interface USBAlternateInterface {
  endpoints: USBEndpoint[];
}

interface USBInterface {
  interfaceNumber: number;
  alternate: USBAlternateInterface;
}

interface USBConfiguration {
  interfaces: USBInterface[];
}

interface USBDevice {
  readonly productName?: string;
  readonly manufacturerName?: string;
  configuration: USBConfiguration | null;
  open(): Promise<void>;
  close(): Promise<void>;
  selectConfiguration(configurationValue: number): Promise<void>;
  claimInterface(interfaceNumber: number): Promise<void>;
  transferOut(endpointNumber: number, data: BufferSource): Promise<unknown>;
}

interface USBDeviceRequestOptions {
  filters: { vendorId?: number; productId?: number }[];
}

interface USB {
  requestDevice(options: USBDeviceRequestOptions): Promise<USBDevice>;
  getDevices(): Promise<USBDevice[]>;
}

interface Navigator {
  readonly usb?: USB;
}
