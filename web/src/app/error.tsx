"use client";

import { useEffect } from "react";
import { AlertTriangle, RefreshCw } from "lucide-react";

export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    console.error("Application error:", error);
  }, [error]);

  return (
    <html lang="en">
      <body>
        <div className="flex min-h-screen items-center justify-center bg-white p-4">
          <div className="w-full max-w-md space-y-6 text-center">
            <div className="flex justify-center">
              <div className="rounded-full bg-red-50 p-4">
                <AlertTriangle className="h-10 w-10 text-red-600" />
              </div>
            </div>

            <div className="space-y-2">
              <h1 className="text-2xl font-semibold text-gray-900">
                Something went wrong
              </h1>
              <p className="text-sm text-gray-500">
                An unexpected error occurred. Please try again.
              </p>
            </div>

            {error.digest && (
              <p className="text-xs text-gray-400">Error ID: {error.digest}</p>
            )}

            <button
              onClick={reset}
              className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
            >
              <RefreshCw className="h-4 w-4" />
              Try again
            </button>
          </div>
        </div>
      </body>
    </html>
  );
}
