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
};

export default nextConfig;
