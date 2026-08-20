import { ImageResponse } from "next/og";
import { SITE } from "@/lib/seo";

/**
 * The link preview card, generated at build time rather than checked in as a PNG,
 * so the wording can never drift from what the site actually says.
 *
 * Deliberately built from plain divs and system-ish font stacks: `next/og` renders
 * in Satori, which supports a subset of CSS (no grid, no shorthand `flex: 1`
 * ambiguity, every child of a multi-child element needs an explicit display), and
 * silently mis-renders rather than erroring when given something it cannot handle.
 */
export const alt = `${SITE.name} — run the whole shop from one counter`;
export const size = { width: 1200, height: 630 };
export const contentType = "image/png";

export default function OpenGraphImage() {
  return new ImageResponse(
    (
      <div
        style={{
          width: "100%",
          height: "100%",
          display: "flex",
          flexDirection: "column",
          justifyContent: "space-between",
          backgroundColor: "#0d1020",
          backgroundImage:
            "radial-gradient(circle at 22% 18%, #1e2a5e 0%, transparent 55%), radial-gradient(circle at 82% 88%, #14224a 0%, transparent 50%)",
          padding: "72px 80px",
          fontFamily: "sans-serif",
        }}
      >
        <div style={{ display: "flex", alignItems: "center", gap: 20 }}>
          <div
            style={{
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              width: 64,
              height: 64,
              borderRadius: 16,
              backgroundColor: "#6366f1",
              color: "#fff",
              fontSize: 36,
              fontWeight: 700,
            }}
          >
            S
          </div>
          <div style={{ display: "flex", color: "#e8eaf6", fontSize: 34, fontWeight: 600 }}>
            {SITE.name}
          </div>
        </div>

        <div style={{ display: "flex", flexDirection: "column", gap: 24 }}>
          <div
            style={{
              display: "flex",
              color: "#ffffff",
              fontSize: 74,
              fontWeight: 700,
              lineHeight: 1.05,
              letterSpacing: "-0.03em",
              maxWidth: 900,
            }}
          >
            Run the whole shop from one counter.
          </div>
          <div style={{ display: "flex", color: "#9aa3c7", fontSize: 30, maxWidth: 860 }}>
            {SITE.shortDescription}
          </div>
        </div>

        <div
          style={{
            display: "flex",
            gap: 16,
            color: "#7f89b4",
            fontSize: 24,
            letterSpacing: "0.14em",
            textTransform: "uppercase",
          }}
        >
          <div style={{ display: "flex" }}>Point of sale</div>
          <div style={{ display: "flex" }}>·</div>
          <div style={{ display: "flex" }}>Inventory</div>
          <div style={{ display: "flex" }}>·</div>
          <div style={{ display: "flex" }}>Accounting</div>
        </div>
      </div>
    ),
    size,
  );
}
