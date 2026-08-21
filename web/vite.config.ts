import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import path from "path";
import { defineConfig } from "vite";
import { VitePWA } from "vite-plugin-pwa";

export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
    VitePWA({
      strategies: "injectManifest",
      srcDir: "src",
      filename: "sw.ts",
      registerType: "autoUpdate",
      devOptions: { enabled: false },
      manifest: {
        id: "/",
        name: "SmartPOS Pro — Point of Sale, Inventory & Accounting",
        short_name: "SmartPOS",
        description:
          "Run your shop end to end: point of sale, inventory, purchasing, customers and accounting in a single platform.",
        lang: "en",
        dir: "ltr",
        categories: ["business", "productivity", "finance"],
        scope: "/",
        start_url: "/dashboard",
        display: "standalone",
        background_color: "#0b1120",
        theme_color: "#4f46e5",
        orientation: "any",
        icons: [
          {
            src: "/favicon.svg",
            sizes: "any",
            type: "image/svg+xml",
            purpose: "any",
          },
          {
            src: "/icon-192.png",
            sizes: "192x192",
            type: "image/png",
            purpose: "any maskable",
          },
          {
            src: "/icon-512.png",
            sizes: "512x512",
            type: "image/png",
            purpose: "any maskable",
          },
        ],
        shortcuts: [
          {
            name: "POS Terminal",
            short_name: "POS",
            url: "/pos",
            description: "Open the point-of-sale terminal",
          },
          {
            name: "New Sale",
            short_name: "Sale",
            url: "/sales",
            description: "View sales history",
          },
        ],
      },
    }),
  ],
  resolve: {
    alias: { "@": path.resolve(import.meta.dirname, "src") },
  },
  build: {
    // Split the long-lived third-party code out of the app chunk so a normal app deploy
    // does not invalidate React/router/query in everyone's browser cache. recharts and the
    // barcode/printing code stay out of this list on purpose — they are already reached
    // through dynamic import()s and get their own chunks only on the pages that use them.
    rollupOptions: {
      output: {
        manualChunks(id: string) {
          if (!id.includes("node_modules")) return;
          if (/[\/]node_modules[\/](react|react-dom|react-is|scheduler)[\/]/.test(id)) return "react";
          if (id.includes("react-router")) return "router";
          if (id.includes("@tanstack")) return "query";
          if (/react-hook-form|@hookform|[\/]zod[\/]/.test(id)) return "forms";
        },
      },
    },
  },
  server: {
    port: 3000,
    // VITE_API_URL is absolute (a separate Laravel origin), so the app itself does not use
    // this proxy — it is here for the service worker's offline replay and anything else that
    // falls back to a same-origin /api path in dev.
    proxy: {
      "/api": "http://localhost:8080",
    },
  },
});
