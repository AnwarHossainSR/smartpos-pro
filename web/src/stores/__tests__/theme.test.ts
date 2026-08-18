import { describe, it, expect, beforeEach } from "vitest";
import { useThemeStore, THEME_STORAGE_KEY, type ServerTheme } from "../theme";

beforeEach(() => {
  useThemeStore.setState({ theme: "system", palette: "indigo", dirty: false });
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

  it("defaults to the indigo palette", () => {
    expect(useThemeStore.getState().palette).toBe("indigo");
  });

  it("can switch palette", () => {
    useThemeStore.getState().setPalette("emerald");
    expect(useThemeStore.getState().palette).toBe("emerald");
  });
});

describe("useThemeStore server sync", () => {
  it("marks a local change as needing a push", () => {
    expect(useThemeStore.getState().dirty).toBe(false);

    useThemeStore.getState().setPalette("rose");
    expect(useThemeStore.getState().dirty).toBe(true);

    useThemeStore.getState().markPushed();
    expect(useThemeStore.getState().dirty).toBe(false);
  });

  it("adopts the theme stored on the account without marking it dirty", () => {
    useThemeStore.getState().applyServerTheme({ mode: "dark", palette: "ocean" });

    expect(useThemeStore.getState().theme).toBe("dark");
    expect(useThemeStore.getState().palette).toBe("ocean");
    expect(useThemeStore.getState().dirty).toBe(false);
  });

  it("keeps the local preference and pushes it when the account has none", () => {
    useThemeStore.setState({ theme: "light", palette: "violet", dirty: false });

    useThemeStore.getState().applyServerTheme(null);

    expect(useThemeStore.getState().theme).toBe("light");
    expect(useThemeStore.getState().palette).toBe("violet");
    expect(useThemeStore.getState().dirty).toBe(true);
  });

  it("ignores an unknown mode or palette from the API", () => {
    useThemeStore.setState({ theme: "light", palette: "violet", dirty: false });

    useThemeStore
      .getState()
      .applyServerTheme({ mode: "midnight", palette: "chartreuse" } as unknown as ServerTheme);

    expect(useThemeStore.getState().theme).toBe("light");
    expect(useThemeStore.getState().palette).toBe("violet");
  });

  it("does not persist the dirty flag", () => {
    useThemeStore.getState().setPalette("rose");

    const stored = JSON.parse(localStorage.getItem(THEME_STORAGE_KEY) ?? "{}");
    expect(stored.state).toEqual({ theme: "system", palette: "rose" });
  });
});
