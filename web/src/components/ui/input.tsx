import { cn } from "@/lib/utils";

// React 19 accepts `ref` as a plain prop on function components (no forwardRef needed) — it
// just has to be declared in the props type, since InputHTMLAttributes itself doesn't include it.
type InputProps = React.InputHTMLAttributes<HTMLInputElement> & { ref?: React.Ref<HTMLInputElement> };

export function Input({ className, ref, ...props }: InputProps) {
  return (
    <input
      ref={ref}
      className={cn(
        "h-10 w-full rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] px-3 text-sm outline-none transition focus:border-[var(--primary)] focus:ring-2 focus:ring-[var(--primary)]/20 placeholder:text-[var(--muted)]",
        className,
      )}
      {...props}
    />
  );
}

export function Label({ className, ...props }: React.LabelHTMLAttributes<HTMLLabelElement>) {
  return <label className={cn("mb-1.5 block text-sm font-medium", className)} {...props} />;
}
