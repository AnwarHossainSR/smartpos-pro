import { fireEvent, render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { useAuth } from "@/stores/auth";
import { CommandPalette } from "../ui/command-palette";

// The palette hides destinations the route guard would refuse, so these tests need a signed-in
// user. The owner role implicitly holds everything, which keeps the assertions about *filtering*
// independent of the permission matrix.
beforeEach(() => {
  useAuth.setState({
    token: "test-token",
    hydrated: true,
    user: { id: "1", name: "Owner", email: "owner@example.com", roles: ["owner"] },
  });
});

afterEach(() => {
  useAuth.setState({ token: null, user: null, hydrated: false });
});

describe("CommandPalette", () => {
  it("does not render when closed", () => {
    const { container } = render(
      <MemoryRouter>
        <CommandPalette open={false} onClose={vi.fn()} />
      </MemoryRouter>,
    );
    expect(container).toBeEmptyDOMElement();
  });

  it("renders search input when open", () => {
    render(
      <MemoryRouter>
        <CommandPalette open={true} onClose={vi.fn()} />
      </MemoryRouter>,
    );
    expect(screen.getByPlaceholderText(/search pages/i)).toBeInTheDocument();
  });

  it("shows all nav items when query is empty", () => {
    render(
      <MemoryRouter>
        <CommandPalette open={true} onClose={vi.fn()} />
      </MemoryRouter>,
    );
    expect(screen.getByText("Dashboard")).toBeInTheDocument();
    expect(screen.getByText("POS Terminal")).toBeInTheDocument();
  });

  it("filters items by query", () => {
    render(
      <MemoryRouter>
        <CommandPalette open={true} onClose={vi.fn()} />
      </MemoryRouter>,
    );
    const input = screen.getByPlaceholderText(/search pages/i);
    fireEvent.change(input, { target: { value: "account" } });
    expect(screen.getByText("Accounting")).toBeInTheDocument();
    expect(screen.queryByText("Dashboard")).not.toBeInTheDocument();
  });

  it("calls onClose when backdrop is clicked", () => {
    const onClose = vi.fn();
    render(
      <MemoryRouter>
        <CommandPalette open={true} onClose={onClose} />
      </MemoryRouter>,
    );
    // Backdrop is the first fixed div's inner div
    const backdrop = document.querySelector(".absolute.inset-0.bg-black\\/40");
    if (backdrop) fireEvent.click(backdrop);
    expect(onClose).toHaveBeenCalled();
  });

  it("hides destinations the user has no permission for", () => {
    useAuth.setState({
      user: {
        id: "2",
        name: "Cashier",
        email: "cashier@example.com",
        roles: ["cashier"],
        permissions: ["dashboard.view", "pos.view", "sales.view"],
      },
    });

    render(
      <MemoryRouter>
        <CommandPalette open={true} onClose={vi.fn()} />
      </MemoryRouter>,
    );
    expect(screen.getByText("Dashboard")).toBeInTheDocument();
    expect(screen.getByText("POS Terminal")).toBeInTheDocument();
    expect(screen.queryByText("Accounting")).not.toBeInTheDocument();
    expect(screen.queryByText("Audit log")).not.toBeInTheDocument();
  });

  it("calls onClose on Escape key", () => {
    const onClose = vi.fn();
    render(
      <MemoryRouter>
        <CommandPalette open={true} onClose={onClose} />
      </MemoryRouter>,
    );
    fireEvent.keyDown(window, { key: "Escape" });
    expect(onClose).toHaveBeenCalled();
  });
});
