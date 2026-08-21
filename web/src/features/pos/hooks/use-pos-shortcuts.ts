import { useEffect } from "react";

export interface PosShortcutHandlers {
  focusSearch: () => void;
  /** F4 — parks the basket as a held sale. */
  parkSale: () => void;
  openPayment: () => void;
  voidLastLine: () => void;
  closeModal: () => void;
  clearCart: () => void;
  confirmPayment: () => void;
  toggleHelp: () => void;
}

export interface PosShortcutState {
  cartEmpty: boolean;
  showPayment: boolean;
  showReceipt: boolean;
  showHelp: boolean;
  showHeld: boolean;
}

/**
 * The till's keyboard, in one listener.
 *
 * There were two competing `keydown` listeners on the POS screen — this hook and a second
 * effect inside the page — which is why F4 both parked the sale *and* toasted a
 * "coming soon" placeholder. F9 comes from that second listener and is kept as an alias for
 * F6 so muscle memory built on either key still opens payment.
 *
 * Every shortcut is inert while a modal owns the screen, so a cashier typing into the
 * payment or held-orders dialog can never park or void the basket behind it.
 */
export function usePosShortcuts(
  handlers: PosShortcutHandlers,
  state: PosShortcutState,
) {
  useEffect(() => {
    const modalOpen = state.showPayment || state.showReceipt || state.showHeld;

    function onKeyDown(e: KeyboardEvent) {
      const active = document.activeElement;
      const isTyping = active instanceof HTMLElement && ["INPUT", "TEXTAREA", "SELECT"].includes(active.tagName);

      if (e.key === "F2") {
        e.preventDefault();
        handlers.focusSearch();
        return;
      }

      if (e.key === "F4") {
        e.preventDefault();
        if (!state.cartEmpty && !modalOpen) handlers.parkSale();
        return;
      }

      if (e.key === "F6" || e.key === "F9") {
        e.preventDefault();
        if (!state.cartEmpty && !state.showPayment && !state.showReceipt) handlers.openPayment();
        return;
      }

      if (e.key === "F8") {
        e.preventDefault();
        if (!state.cartEmpty && !state.showPayment && !state.showReceipt) handlers.voidLastLine();
        return;
      }

      if (e.key === "Escape") {
        e.preventDefault();
        if (state.showHelp) {
          handlers.toggleHelp();
        } else if (state.showPayment || state.showReceipt) {
          handlers.closeModal();
        } else if (!state.cartEmpty) {
          handlers.clearCart();
        }
        return;
      }

      if (e.key === "Enter" && e.ctrlKey) {
        e.preventDefault();
        if (state.showPayment) handlers.confirmPayment();
        return;
      }

      if (e.key === "`") {
        e.preventDefault();
        handlers.toggleHelp();
        return;
      }

      // Typing anywhere on the screen goes to the search box — a scanner fires into
      // whatever has focus, and at the till that must always be the product search.
      if (!isTyping && !modalOpen && !state.showHelp && /^[a-zA-Z0-9]$/.test(e.key)) {
        handlers.focusSearch();
      }
    }

    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [handlers, state]);
}
