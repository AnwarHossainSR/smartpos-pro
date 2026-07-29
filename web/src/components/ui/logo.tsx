import { useId } from "react";
import { cn } from "@/lib/utils";

interface LogoProps {
  size?: number;
  className?: string;
  /** Renders just the mark (no rounded badge behind it) — for use on colored surfaces. */
  markOnly?: boolean;
}

export function Logo({ size = 32, className, markOnly }: LogoProps) {
  const gradientId = useId();

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 32 32"
      className={cn("shrink-0", className)}
      role="img"
      aria-label="SmartPOS"
    >
      <defs>
        <linearGradient id={gradientId} gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="32" y2="32">
          <stop offset="0" stopColor="var(--primary)" />
          <stop offset="1" stopColor="var(--primary-hover)" />
        </linearGradient>
      </defs>
      {!markOnly && <rect width="32" height="32" rx="9" fill={`url(#${gradientId})`} />}
      <path
        d="M19.5 10.8C19.5 8 17 6.3 14.3 6.7C11.8 7.1 10 8.8 10 11C10 13.6 12.3 14.6 16 15.6C19.7 16.6 22 17.9 22 20.8C22 23.4 19.8 25.3 16.8 25.3C14 25.3 11.5 23.7 11.3 21"
        stroke={markOnly ? "currentColor" : "white"}
        strokeWidth="3.4"
        strokeLinecap="round"
        fill="none"
      />
    </svg>
  );
}
