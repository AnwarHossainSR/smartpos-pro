"use client";

import { useEffect } from "react";

export interface PosShortcutHandlers {
  focusSearch: () => void;
  holdSale: () => void;
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
}

export function usePosShortcuts(
  handlers: PosShortcutHandlers,
  state: PosShortcutState,
) {
  useEffect(() => {
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
        if (!state.cartEmpty && !state.showPayment && !state.showReceipt) {
          handlers.holdSale();
        }
        return;
      }

      if (e.key === "F6") {
        e.preventDefault();
        if (!state.cartEmpty && !state.showPayment && !state.showReceipt) {
          handlers.openPayment();
        }
        return;
      }

      if (e.key === "F8") {
        e.preventDefault();
        if (!state.cartEmpty && !state.showPayment && !state.showReceipt) {
          handlers.voidLastLine();
        }
        return;
      }

      if (e.key === "Escape") {
        e.preventDefault();
        if (state.showHelp) {
          handlers.toggleHelp();
        } else if (state.showPayment) {
          handlers.closeModal();
        } else if (state.showReceipt) {
          handlers.closeModal();
        } else if (!state.cartEmpty) {
          handlers.clearCart();
        }
        return;
      }

      if (e.key === "Enter" && e.ctrlKey) {
        e.preventDefault();
        if (state.showPayment) {
          handlers.confirmPayment();
        }
        return;
      }

      if (e.key === "`") {
        e.preventDefault();
        handlers.toggleHelp();
        return;
      }

      if (!isTyping && !state.showPayment && !state.showReceipt && !state.showHelp && /^[a-zA-Z0-9]$/.test(e.key)) {
        handlers.focusSearch();
      }
    }

    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [handlers, state]);
}
