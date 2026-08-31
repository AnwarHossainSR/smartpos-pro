import { beforeEach, describe, expect, it } from "vitest";
import { type ServerTheme, THEME_STORAGE_KEY, useThemeStore } from "../theme";

beforeEach(() => {
  useThemeStore.setState({
    theme: "system",
    palette: "amber",
    modeDirty: false,
    paletteDirty: false,
  });
});

describe("useThemeStore", () => {
  it("defaults to system mode", () => {
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

  it("defaults to the amber palette", () => {
    expect(useThemeStore.getState().palette).toBe("amber");
  });

  it("can switch palette", () => {
    useThemeStore.getState().setPalette("emerald");
    expect(useThemeStore.getState().palette).toBe("emerald");
  });
});

describe("useThemeStore server sync", () => {
  it("marks a local mode change as needing a push", () => {
    expect(useThemeStore.getState().modeDirty).toBe(false);

    useThemeStore.getState().setTheme("dark");
    expect(useThemeStore.getState().modeDirty).toBe(true);

    useThemeStore.getState().markModePushed();
    expect(useThemeStore.getState().modeDirty).toBe(false);
  });

  it("marks a local palette change as needing a push", () => {
    expect(useThemeStore.getState().paletteDirty).toBe(false);

    useThemeStore.getState().setPalette("rose");
    expect(useThemeStore.getState().paletteDirty).toBe(true);

    useThemeStore.getState().markPalettePushed();
    expect(useThemeStore.getState().paletteDirty).toBe(false);
  });

  it("adopts the mode stored on the account without marking it dirty", () => {
    useThemeStore.getState().applyServerTheme({ mode: "dark" } as ServerTheme);

    expect(useThemeStore.getState().theme).toBe("dark");
    expect(useThemeStore.getState().modeDirty).toBe(false);
  });

  it("keeps the local mode and pushes it when the account has none", () => {
    useThemeStore.setState({ theme: "light", modeDirty: false });

    useThemeStore.getState().applyServerTheme(null);

    expect(useThemeStore.getState().theme).toBe("light");
    expect(useThemeStore.getState().modeDirty).toBe(true);
  });

  it("adopts the tenant palette without marking it dirty", () => {
    useThemeStore.getState().applyPalette("ocean");

    expect(useThemeStore.getState().palette).toBe("ocean");
    expect(useThemeStore.getState().paletteDirty).toBe(false);
  });

  it("keeps a local palette and pushes it when the tenant has none", () => {
    useThemeStore.setState({ palette: "violet", paletteDirty: false });

    useThemeStore.getState().applyPalette(null);

    expect(useThemeStore.getState().palette).toBe("violet");
    expect(useThemeStore.getState().paletteDirty).toBe(true);
  });

  it("ignores an unknown mode from the API", () => {
    useThemeStore.setState({ theme: "light", modeDirty: false });

    useThemeStore.getState().applyServerTheme({ mode: "midnight" } as unknown as ServerTheme);

    expect(useThemeStore.getState().theme).toBe("light");
    expect(useThemeStore.getState().modeDirty).toBe(true);
  });

  it("ignores an unknown palette from the API", () => {
    useThemeStore.setState({ palette: "violet", paletteDirty: false });

    useThemeStore.getState().applyPalette("chartreuse" as never);

    expect(useThemeStore.getState().palette).toBe("violet");
    expect(useThemeStore.getState().paletteDirty).toBe(true);
  });

  it("does not persist the dirty flags", () => {
    useThemeStore.getState().setPalette("rose");

    const stored = JSON.parse(localStorage.getItem(THEME_STORAGE_KEY) ?? "{}");
    expect(stored.state).toEqual({ theme: "system", palette: "rose" });
  });
});
