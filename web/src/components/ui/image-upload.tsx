import { Upload, X } from "lucide-react";
import { useCallback, useRef, useState } from "react";
import { cn } from "@/lib/utils";
import { toast } from "./toast";

interface ImageUploadProps {
  onUpload: (file: File) => void;
  currentUrl?: string | null;
  onRemove?: () => void;
  accept?: string;
  maxSizeMb?: number;
  className?: string;
}

export function ImageUpload({
  onUpload,
  currentUrl,
  onRemove,
  accept = "image/jpeg,image/png,image/webp",
  maxSizeMb = 2,
  className,
}: ImageUploadProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [preview, setPreview] = useState<string | null>(currentUrl ?? null);
  const [dragging, setDragging] = useState(false);

  const handleFile = useCallback(
    (file: File) => {
      if (file.size > maxSizeMb * 1024 * 1024) {
        // Returning quietly here left the user staring at an unchanged field with
        // no idea why their file did not take.
        toast(
          `Image must be smaller than ${maxSizeMb} MB — that file is ${(file.size / 1024 / 1024).toFixed(1)} MB.`,
          "error",
        );
        return;
      }
      const url = URL.createObjectURL(file);
      setPreview(url);
      onUpload(file);
    },
    [maxSizeMb, onUpload],
  );

  const handleChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const file = e.target.files?.[0];
      if (file) handleFile(file);
      e.target.value = "";
    },
    [handleFile],
  );

  const handleDrop = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault();
      setDragging(false);
      const file = e.dataTransfer.files[0];
      if (file && file.type.startsWith("image/")) handleFile(file);
    },
    [handleFile],
  );

  const handleRemove = useCallback(() => {
    setPreview(null);
    onRemove?.();
  }, [onRemove]);

  if (preview) {
    return (
      <div className={cn("relative group inline-block", className)}>
        <img
          src={preview}
          alt="Preview"
          className="h-20 w-20 rounded-[var(--radius)] object-cover border border-[var(--border)]"
        />
        {onRemove && (
          <button
            type="button"
            onClick={handleRemove}
            className="absolute -top-1.5 -right-1.5 h-5 w-5 rounded-full bg-[var(--color-danger)] text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer"
          >
            <X className="h-3 w-3" />
          </button>
        )}
      </div>
    );
  }

  return (
    <div
      className={cn(
        "flex flex-col items-center justify-center gap-1 h-20 w-20 rounded-[var(--radius)] border-2 border-dashed cursor-pointer transition-colors",
        dragging
          ? "border-[var(--primary)] bg-[var(--primary)]/5"
          : "border-[var(--border)] hover:border-[var(--primary)]/50",
        className,
      )}
      onDragOver={(e) => {
        e.preventDefault();
        setDragging(true);
      }}
      onDragLeave={() => setDragging(false)}
      onDrop={handleDrop}
      onClick={() => inputRef.current?.click()}
    >
      <Upload className="h-4 w-4 text-[var(--muted)]" />
      <span className="text-[10px] text-[var(--muted)]">Upload</span>
      <input
        ref={inputRef}
        type="file"
        accept={accept}
        onChange={handleChange}
        className="hidden"
      />
    </div>
  );
}
