import { api, apiUpload, errorMessage } from "@/lib/api";
import { cn } from "@/lib/utils";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { ImageOff, Loader2, Trash2, Upload } from "lucide-react";
import { useCallback, useId, useRef, useState } from "react";
import { Button } from "./button";
import { Dialog } from "./dialog";
import { toast } from "./toast";
import { useMediaCollections } from "./use-media-collections";

interface MediaFieldProps {
  /** Collection key, e.g. `business.logo`. Drives the accept and size limits. */
  collection: string;
  /** Endpoint accepting `POST` (multipart, field `file`) and `DELETE`. */
  endpoint: string;
  label: string;
  hint?: string;
  /** Current image, or null when the slot is empty. */
  url?: string | null;
  /** Query keys to invalidate once the slot changes — pass `queryKeys.*` members. */
  invalidate?: readonly (readonly unknown[])[];
  /** Square for a logo or avatar; wide for a cover image. */
  aspect?: "square" | "wide";
  disabled?: boolean;
  className?: string;
}

/**
 * One image slot: shows what is stored, replaces it, or clears it.
 *
 * Uploads immediately rather than deferring to a surrounding form submit. A logo is
 * not a form field — there is nothing to "save" alongside it, and holding the file
 * until submit means an upload failure surfaces long after the user chose the file.
 *
 * The optimistic preview is a local object URL, so the new image appears at once
 * even though the bucket write and the CDN fetch have not finished.
 */
export function MediaField({
  collection,
  endpoint,
  label,
  hint,
  url,
  invalidate = [],
  aspect = "square",
  disabled = false,
  className,
}: MediaFieldProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const inputId = useId();
  const qc = useQueryClient();
  const [dragging, setDragging] = useState(false);
  const [preview, setPreview] = useState<string | null>(null);
  const [confirmingRemove, setConfirmingRemove] = useState(false);

  const { data: collections } = useMediaCollections();
  const limits = collections?.data.find((c) => c.key === collection);

  const settle = useCallback(() => {
    for (const key of invalidate) qc.invalidateQueries({ queryKey: key });
  }, [invalidate, qc]);

  const upload = useMutation({
    mutationFn: (file: File) => {
      const form = new FormData();
      form.append("file", file);
      return apiUpload(endpoint, form);
    },
    onSuccess: () => {
      toast(`${label} updated.`);
      settle();
    },
    onError: (e: unknown) => {
      // The optimistic preview has to go, or the user is left looking at an image
      // that was never stored.
      setPreview(null);
      toast(errorMessage(e, `Could not upload ${label.toLowerCase()}.`), "error");
    },
  });

  const remove = useMutation({
    mutationFn: () => api(endpoint, { method: "DELETE" }),
    onSuccess: () => {
      setPreview(null);
      setConfirmingRemove(false);
      toast(`${label} removed.`);
      settle();
    },
    onError: (e: unknown) => {
      setConfirmingRemove(false);
      toast(errorMessage(e, `Could not remove ${label.toLowerCase()}.`), "error");
    },
  });

  const accept = limits?.accept ?? "image/jpeg,image/png,image/webp";
  const maxKb = limits?.max_kb ?? 2048;
  const busy = upload.isPending || remove.isPending;

  const handleFile = useCallback(
    (file: File) => {
      if (file.size > maxKb * 1024) {
        toast(
          `${label} must be smaller than ${(maxKb / 1024).toFixed(1)} MB — that file is ${(file.size / 1024 / 1024).toFixed(1)} MB.`,
          "error",
        );
        return;
      }

      // Extension rather than MIME: browsers disagree on the type they report for
      // SVG and WebP, but the extension is what the server validates against too.
      const extension = file.name.split(".").pop()?.toLowerCase() ?? "";
      if (limits && !limits.mimes.includes(extension)) {
        toast(`${label} must be one of: ${limits.mimes.join(", ")}.`, "error");
        return;
      }

      setPreview(URL.createObjectURL(file));
      upload.mutate(file);
    },
    [label, limits, maxKb, upload],
  );

  const shown = preview ?? url ?? null;
  const box = aspect === "wide" ? "h-24 aspect-[16/9]" : "h-24 w-24";

  return (
    <div className={cn("space-y-2", className)}>
      <div className="flex items-baseline justify-between gap-3">
        <label htmlFor={inputId} className="text-sm font-medium">
          {label}
        </label>
        {limits && (
          <span className="font-[family-name:var(--font-mono)] text-[0.65rem] text-[var(--muted)]">
            {limits.mimes.join("/")} · max {(maxKb / 1024).toFixed(maxKb % 1024 === 0 ? 0 : 1)} MB
          </span>
        )}
      </div>

      <div className="flex items-start gap-3">
        <div
          role="button"
          tabIndex={disabled ? -1 : 0}
          aria-disabled={disabled}
          aria-busy={busy}
          onClick={() => !disabled && !busy && inputRef.current?.click()}
          onKeyDown={(e) => {
            if (disabled || busy) return;
            if (e.key === "Enter" || e.key === " ") {
              e.preventDefault();
              inputRef.current?.click();
            }
          }}
          onDragOver={(e) => {
            if (disabled || busy) return;
            e.preventDefault();
            setDragging(true);
          }}
          onDragLeave={() => setDragging(false)}
          onDrop={(e) => {
            e.preventDefault();
            setDragging(false);
            if (disabled || busy) return;
            const file = e.dataTransfer.files[0];
            if (file) handleFile(file);
          }}
          className={cn(
            "relative flex shrink-0 items-center justify-center overflow-hidden rounded-[var(--radius)] border-2 border-dashed transition-colors",
            box,
            disabled
              ? "cursor-not-allowed border-[var(--border)] opacity-60"
              : "cursor-pointer",
            dragging
              ? "border-[var(--primary)] bg-[var(--primary)]/5"
              : "border-[var(--border)] hover:border-[var(--primary)]/50",
          )}
        >
          {shown ? (
            // Plain <img>: the src is either a bucket URL or a local object URL
            // during the optimistic preview.
            <img src={shown} alt={label} className="h-full w-full object-contain p-1" />
          ) : (
            <div className="flex flex-col items-center gap-1 text-[var(--muted)]">
              <Upload className="h-4 w-4" />
              <span className="text-[10px]">Upload</span>
            </div>
          )}

          {busy && (
            <div className="absolute inset-0 flex items-center justify-center bg-[var(--surface)]/70">
              <Loader2 className="h-5 w-5 animate-spin text-[var(--primary)]" />
            </div>
          )}
        </div>

        <div className="min-w-0 flex-1 space-y-2">
          {hint && <p className="text-xs leading-relaxed text-[var(--muted)]">{hint}</p>}
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={disabled || busy}
              onClick={() => inputRef.current?.click()}
            >
              {shown ? "Replace" : "Choose file"}
            </Button>
            {url && (
              <Button
                type="button"
                size="sm"
                variant="ghost"
                disabled={disabled || busy}
                onClick={() => setConfirmingRemove(true)}
              >
                <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                Remove
              </Button>
            )}
          </div>
        </div>
      </div>

      <input
        id={inputId}
        ref={inputRef}
        type="file"
        accept={accept}
        className="hidden"
        disabled={disabled || busy}
        onChange={(e) => {
          const file = e.target.files?.[0];
          if (file) handleFile(file);
          // Reset so choosing the same file twice still fires a change event.
          e.target.value = "";
        }}
      />

      <Dialog
        open={confirmingRemove}
        onClose={() => setConfirmingRemove(false)}
        title={`Remove ${label.toLowerCase()}?`}
        confirmLabel="Remove"
        confirmVariant="danger"
        loading={remove.isPending}
        onConfirm={() => remove.mutate()}
      >
        <p className="flex items-start gap-2 text-sm text-[var(--muted)]">
          <ImageOff className="mt-0.5 h-4 w-4 shrink-0" />
          <span>
            The image is deleted from storage and cannot be recovered. Anywhere it appears —
            receipts, the portal, exported documents — falls back to the business name.
          </span>
        </p>
      </Dialog>
    </div>
  );
}
