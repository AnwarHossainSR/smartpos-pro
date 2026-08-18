"use client";

import { useCallback, useRef } from "react";
import { cn } from "@/lib/utils";
import { Numpad, useIsTouchDevice } from "@/components/ui/numpad";

interface PaymentNumpadProps {
  value: string;
  onChange: (value: string) => void;
}

export function PaymentNumpad({ value, onChange }: PaymentNumpadProps) {
  const isTouch = useIsTouchDevice();
  const bufferRef = useRef(value);

  const handleDigit = useCallback(
    (d: string) => {
      const prev = bufferRef.current;
      if (d === "." && prev.includes(".")) return;
      const next = prev === "0" && d !== "." ? d : prev + d;
      bufferRef.current = next;
      onChange(next);
    },
    [onChange],
  );

  const handleBackspace = useCallback(() => {
    const prev = bufferRef.current;
    const next = prev.length > 1 ? prev.slice(0, -1) : "";
    bufferRef.current = next;
    onChange(next);
  }, [onChange]);

  if (!isTouch) return null;

  return (
    <div className="mt-2" key={value}>
      <Numpad
        onDigit={handleDigit}
        onBackspace={handleBackspace}
        className={cn("max-w-[240px]")}
      />
    </div>
  );
}
