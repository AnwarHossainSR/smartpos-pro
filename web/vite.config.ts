import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import { VitePWA } from "vite-plugin-pwa";
import path from "path";

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
        name: "SmartPOS Pro",
        short_name: "SmartPOS",
        description: "Modern multi-tenant POS & retail management platform",
        start_url: "/dashboard",
        display: "standalone",
        background_color: "#0b1120",
        theme_color: "#4f46e5",
        orientation: "any",
        icons: [
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
    alias: { "@": path.resolve(__dirname, "src") },
  },
  server: {
    port: 3000,
    proxy: {
      "/api": "http://localhost:8080",
    },
  },
});
