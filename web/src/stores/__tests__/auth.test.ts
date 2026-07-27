import { describe, it, expect, beforeEach } from "vitest";
import { useAuth } from "../auth";

beforeEach(() => {
  useAuth.setState({ token: null, user: null });
});

describe("useAuth", () => {
  it("starts with no token", () => {
    expect(useAuth.getState().token).toBeNull();
  });

  it("setAuth stores token and user", () => {
    useAuth.getState().setAuth("tok123", {
      id: 1,
      name: "Alice",
      email: "alice@example.com",
      roles: ["owner"],
    });
    expect(useAuth.getState().token).toBe("tok123");
    expect(useAuth.getState().user?.name).toBe("Alice");
  });

  it("owner can do anything", () => {
    useAuth.getState().setAuth("t", { id: 1, name: "Owner", email: "o@e.com", roles: ["owner"] });
    expect(useAuth.getState().can("products.delete")).toBe(true);
    expect(useAuth.getState().can("team.create")).toBe(true);
  });

  it("user with specific permission", () => {
    useAuth.getState().setAuth("t", {
      id: 2,
      name: "Staff",
      email: "s@e.com",
      roles: ["cashier"],
      permissions: ["sales.view", "pos.checkout"],
    });
    expect(useAuth.getState().can("sales.view")).toBe(true);
    expect(useAuth.getState().can("products.delete")).toBe(false);
  });

  it("logout clears state", () => {
    useAuth.getState().setAuth("tok", { id: 1, name: "A", email: "a@b.com" });
    useAuth.getState().logout();
    expect(useAuth.getState().token).toBeNull();
    expect(useAuth.getState().user).toBeNull();
  });
});
