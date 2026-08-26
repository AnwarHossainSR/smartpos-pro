import { create } from "zustand";

interface ModuleState {
  modules: Record<string, boolean>;
  loaded: boolean;
  setModules: (modules: Record<string, boolean>) => void;
  isEnabled: (module: string) => boolean;
}

export const useModulesStore = create<ModuleState>((set, get) => ({
  modules: {},
  loaded: false,
  setModules: (modules) => set({ modules, loaded: true }),
  isEnabled: (module) => get().modules[module] ?? false,
}));
