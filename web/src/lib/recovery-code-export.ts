export function exportRecoveryCodesAsCsv(codes: string[]) {
  const csv = ["Recovery code", ...codes].join("\n");
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = "recovery-codes.csv";
  a.click();
  URL.revokeObjectURL(url);
}

export function exportRecoveryCodesAsPdf(codes: string[]) {
  const timestamp = new Date().toLocaleString();
  const codeRows = codes
    .map((c) => `<li style="font-family:monospace;font-size:14px;padding:4px 0;">${c}</li>`)
    .join("");

  const html = `<!DOCTYPE html>
<html>
<head>
  <title>Recovery Codes</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 480px; margin: 0 auto; padding: 40px 20px; color: #1a1a1a; }
    h1 { font-size: 20px; margin-bottom: 8px; }
    .warning { background: #fef3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 12px; font-size: 13px; margin-bottom: 20px; }
    .timestamp { font-size: 12px; color: #666; margin-bottom: 16px; }
    ul { list-style: none; padding: 0; columns: 2; column-gap: 24px; }
    @media print { body { padding: 20px; } }
  </style>
</head>
<body>
  <h1>SmartPOS Recovery Codes</h1>
  <div class="warning">
    Each code can be used once to sign in if you lose access to your authenticator app.
    Keep these codes in a safe place. They will not be shown again.
  </div>
  <div class="timestamp">Exported: ${timestamp}</div>
  <ul>${codeRows}</ul>
</body>
</html>`;

  const printWindow = window.open("", "_blank");
  if (!printWindow) return;
  printWindow.document.write(html);
  printWindow.document.close();
  printWindow.focus();
  printWindow.print();
  setTimeout(() => printWindow.close(), 1000);
}
