import { useEffect, useState } from "react";
import { useNavigate, useSearchParams } from "react-router";
import { Logo } from "@/components/ui/logo";
import { Spinner } from "@/components/ui/spinner";
import { api, errorMessage } from "@/lib/api";
import { setPortalToken } from "@/lib/portal";

export default function PortalAuthPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const token = searchParams.get("token");

  const [verifyError, setVerifyError] = useState<string | null>(null);
  const [verifying, setVerifying] = useState(!!token);

  useEffect(() => {
    if (!token) return;

    let active = true;
    api<{ token: string }>(`/portal/verify/${token}`)
      .then((res) => {
        if (!active) return;
        setPortalToken(res.token);
        navigate("/portal/dashboard", { replace: true });
      })
      .catch((err: unknown) => {
        if (!active) return;
        setVerifyError(errorMessage(err, "Invalid or expired link."));
        setVerifying(false);
      });

    return () => {
      active = false;
    };
  }, [token, navigate]);

  const error = token ? verifyError : "No sign-in token was provided.";

  return (
    <div className="flex min-h-screen items-center justify-center bg-[var(--bg)] p-6">
      <div className="w-full max-w-md text-center">
        <Logo size={44} className="mx-auto mb-4" />
        {verifying && (
          <div role="status" aria-live="polite">
            <Spinner className="mx-auto" />
            <span className="sr-only">Signing you in</span>
          </div>
        )}
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
