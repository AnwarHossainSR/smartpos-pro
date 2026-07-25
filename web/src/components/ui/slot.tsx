import { cloneElement, isValidElement } from "react";

type SlotProps = {
  children?: React.ReactNode;
} & Record<string, unknown>;

/** Minimal Slot: merges props onto a single child element (asChild pattern). */
export function Slot({ children, ...props }: SlotProps) {
  if (isValidElement(children)) {
    const child = children as React.ReactElement<Record<string, unknown>>;
    return cloneElement(child, { ...props, ...child.props });
  }
  return null;
}
