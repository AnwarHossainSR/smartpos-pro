import { render, screen } from "@testing-library/react";
import { createMemoryRouter, RouterProvider } from "react-router";
import { describe, expect, it } from "vitest";
import { RouteMeta } from "@/components/route-meta";
import { SITE_NAME } from "@/lib/seo";

const routes = [
  {
    element: <RouteMeta />,
    children: [
      {
        path: "/shell",
        element: <div>shell</div>,
        children: [
          {
            path: "products",
            element: <div>products page</div>,
            handle: { seo: { title: "Products" } },
          },
        ],
      },
      { path: "/login", element: <div>login page</div>, handle: { seo: { title: "Sign in" } } },
      { path: "/bare", element: <div>bare page</div> },
    ],
  },
];

function renderAt(path: string) {
  return render(<RouterProvider router={createMemoryRouter(routes, { initialEntries: [path] })} />);
}

describe("RouteMeta", () => {
  it("titles a page from the deepest matched route handle", async () => {
    renderAt("/shell/products");
    expect(await screen.findByText("shell")).toBeInTheDocument();
    expect(document.title).toBe(`Products · ${SITE_NAME}`);
  });

  it("titles a top-level route", async () => {
    renderAt("/login");
    expect(await screen.findByText("login page")).toBeInTheDocument();
    expect(document.title).toBe(`Sign in · ${SITE_NAME}`);
  });

  it("falls back to the site title when a route declares no seo handle", async () => {
    renderAt("/bare");
    expect(await screen.findByText("bare page")).toBeInTheDocument();
    expect(document.title).toContain(SITE_NAME);
  });
});
