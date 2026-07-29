"use client";

import { useEffect } from "react";
import { useThemeStore } from "@/stores/theme";

export function ThemeSync() {
  const theme = useThemeStore((s) => s.theme);
  const palette = useThemeStore((s) => s.palette);

  useEffect(() => {
    const root = document.documentElement;
    root.classList.remove("light", "dark");
    if (theme === "light") root.classList.add("light");
    else if (theme === "dark") root.classList.add("dark");
  }, [theme]);

  useEffect(() => {
    document.documentElement.setAttribute("data-theme", palette);
  }, [palette]);

  return null;
}
