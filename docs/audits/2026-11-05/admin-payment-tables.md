# Compact admin payment tables

Scope: presentation only on `/admin/payments`. No controller, route, schema, financial formula or permission changes.

Both tables use fixed layouts and five summary columns. Escrow shows order, recipient with a short role, amount, status and View. Transactions show order, method, amount, status and View. Secondary columns disappear as the content area narrows, with complete information in a native details dialog. Long identifiers are abbreviated in summary rows and shown in full in the dialog. Amounts remain visible. No horizontal scroll wrapper or clipping container is used.

View opens a keyboard-accessible dialog with the full original fields, order link, and relevant payout action. Release forms retain their POST route, CSRF token, confirmation and required GCash transfer reference. The backend still enforces complete escrow funding, delivery eligibility and payout limits. Pending COD batch release is unchanged. Server filters, separate pagers and shared table sorting remain available.

The header and summary cards are smaller and use light neutral surfaces. Duplicate commission summary was removed; the platform commission total remains in the earnings strip. Actual Voided records now display Voided rather than the previous generic Held fallback.

Validation:
- 16 existing escrow workflow tests passed (140 assertions).
- Blade compilation, JavaScript syntax and whitespace checks passed.
- Chromium checks at 1440, 1024, 768, 390 and 320 px: page/table/dialog widths fit, View actions visible, rows below 85 px, full details present, Escape/close restore focus, filters visible and shared sorting retained.
- Render-only pending payout fixture checks required reference, CSRF, original endpoint, cancellation and confirmation. Transfer confirmation safely handles apostrophes in recipient names. Final POST is intercepted by the local preview; no funds are moved.

Preview source is a read-only render of current payment records. A separate in-memory variant demonstrates the pending GCash release form; no model or session is saved. Ignored review screenshots and helper scripts live under `.tools/admin-payments-*`, `.tools/admin-payout-detail-*` and `.tools/check-admin-payments.mjs`.
