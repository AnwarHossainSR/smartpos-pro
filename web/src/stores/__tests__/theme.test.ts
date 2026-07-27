import { describe, it, expect, beforeEach } from "vitest";
import { useThemeStore } from "../theme";

beforeEach(() => {
  useThemeStore.setState({ theme: "system" });
});

describe("useThemeStore", () => {
  it("defaults to system", () => {
    expect(useThemeStore.getState().theme).toBe("system");
  });

  it("can switch to dark", () => {
    useThemeStore.getState().setTheme("dark");
    expect(useThemeStore.getState().theme).toBe("dark");
  });

  it("can switch to light", () => {
    useThemeStore.getState().setTheme("light");
    expect(useThemeStore.getState().theme).toBe("light");
  });
});
