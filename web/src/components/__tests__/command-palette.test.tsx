import { describe, it, expect, vi } from "vitest";
import { render, screen, fireEvent } from "@testing-library/react";
import { CommandPalette } from "../ui/command-palette";

// next/navigation mock
vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn() }),
}));

describe("CommandPalette", () => {
  it("does not render when closed", () => {
    const { container } = render(<CommandPalette open={false} onClose={vi.fn()} />);
    expect(container).toBeEmptyDOMElement();
  });

  it("renders search input when open", () => {
    render(<CommandPalette open={true} onClose={vi.fn()} />);
    expect(screen.getByPlaceholderText(/search pages/i)).toBeInTheDocument();
  });

  it("shows all nav items when query is empty", () => {
    render(<CommandPalette open={true} onClose={vi.fn()} />);
    expect(screen.getByText("Dashboard")).toBeInTheDocument();
    expect(screen.getByText("POS Terminal")).toBeInTheDocument();
  });

  it("filters items by query", () => {
    render(<CommandPalette open={true} onClose={vi.fn()} />);
    const input = screen.getByPlaceholderText(/search pages/i);
    fireEvent.change(input, { target: { value: "account" } });
    expect(screen.getByText("Accounting")).toBeInTheDocument();
    expect(screen.queryByText("Dashboard")).not.toBeInTheDocument();
  });

  it("calls onClose when backdrop is clicked", () => {
    const onClose = vi.fn();
    render(<CommandPalette open={true} onClose={onClose} />);
    // Backdrop is the first fixed div's inner div
    const backdrop = document.querySelector(".absolute.inset-0.bg-black\\/40");
    if (backdrop) fireEvent.click(backdrop);
    expect(onClose).toHaveBeenCalled();
  });

  it("calls onClose on Escape key", () => {
    const onClose = vi.fn();
    render(<CommandPalette open={true} onClose={onClose} />);
    fireEvent.keyDown(window, { key: "Escape" });
    expect(onClose).toHaveBeenCalled();
  });
});
