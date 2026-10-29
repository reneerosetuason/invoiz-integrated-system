# Seller return visibility and phone connection — 29 October 2026

The local `return_requests` table contained zero records before and after this
work. The seller page had nothing saved to display; no ownership mismatch or
missing return-table columns was found. Opening the mobile return dialog does
not submit it. A buyer must send the request successfully, then refresh the
correct store's Returns page. A failed or uncertain network submission should
be checked by refreshing buyer order details before retrying.

Added `tests/Feature/SellerReturnVisibilityTest.php`: two isolated SQLite-memory
tests with 25 assertions passed. They submit through the real buyer API, load
the owning seller's real web page, verify another seller cannot see or approve
the request, approve as the owner, and read that decision back through buyer
order details. The fixture deliberately separates seller record and user IDs.
Unsubmitted/invalid requests remain absent. No live return was created and no
refund, order, inventory or seller data was changed. No application return
logic was changed based on an unproven ownership theory. The broader workflow
and accounting findings in `return-refund-flow-review.md` remain outstanding.

The phone's wireless debugging IP and port changed during reconnection. Restored
the authorized TECNO KI7 connection and reverse mapping for port 8000, then
opened the existing INVOIZ app without reinstalling or clearing its data.

Added `buyer_mobile/scripts/Connect-Phone.ps1` and instructions in
`buyer_mobile/README.md`. The helper:

- Uses an authorized USB/ADB device or an explicitly supplied wireless IP/port.
- Can resolve a paired phone's mDNS identity when its connection port changes.
- Remembers the selected phone identity only in Git-ignored
  `buyer_mobile/.dart_tool/invoiz-phone.json`; later automatic discovery targets
  that identity, not unrelated phones on the network.
- Requires an explicit selection when multiple devices are ambiguous.
- Checks the running shared Laravel backend, restores
  `adb reverse tcp:8000 tcp:8000`, and verifies HTTP 200 **from Android** before
  opening INVOIZ. Holds netcat input briefly so Android does not exit before the
  HTTP response, avoiding a false connection failure.
- Does not start a new backend, rebuild/install the app, clear app data or alter
  `.env`. This development APK uses `http://127.0.0.1:8000/api` through ADB.

Verified the real phone connection using both explicit device selection and a
subsequent default helper run. Android received HTTP 200 from `/api/categories`,
INVOIZ was running, and its own recent log contained zero Flutter/fatal startup
errors. PowerShell/PHP syntax and `git diff --check` passed. USB-specific behavior
was documented but not exercised with a physical USB cable in this session.

No commit was made. Read-only routing inspection is in
`%TEMP%/invoiz-seller-returns-phone-20261029`.
