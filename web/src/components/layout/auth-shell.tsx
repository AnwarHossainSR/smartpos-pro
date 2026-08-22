import { Link } from "react-router";
import { Check } from "lucide-react";
import { Logo } from "@/components/ui/logo";
import { useTranslation } from "@/i18n/context";

export function AuthShell({
  title,
  subtitle,
  children,
}: {
  title: string;
  subtitle: string;
  children: React.ReactNode;
}) {
  const t = useTranslation();
  const features = [t("auth.feature1"), t("auth.feature2"), t("auth.feature3")];

  return (
    <div className="flex min-h-screen">
      <div className="relative hidden w-[45%] flex-col justify-between overflow-hidden bg-[var(--sidebar-bg)] p-12 text-white lg:flex">
        <div className="pointer-events-none absolute -top-24 -left-24 h-72 w-72 rounded-full bg-[var(--primary)]/30 blur-3xl" />
        <div className="pointer-events-none absolute -bottom-32 -right-16 h-80 w-80 rounded-full bg-[var(--primary)]/20 blur-3xl" />

        <Link to="/" className="relative flex items-center gap-2">
          <Logo size={36} />
          <span className="text-lg font-semibold">SmartPOS</span>
        </Link>

        <div className="relative space-y-5">
          <h2 className="max-w-sm text-3xl font-semibold leading-tight">{t("auth.marketingTitle")}</h2>
          <p className="max-w-sm text-sm text-white/70">{t("auth.marketingBody")}</p>
          <ul className="space-y-2.5 pt-2 text-sm text-white/85">
            {features.map((f) => (
              <li key={f} className="flex items-center gap-2.5">
                <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/10">
                  <Check className="h-3 w-3 text-[var(--sidebar-active)]" />
                </span>
                {f}
              </li>
            ))}
          </ul>
        </div>

        <p className="relative text-xs text-white/40">© {new Date().getFullYear()} SmartPOS Pro</p>
      </div>

      <div className="flex flex-1 items-center justify-center bg-[var(--bg)] p-6">
        <div className="w-full max-w-md">
          <div className="mb-8 text-center lg:text-left">
            <Logo size={44} className="mx-auto mb-4 lg:hidden" />
            <h1 className="text-2xl font-semibold">{title}</h1>
            <p className="mt-1 text-sm text-[var(--muted)]">{subtitle}</p>
          </div>
          <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-6 shadow-sm">
            {children}
          </div>
        </div>
      </div>
    </div>
  );
}
