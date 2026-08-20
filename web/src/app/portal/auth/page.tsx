"use client";

import { useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Logo } from "@/components/ui/logo";
import { Spinner } from "@/components/ui/spinner";
import { api, ApiError } from "@/lib/api";

export default function PortalAuthPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const token = searchParams.get("token");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!token) {
      setError("No token provided.");
      setLoading(false);
      return;
    }

    api<{ token: string }>(`/portal/verify/${token}`)
      .then((res) => {
        localStorage.setItem("portal_token", res.token);
        router.replace("/portal/dashboard");
      })
      .catch((err) => {
        setError(err instanceof ApiError ? err.message : "Invalid or expired link.");
        setLoading(false);
      });
  }, [token, router]);

  return (
    <div className="flex min-h-screen items-center justify-center bg-[var(--bg)] p-6">
      <div className="w-full max-w-md text-center">
        <Logo size={44} className="mx-auto mb-4" />
        {loading && <Spinner className="mx-auto" />}
        {error && (
          <>
            <h1 className="text-2xl font-semibold">Sign-in failed</h1>
            <p className="mt-2 text-sm text-[var(--muted)]">{error}</p>
          </>
        )}
      </div>
    </div>
  );
}
