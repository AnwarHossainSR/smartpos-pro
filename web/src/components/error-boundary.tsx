import { AlertTriangle, RefreshCw } from "lucide-react";
import { useEffect } from "react";
import { isRouteErrorResponse, Link, useRouteError } from "react-router";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/api";

interface Described {
  title: string;
  detail: string;
  /** Shown small, for a support ticket — only when the server gave us a status to quote. */
  code?: string;
}

/**
 * Three shapes reach an error element: a `Response` thrown by a loader (`isRouteErrorResponse`),
 * an `ApiError` from `api()`, and a plain render-time `Error`. Only the first two can say
 * anything useful about *what* went wrong, so the third gets a deliberately generic line
 * rather than leaking a minified stack message at the user.
 */
function describe(error: unknown): Described {
  if (isRouteErrorResponse(error)) {
    if (error.status === 404) {
      return {
        title: "Page not found",
        detail: "The page you asked for does not exist, or has been moved.",
        code: "404",
      };
    }
    if (error.status === 403) {
      return {
        title: "You do not have access to this",
        detail: "Ask an administrator to grant you the permission this page needs.",
        code: "403",
      };
    }
    return {
      title: "Something went wrong",
      detail: error.statusText || "The server could not complete that request.",
      code: String(error.status),
    };
  }

  if (error instanceof Error) {
    return { title: "Something went wrong", detail: errorMessage(error) };
  }

  return { title: "Something went wrong", detail: "An unexpected error occurred." };
}

/**
 * The router's `errorElement`. Without one, React Router renders its own developer screen —
 * a stack trace and "💿 Hey developer 👋" — to whoever is standing at the till.
 *
 * "Try again" is a full reload rather than a state reset: a render-time error leaves the
 * component tree in an unknown state, and unlike a loader error there is nothing to
 * revalidate. Navigating to the dashboard clears the error too (the router drops it on a
 * location change), so that link is the cheaper escape when the app itself is still healthy.
 */
export function RouteErrorBoundary() {
  const error = useRouteError();
  const { title, detail, code } = describe(error);

  useEffect(() => {
    console.error("Route error:", error);
  }, [error]);

  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center gap-4 p-6 text-center">
      <div className="rounded-full bg-[var(--surface-2)] p-4">
        <AlertTriangle className="h-9 w-9 text-[var(--color-danger)]" aria-hidden="true" />
      </div>

      <div className="space-y-2">
        <h1 className="text-lg font-semibold">{title}</h1>
        <p className="max-w-sm text-sm text-[var(--muted)]">{detail}</p>
      </div>

      {code && (
        <p className="rounded-[var(--radius)] border border-[var(--border)] px-2 py-1 font-mono text-xs text-[var(--muted)]">
          {code}
        </p>
      )}

      <div className="flex gap-2">
        <Button variant="outline" onClick={() => window.location.reload()}>
          <RefreshCw className="h-4 w-4" />
          Try again
        </Button>
        <Button asChild>
          <Link to="/dashboard">Go to dashboard</Link>
        </Button>
      </div>
    </div>
  );
}
