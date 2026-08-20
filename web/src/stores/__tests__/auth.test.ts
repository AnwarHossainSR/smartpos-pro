import { describe, it, expect, beforeEach } from "vitest";
import { hasPermission, useAuth, type AuthUser } from "../auth";

beforeEach(() => {
  useAuth.setState({ token: null, user: null });
});

describe("useAuth", () => {
  it("starts with no token", () => {
    expect(useAuth.getState().token).toBeNull();
  });

  it("setAuth stores token and user", () => {
    useAuth.getState().setAuth("tok123", {
      id: "1",
      name: "Alice",
      email: "alice@example.com",
      roles: ["owner"],
    });
    expect(useAuth.getState().token).toBe("tok123");
    expect(useAuth.getState().user?.name).toBe("Alice");
  });

  it("logout clears state", () => {
    useAuth.getState().setAuth("tok", { id: "1", name: "A", email: "a@b.com" });
    useAuth.getState().logout();
    expect(useAuth.getState().token).toBeNull();
    expect(useAuth.getState().user).toBeNull();
  });
});

describe("can()", () => {
  function signIn(user: Partial<AuthUser>) {
    useAuth.getState().setAuth("t", {
      id: "1",
      name: "Test",
      email: "t@example.com",
      ...user,
    });
  }

  it("denies everything when nobody is signed in", () => {
    expect(useAuth.getState().can("dashboard.view")).toBe(false);
    expect(useAuth.getState().canAny(["dashboard.view"])).toBe(false);
    expect(useAuth.getState().canAll(["dashboard.view"])).toBe(false);
  });

  it("grants everything to a platform super admin, regardless of role or grants", () => {
    signIn({ is_super_admin: true, roles: [], permissions: [] });
    expect(useAuth.getState().can("products.delete")).toBe(true);
    expect(useAuth.getState().can("roles.create")).toBe(true);
    expect(useAuth.getState().can("reorder-suggestions.create")).toBe(true);
  });

  it("grants everything to the tenant owner, even with an empty permission list", () => {
    signIn({ roles: ["owner"], permissions: [] });
    expect(useAuth.getState().can("products.delete")).toBe(true);
    expect(useAuth.getState().can("audit.view")).toBe(true);
  });

  it("does not confuse a role named like the owner", () => {
    signIn({ roles: ["owner_assistant"], permissions: ["sales.view"] });
    expect(useAuth.getState().can("sales.view")).toBe(true);
    expect(useAuth.getState().can("products.delete")).toBe(false);
  });

  it("matches an exact slug and nothing else", () => {
    signIn({ roles: ["cashier"], permissions: ["sales.view", "pos.create"] });
    expect(useAuth.getState().can("sales.view")).toBe(true);
    expect(useAuth.getState().can("pos.create")).toBe(true);
    expect(useAuth.getState().can("sales.refund")).toBe(false);
    expect(useAuth.getState().can("products.delete")).toBe(false);
  });

  it("honours a bare '*' wildcard from the server", () => {
    signIn({ roles: ["admin"], permissions: ["*"] });
    expect(useAuth.getState().can("products.delete")).toBe(true);
    expect(useAuth.getState().can("settings.update")).toBe(true);
  });

  it("honours a per-module '<module>.*' wildcard", () => {
    signIn({ roles: ["stock_clerk"], permissions: ["inventory.*", "products.view"] });
    expect(useAuth.getState().can("inventory.adjust")).toBe(true);
    expect(useAuth.getState().can("inventory.approve")).toBe(true);
    expect(useAuth.getState().can("products.view")).toBe(true);
    expect(useAuth.getState().can("products.delete")).toBe(false);
  });

  it("handles a hyphenated module slug", () => {
    signIn({ roles: ["buyer"], permissions: ["reorder-suggestions.view"] });
    expect(useAuth.getState().can("reorder-suggestions.view")).toBe(true);
    expect(useAuth.getState().can("reorder-suggestions.create")).toBe(false);
  });

  it("treats a missing permissions array as no grants", () => {
    signIn({ roles: ["cashier"] });
    expect(useAuth.getState().can("sales.view")).toBe(false);
  });

  it("canAny is true for at least one grant, and vacuously true for no requirement", () => {
    signIn({ roles: ["cashier"], permissions: ["shifts.view"] });
    expect(useAuth.getState().canAny(["users.view", "roles.view", "shifts.view"])).toBe(true);
    expect(useAuth.getState().canAny(["users.view", "roles.view"])).toBe(false);
    expect(useAuth.getState().canAny([])).toBe(true);
  });

  it("canAll requires every grant", () => {
    signIn({ roles: ["cashier"], permissions: ["sales.view", "pos.create"] });
    expect(useAuth.getState().canAll(["sales.view", "pos.create"])).toBe(true);
    expect(useAuth.getState().canAll(["sales.view", "sales.refund"])).toBe(false);
    expect(useAuth.getState().canAll([])).toBe(true);
  });
});

describe("hasPermission()", () => {
  it("works on a bare user object without touching the store", () => {
    expect(hasPermission(null, "dashboard.view")).toBe(false);
    expect(
      hasPermission(
        { id: "1", name: "S", email: "s@e.com", is_super_admin: true },
        "anything.at-all",
      ),
    ).toBe(true);
    expect(
      hasPermission(
        { id: "1", name: "C", email: "c@e.com", permissions: ["sales.view"] },
        "sales.view",
      ),
    ).toBe(true);
  });

  it("rejects a malformed slug rather than throwing", () => {
    const user: AuthUser = { id: "1", name: "C", email: "c@e.com", permissions: ["sales.view"] };
    expect(hasPermission(user, "sales")).toBe(false);
    expect(hasPermission(user, "")).toBe(false);
    expect(hasPermission(user, ".view")).toBe(false);
  });
});
