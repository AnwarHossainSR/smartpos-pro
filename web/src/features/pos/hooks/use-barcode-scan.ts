import { useCallback, useRef } from "react";
import { toast } from "@/components/ui/toast";
import { posApi } from "@/features/pos/api";
import type { Product } from "@/features/pos/types";

/** A hardware scanner "types" far faster than a human; anything under this is machine input. */
const SCANNER_KEY_GAP_MS = 30;
/** Below this many machine-speed characters, treat it as typing rather than a scan. */
const MIN_SCAN_LENGTH = 3;

/** Best-effort haptic/audio confirmation — absent or blocked on most desktop tills. */
function buzz(pattern: number | number[]) {
  try { navigator.vibrate?.(pattern); } catch {}
}

function beep() {
  try {
    const ctx = new AudioContext();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.frequency.value = 1200;
    gain.gain.value = 0.1;
    osc.start();
    osc.stop(ctx.currentTime + 0.08);
  } catch {}
}

/**
 * Barcode scanning through the search box. A USB scanner is a keyboard: it emits the code
 * a character at a time and finishes with Enter, so the keystroke gaps are what separate a
 * scan from a cashier typing a product name. Enter first tries an exact barcode match and
 * only then falls back to whatever is in the box, which is the live text search.
 */
export function useBarcodeScan(onFound: (product: Product, weight?: number | null) => void) {
  const lastKeystrokeRef = useRef(0);
  const bufferRef = useRef("");

  const lookup = useCallback(async (code: string) => {
    if (!code.trim()) return;
    try {
      const found = await posApi.barcode(code.trim());
      if (found?.data) {
        onFound(found.data, found.parsed_weight);
        buzz(50);
        beep();
      }
    } catch {
      toast(`Barcode "${code}" not found`, "error");
      buzz([50, 30, 50]);
    }
  }, [onFound]);

  const handleSearchKeyDown = useCallback(
    (e: React.KeyboardEvent<HTMLInputElement>) => {
      const now = Date.now();

      if (e.key === "Enter") {
        const gap = now - lastKeystrokeRef.current;
        lastKeystrokeRef.current = now;
        const scanned = bufferRef.current;
        bufferRef.current = "";
        lookup(gap < SCANNER_KEY_GAP_MS && scanned.length >= MIN_SCAN_LENGTH ? scanned : e.currentTarget.value);
        return;
      }

      if (e.key.length === 1) {
        const gap = now - lastKeystrokeRef.current;
        lastKeystrokeRef.current = now;
        bufferRef.current = gap < SCANNER_KEY_GAP_MS ? bufferRef.current + e.key : e.key;
      }
    },
    [lookup],
  );

  return { handleSearchKeyDown, lookup };
}
