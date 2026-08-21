import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Logo } from "@/components/ui/logo";
import { Spinner } from "@/components/ui/spinner";
import { api, ApiError } from "@/lib/api";
import { useState } from "react";

export default function PortalLoginPage() {
  const [email, setEmail] = useState("");
  const [sent, setSent] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      await api("/portal/magic-link", {
        method: "POST",
        body: { email },
      });
      setSent(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to send magic link");
    } finally {
      setLoading(false);
    }
  }

  if (sent) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--bg)] p-6">
        <div className="w-full max-w-md text-center">
          <Logo size={44} className="mx-auto mb-4" />
          <h1 className="text-2xl font-semibold">Check your email</h1>
          <p className="mt-2 text-sm text-[var(--muted)]">
            We sent a sign-in link to <strong>{email}</strong>. Click the link to access your account.
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-[var(--bg)] p-6">
      <div className="w-full max-w-md">
        <div className="mb-8 text-center">
          <Logo size={44} className="mx-auto mb-4" />
          <h1 className="text-2xl font-semibold">Sign in to your account</h1>
          <p className="mt-1 text-sm text-[var(--muted)]">
            Enter your email and we&apos;ll send you a magic link.
          </p>
        </div>
        <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-6 shadow-sm">
          <form onSubmit={onSubmit} className="space-y-4">
            <div>
              <Label>Email address</Label>
              <Input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                autoFocus
              />
            </div>
            {error && <p className="text-sm text-[var(--color-danger)]">{error}</p>}
            <Button type="submit" size="lg" className="w-full" disabled={loading}>
              {loading && <Spinner />} {loading ? "Sending..." : "Send magic link"}
            </Button>
          </form>
        </div>
      </div>
    </div>
  );
}
