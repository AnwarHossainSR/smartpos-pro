import js from "@eslint/js";
import globals from "globals";
import tseslint from "typescript-eslint";
import reactHooks from "eslint-plugin-react-hooks";
import reactRefresh from "eslint-plugin-react-refresh";

export default tseslint.config(
  js.configs.recommended,
  ...tseslint.configs.recommended,
  {
    languageOptions: {
      globals: { ...globals.browser, ...globals.es2024 },
    },
    plugins: {
      "react-hooks": reactHooks,
      "react-refresh": reactRefresh,
    },
    rules: {
      ...reactHooks.configs.recommended.rules,
      // A file that exports a component *and* a hook/constant cannot be Fast-Refreshed:
      // Vite re-executes it on edit, which re-creates module-level values such as a
      // React context — and every mounted consumer then reads the stale one. That is a
      // real runtime failure in dev, not a style nit, so it is an error here.
      "react-refresh/only-export-components": ["error", { allowConstantExport: true }],
      // `try { … } catch {}` is the intended shape for genuinely optional side effects
      // (navigator.vibrate, AudioContext, a best-effort logout call).
      "no-empty": ["error", { allowEmptyCatch: true }],
      "@typescript-eslint/no-unused-vars": [
        "error",
        { argsIgnorePattern: "^_", varsIgnorePattern: "^_", caughtErrorsIgnorePattern: "^_" },
      ],
    },
  },
  {
    // The service worker runs in a worker global scope, not the DOM one.
    files: ["src/sw.ts"],
    languageOptions: { globals: globals.serviceworker },
  },
  {
    files: ["**/*.{test,spec}.{ts,tsx}", "src/test/**", "e2e/**"],
    languageOptions: { globals: { ...globals.browser, ...globals.node } },
  },
  {
    ignores: ["dist/**", "node_modules/**", "playwright-report/**", "dev-dist/**"],
  },
);
