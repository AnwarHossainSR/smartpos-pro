import { create } from "zustand";
import { api } from "@/lib/api";

interface ModuleState {
  modules: Record<string, boolean>;
  loaded: boolean;
  setModules: (modules: Record<string, boolean>) => void;
  fetchModules: () => Promise<void>;
  isEnabled: (module: string) => boolean;
}

export const useModulesStore = create<ModuleState>((set, get) => ({
  modules: {},
  loaded: false,
  setModules: (modules) => set({ modules, loaded: true }),
  fetchModules: async () => {
    try {
      const { data } = await api<{ data: Record<string, boolean> }>("/modules");
      set({ modules: data, loaded: true });
    } catch {
      set({ loaded: true });
    }
  },
  isEnabled: (module) => get().modules[module] ?? false,
}));
