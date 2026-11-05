# Manual GCash receipts at checkout

Buyers can select GCash on the website checkout, attach a JPG, PNG or WebP
receipt up to 5 MB, and place the order. The selected receipt has a local
preview. Selecting COD clears and disables the upload. Validation errors keep
the payment choice; browsers require buyers to select the file again.

Sellers open an order and use **Payment & Delivery → View buyer's GCash
receipt**. Buyers can also open their receipt from their order details.
Receipts are private files, served through order ownership checks, and their
storage paths are hidden from JSON responses. Uploading a receipt leaves the
payment pending; this feature does not verify a transfer or call a GCash gateway.
The existing order/payment lifecycle remains responsible for settlement.

Single-item, store, selected-cart, and full-cart checkout share the payment
logic. A checkout containing multiple stores attaches the same uploaded
receipt to each resulting order; the checkout explicitly tells buyers this.
GCash is recorded on the payment, checkout ledger, and shipment, with zero cash
to collect from the buyer. Failed checkouts roll back database changes and
remove the new receipt. Committed API orders retain their receipt even if
loading the response subsequently fails.

The shared checkout API also accepts multipart requests with
`payment_method=gcash` and `gcash_proof`; the authenticated buyer can retrieve
the receipt at `/api/orders/{order}/payment-proof`. No Flutter screens were
changed in this task.

## Database change

Applied only `2026_11_04_120000_add_proof_path_to_payments_table` to the configured
local `invoizdb` database. It adds nullable `payments.proof_path`, without
rewriting existing payments. Before/after metadata snapshots show the same
98 tables and unchanged business record counts; only the migration history
count increased. Existing payment enums already support GCash.

Created a full backup before applying the migration:

```text
storage/app/private/logistics-before-repair-20261104-111330.sql
SHA-256: 82084ea25b0f0608c332301241b0682f807ad7978c3b11c243f590dbfb0c0b64
```

The `.env` and PHP configuration were unchanged. This PHP runtime allows 8 MB
per uploaded file and 40 MB per request, sufficient for the 5 MB receipt limit.

## Validation

- `GcashPaymentProofTest` and `CheckoutVoucherScopeTest`: 20 tests passed,
  165 assertions, using in-memory SQLite and private fake storage. Coverage
  includes upload validation, pending payment status, ledger/shipment method,
  buyer/seller ownership, denied guest/public access, rollback cleanup,
  multi-store attachment, API path privacy, and existing voucher behavior.
- Chromium exercised the actual rendered checkout with isolated fixtures at
  390 px and 1280 px: payment switching, required upload, invalid-file feedback,
  preview, COD reset, no horizontal overflow, and no page errors. Preview
  artifacts and the browser script are in the ignored `.tools` directory.
- Modified PHP files and the new checkout JavaScript passed syntax checks;
  all Blade views compiled successfully; `git diff --check` passed.
- A broader `BuyerWebShoppingTest` run found two failures in unchanged
  cart/variation behavior: `test_add_to_cart_repeats_and_keeps_variations_separate`
  and `test_product_keeps_add_button_after_first_cart_addition`. These were not
  expanded into unrelated cart changes. The installed runner also emits an
  existing PHP 8.5 dependency deprecation.

No test orders or receipt uploads were created in the real application
database. The migration was applied locally; other deployments need that
specific migration before using the feature.
