import { beforeEach, describe, expect, it, vi } from "vitest";
import { exportRecoveryCodesAsCsv, exportRecoveryCodesAsPdf } from "../recovery-code-export";

describe("exportRecoveryCodesAsCsv", () => {
  let clickSpy: ReturnType<typeof vi.fn>;
  let createObjectURLSpy: ReturnType<typeof vi.fn>;
  let revokeObjectURLSpy: ReturnType<typeof vi.fn>;
  let createElementSpy: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    clickSpy = vi.fn();
    createObjectURLSpy = vi.fn(() => "blob:mock-url");
    revokeObjectURLSpy = vi.fn();
    createElementSpy = vi.fn(() => ({ click: clickSpy }));

    vi.stubGlobal("URL", {
      createObjectURL: createObjectURLSpy,
      revokeObjectURL: revokeObjectURLSpy,
    });
    vi.stubGlobal("document", { createElement: createElementSpy });
  });

  it("creates a CSV with header and one code per row", () => {
    exportRecoveryCodesAsCsv(["ABCD-1234", "EFGH-5678"]);

    expect(createObjectURLSpy).toHaveBeenCalledOnce();
    const blob = createObjectURLSpy.mock.calls[0][0] as Blob;
    expect(blob.type).toContain("text/csv");
  });

  it("triggers a download via anchor click", () => {
    exportRecoveryCodesAsCsv(["CODE-1"]);

    expect(createElementSpy).toHaveBeenCalledWith("a");
    const anchor = createElementSpy.mock.results[0].value;
    expect(anchor.href).toBe("blob:mock-url");
    expect(anchor.download).toBe("recovery-codes.csv");
    expect(clickSpy).toHaveBeenCalledOnce();
  });

  it("revokes the object URL after download", () => {
    exportRecoveryCodesAsCsv(["CODE-1"]);
    expect(revokeObjectURLSpy).toHaveBeenCalledWith("blob:mock-url");
  });
});

describe("exportRecoveryCodesAsPdf", () => {
  let printSpy: ReturnType<typeof vi.fn>;
  let closeSpy: ReturnType<typeof vi.fn>;
  let writeSpy: ReturnType<typeof vi.fn>;
  let openSpy: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    printSpy = vi.fn();
    closeSpy = vi.fn();
    writeSpy = vi.fn();
    openSpy = vi.fn(() => ({
      document: { write: writeSpy, close: vi.fn() },
      focus: vi.fn(),
      print: printSpy,
      close: closeSpy,
    }));

    vi.stubGlobal("window", { open: openSpy });
    vi.useFakeTimers();
  });

  it("opens a new window with print page", () => {
    exportRecoveryCodesAsPdf(["AAAA-1111", "BBBB-2222"]);

    expect(openSpy).toHaveBeenCalledWith("", "_blank");
    expect(writeSpy).toHaveBeenCalledOnce();
  });

  it("includes all codes in the printable document", () => {
    exportRecoveryCodesAsPdf(["AAAA-1111", "BBBB-2222"]);

    const html = writeSpy.mock.calls[0][0] as string;
    expect(html).toContain("AAAA-1111");
    expect(html).toContain("BBBB-2222");
  });

  it("includes SmartPOS heading and warning text", () => {
    exportRecoveryCodesAsPdf(["CODE-1"]);

    const html = writeSpy.mock.calls[0][0] as string;
    expect(html).toContain("SmartPOS Recovery Codes");
    expect(html).toContain("Each code can be used once");
  });

  it("includes export timestamp", () => {
    exportRecoveryCodesAsPdf(["CODE-1"]);

    const html = writeSpy.mock.calls[0][0] as string;
    expect(html).toContain("Exported:");
  });

  it("invokes browser print dialog", () => {
    exportRecoveryCodesAsPdf(["CODE-1"]);

    expect(printSpy).toHaveBeenCalledOnce();
  });

  it("closes the print window after delay", () => {
    exportRecoveryCodesAsPdf(["CODE-1"]);

    vi.advanceTimersByTime(1000);
    expect(closeSpy).toHaveBeenCalledOnce();
  });
});
