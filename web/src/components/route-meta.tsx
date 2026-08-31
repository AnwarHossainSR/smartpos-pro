import { useEffect } from "react";
import { Outlet, useLocation, useMatches } from "react-router";
import { applySeo, type RouteHandle, type RouteSeo, SITE_NAME } from "@/lib/seo";

const FALLBACK: RouteSeo = { title: SITE_NAME };

function seoForMatches(matches: ReturnType<typeof useMatches>): RouteSeo {
  for (let i = matches.length - 1; i >= 0; i--) {
    const seo = (matches[i].handle as RouteHandle | undefined)?.seo;
    if (seo) return seo;
  }
  return FALLBACK;
}

/**
 * Root layout: keeps the document head in sync with the matched route.
 * Route titles/descriptions live in the `handle.seo` of each route in `src/routes.tsx`.
 */
export function RouteMeta() {
  const matches = useMatches();
  const { pathname } = useLocation();
  const seo = seoForMatches(matches);

  useEffect(() => {
    applySeo(seo, pathname);
  }, [seo, pathname]);

  return <Outlet />;
}
