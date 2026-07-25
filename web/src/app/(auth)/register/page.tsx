"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { api, ApiError } from "@/lib/api";
import { useAuth, type AuthUser } from "@/stores/auth";
import { AuthShell } from "@/app/(auth)/login/page";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";

export default function RegisterPage() {
  const router = useRouter();
  const setAuth = useAuth((s) => s.setAuth);
  const [form, setForm] = useState({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    business_name: "",
  });
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const res = await api<{ token: string; user: AuthUser }>("/auth/register", {
        method: "POST",
        body: form,
      });
      setAuth(res.token, res.user);
      router.replace("/dashboard");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Registration failed");
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthShell title="Create your business" subtitle="Start selling in minutes">
      <form onSubmit={onSubmit} className="space-y-4">
        <div>
          <Label>Business name</Label>
          <Input value={form.business_name} onChange={set("business_name")} required />
        </div>
        <div>
          <Label>Your name</Label>
          <Input value={form.name} onChange={set("name")} required />
        </div>
        <div>
          <Label>Email</Label>
          <Input type="email" value={form.email} onChange={set("email")} required />
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <Label>Password</Label>
            <Input type="password" value={form.password} onChange={set("password")} required />
          </div>
          <div>
            <Label>Confirm</Label>
            <Input type="password" value={form.password_confirmation} onChange={set("password_confirmation")} required />
          </div>
        </div>
        {error && <p className="text-sm text-[var(--color-danger)]">{error}</p>}
        <Button type="submit" size="lg" className="w-full" disabled={loading}>
          {loading && <Spinner />} Create account
        </Button>
      </form>
      <p className="mt-6 text-center text-sm text-[var(--muted)]">
        Already have an account?{" "}
        <Link href="/login" className="font-medium text-[var(--primary)]">
          Sign in
        </Link>
      </p>
    </AuthShell>
  );
}
