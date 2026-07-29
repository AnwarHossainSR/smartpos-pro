export function AuthShell({
  title,
  subtitle,
  children,
}: {
  title: string;
  subtitle: string;
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-screen items-center justify-center p-6">
      <div className="w-full max-w-md">
        <div className="mb-8 text-center">
          <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-[var(--primary)] text-xl font-bold text-[var(--primary-fg)]">
            S
          </div>
          <h1 className="text-2xl font-semibold">{title}</h1>
          <p className="mt-1 text-sm text-[var(--muted)]">{subtitle}</p>
        </div>
        <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-6 shadow-sm">
          {children}
        </div>
      </div>
    </div>
  );
}
