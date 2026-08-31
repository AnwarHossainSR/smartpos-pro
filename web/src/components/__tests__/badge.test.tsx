import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { Badge } from "../ui/badge";

describe("Badge", () => {
  it("renders children", () => {
    render(<Badge>Active</Badge>);
    expect(screen.getByText("Active")).toBeInTheDocument();
  });

  it("applies success tone class", () => {
    const { container } = render(<Badge tone="success">OK</Badge>);
    expect(container.firstChild).toHaveClass("text-green-600");
  });

  it("applies danger tone class", () => {
    const { container } = render(<Badge tone="danger">Error</Badge>);
    expect(container.firstChild).toHaveClass("text-red-600");
  });

  it("applies warning tone class", () => {
    const { container } = render(<Badge tone="warning">Warn</Badge>);
    expect(container.firstChild).toHaveClass("text-amber-600");
  });
});
