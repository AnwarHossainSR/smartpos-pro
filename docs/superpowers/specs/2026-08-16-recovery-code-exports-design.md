# Recovery-code export design

## Goal

Let a user download newly displayed two-factor recovery codes as CSV or a print-ready PDF before dismissing the one-time recovery-code modal.

## Behaviour

- The modal displays `Download CSV` and `Download PDF` actions beside the final acknowledgement action.
- CSV contains a single `Recovery code` column and one code per row.
- PDF contains the SmartPOS heading, a one-time-use warning, export timestamp, and the recovery-code grid.
- Both exports run entirely in the browser. Recovery codes are not stored, uploaded, or sent to an API.
- The acknowledgement button remains the only way to dismiss the modal.
- Export failures notify the user without closing the modal.

## Implementation

- Extract small, testable export helpers for CSV creation and PDF print-document generation.
- Use `Blob` plus a temporary object URL for CSV download.
- Generate the PDF as a self-contained print page, invoke the browser print dialog, and revoke resources when finished. This avoids adding a heavyweight PDF package and supports the browser's native Save as PDF workflow.
- Add focused unit tests for CSV escaping/content and the printable document content.

## Scope

Only the recovery-code modal changes. Existing two-factor API, regeneration flow, and modal acknowledgement behavior remain unchanged.
