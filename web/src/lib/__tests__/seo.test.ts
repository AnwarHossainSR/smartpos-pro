import { describe, it, expect, beforeEach } from "vitest";
import { applySeo, formatTitle, SITE_NAME } from "@/lib/seo";

function head(selector: string) {
  return document.head.querySelectorAll(selector);
}

function content(selector: string) {
  return document.head.querySelector<HTMLMetaElement>(selector)?.content;
}

describe("formatTitle", () => {
  it("suffixes page titles with the site name", () => {
    expect(formatTitle("Dashboard")).toBe(`Dashboard · ${SITE_NAME}`);
  });

  it("falls back to the descriptive site title", () => {
    expect(formatTitle()).toContain(SITE_NAME);
    expect(formatTitle(SITE_NAME)).toBe(formatTitle());
  });
});

describe("applySeo", () => {
  beforeEach(() => {
    document.head.innerHTML = "";
    document.title = "";
  });

  it("sets title, description, canonical and social tags", () => {
    applySeo({ title: "Products", description: "Manage your catalog." }, "/products");

    expect(document.title).toBe(`Products · ${SITE_NAME}`);
    expect(content('meta[name="description"]')).toBe("Manage your catalog.");
    expect(content('meta[property="og:title"]')).toBe(`Products · ${SITE_NAME}`);
    expect(content('meta[property="og:url"]')).toBe(`${window.location.origin}/products`);
    expect(content('meta[name="twitter:description"]')).toBe("Manage your catalog.");
    expect(document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]')?.href).toBe(
      `${window.location.origin}/products`,
    );
  });

  it("marks authenticated pages noindex and public pages indexable", () => {
    applySeo({ title: "Dashboard", noIndex: true }, "/dashboard");
    expect(content('meta[name="robots"]')).toBe("noindex, nofollow");

    applySeo({ title: "Sign in" }, "/login");
    expect(content('meta[name="robots"]')).toBe("index, follow");
  });

  it("reuses head tags across navigations instead of duplicating them", () => {
    applySeo({ title: "Sales" }, "/sales");
    applySeo({ title: "Customers" }, "/customers");

    expect(head('meta[name="description"]')).toHaveLength(1);
    expect(head('meta[property="og:title"]')).toHaveLength(1);
    expect(head('link[rel="canonical"]')).toHaveLength(1);
    expect(document.title).toBe(`Customers · ${SITE_NAME}`);
  });
});
