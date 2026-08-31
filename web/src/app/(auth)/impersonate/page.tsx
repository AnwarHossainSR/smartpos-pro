import { useEffect } from "react";
import { useNavigate, useSearchParams } from "react-router";
import { api } from "@/lib/api";
import { useAuth } from "@/stores/auth";

/**
 * Landing page for impersonation. The super-admin's tenant-list opens a new tab
 * to `/impersonate?token=<sanctum-token>`. This page stores the token in the auth
 * cookie, fetches the user profile, and redirects to the dashboard.
 */
export default function ImpersonatePage() {
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const setImpersonateAuth = useAuth((s) => s.setImpersonateAuth);

  useEffect(() => {
    const token = params.get("token");
    if (!token) {
      navigate("/login", { replace: true });
      return;
    }

    let cancelled = false;

    async function run() {
      try {
        const res = await api<{ data: { user: import("@/stores/auth").AuthUser } }>("/auth/me", {
          headers: { Authorization: `Bearer ${token}` },
        });

        if (cancelled) return;
        setImpersonateAuth(token as string, res.data.user);
        navigate("/dashboard", { replace: true });
      } catch {
        if (cancelled) return;
        navigate("/login", { replace: true });
      }
    }

    run();

    return () => {
      cancelled = true;
    };
  }, [params, navigate, setImpersonateAuth]);

  return (
    <div className="flex h-screen items-center justify-center">
      <p className="text-sm text-[var(--muted)]">Signing you in…</p>
    </div>
  );
}
