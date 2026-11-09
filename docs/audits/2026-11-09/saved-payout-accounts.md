# Saved receiving accounts for remittances

Workers can save and edit one receiving account in their regular remittance page. The same configuration is used by Admin when reviewing both original order payouts and return-service payouts. Account details are informational: saving them does not change amounts, funding, release or receipt-confirmation rules.

## UI

- Sellers: **Remittances → Bank & e-wallet details → Add details / Edit details**.
- Logistics: **Hub remittances → Bank & e-wallet details**. Staff at the same hub share that hub's account. Company managers can manage only their company's hubs.
- Rider, inter-hub driver and logistics app: **Remittances → Bank details** in the top bar.
- Fields: **Bank / e-wallet name**, **Account holder**, **Account number / mobile number**. **Save payout details** creates or updates the same record. Numbers are strings, retaining leading zeros.
- Admin: **Manage Payments → payout View → Current saved payout account**. Shows current provider, holder, number, owner and update time. Each opening fetches fresh details with private/no-store headers. **Refresh bank details** updates an already-open dialog. Failed requests clear old values and offer retry guidance.

No receiving account is collected again per payout. Admin still performs actual transfers through the existing banking arrangements; this feature does not initiate bank transfers or identify which account was used by an older transfer.

## Storage and access

`2026_11_09_080000_create_payout_accounts.php` creates the additive `payout_accounts` table. Unique `(owner_type, owner_id)` identifies one user, hub or driver. The details array is encrypted and excluded from default model serialization. Driver tokens and linked user logins resolve to the same driver record. Hub shares resolve to the hub account, rather than the personal account of a staff member named on the payout.

Authenticated `GET /api/remittances/payout-account` returns only the actor's editable records. `POST` validates all three fields and rejects foreign target keys. Website forms use the authenticated, verified equivalent `/remittances/payout-account`. Admin-only `GET /admin/payments/payout-account/{source}/{id}` resolves the actual order or return recipient. The normal recipient payout feed does not gain other workers' banking details.

The single migration was applied to the live database. Immediate read-only audits before and after matched existing deposits, allocations, original payouts and return workflow records. No real worker bank details were invented or saved for demonstration.

## Validation

- Seven isolated account tests cover saved edits, encryption, leading zeros, order/return reuse, staff/hub/company scoping, linked driver identity, inactive/foreign-user denial, invalid input and unchanged payment records.
- Backend regression selection: **153 passed, 1,103 assertions**, including return settlements, original escrow payments and all recipient receipt-confirmation paths. An additional website form-save assertion was checked separately after that run.
- Full rider app suite: **440 passed**, including bank-form persistence/reopening, hub choice, authentication, failure/retry, duplicate taps and keyboard/large-text layouts.
- Scoped Flutter analysis: **no issues found**.
- Browser checks passed at **320, 390, 768, 1280 and 1920 px**, including live dialog refresh after account changes, failed-load clearing/retry, safe text rendering and no horizontal overflow. Mobile and laptop screenshots were reviewed.

The current user-facing instructions are in [the UI guide](../../flows/return-refund-ui-guide.html#bank-details).

## Release verification

Rider/logistics **1.0.8 (build 10)** was built and published. The publication script verified package/version and signing-key continuity and privately backed up the previous installer. APK SHA-256: `d6158c60d3083abeab38ff7042f8a210ed4010cc387c07159dcacfdb0666b12a`. The full public HTTPS download matched this checksum; the landing page displayed the new version. The compiled app contains the saved-account API and Bank details control.

The wirelessly connected TECNO phone updated successfully in place and Android reports version 1.0.8/build 10. The saved-account editor was visible with its three fields and the keyboard, without overflow. No bank details or payments were submitted through device automation. The buyer installer remains 1.0.7/build 8.
