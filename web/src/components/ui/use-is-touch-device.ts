/**
 * Lives apart from `numpad.tsx` because a module that exports a component may not also
 * export a hook — Vite re-executes such a file on every edit and Fast Refresh breaks.
 */
export function useIsTouchDevice() {
  if (typeof window === "undefined") return false;
  return window.matchMedia("(pointer: coarse)").matches;
}
