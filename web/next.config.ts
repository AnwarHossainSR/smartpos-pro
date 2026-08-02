import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Repo root also has a bun.lock; pin the workspace root to web/ so Turbopack
  // does not infer the Laravel root as the app directory.
  turbopack: {
    root: __dirname,
  },
  // Default bottom-left puts the dev overlay on top of the sidebar's own controls.
  devIndicators: {
    position: "bottom-right",
  },
  // Rewrites each `import { X } from "lucide-react"` to import directly from that icon's own
  // module, so a page that uses 5 icons doesn't pull the rest of the ~1600-icon package's
  // barrel file into its bundle. recharts is the app's heaviest dependency by far, but it's
  // already kept out of the main graph via next/dynamic (see reports/tax's chart) rather than
  // this — its exports aren't tree-shaken per-symbol the way lucide's are.
  experimental: {
    optimizePackageImports: ["lucide-react"],
  },
};

export default nextConfig;
