import type { RouteHandle } from "@/lib/seo";

/** Adapts a `React.lazy`-style dynamic import to React Router's `lazy()` contract. */
export async function loadPage(factory: () => Promise<{ default: React.ComponentType }>) {
  const { default: Component } = await factory();
  return { Component };
}

/** Title + description for the document head. Everything behind auth is `noIndex`. */
export function seo(
  title: string,
  description: string,
  options?: { indexable?: boolean },
): RouteHandle {
  return { seo: { title, description, noIndex: !options?.indexable } };
}
