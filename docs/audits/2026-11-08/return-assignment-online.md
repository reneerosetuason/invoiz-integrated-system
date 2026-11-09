# Online availability for return/refund assignments

Return pickup riders, riders delivering the item back to the seller, replacement riders and inter-hub return drivers must be online before logistics assigns them.

The rider selectors list only online, approved, verified riders with active accounts from the relevant hub. When none are available, the page tells logistics to ask a rider to go online and refresh. The inter-hub selector also excludes offline drivers from the return collection company's driver list.

Availability is rechecked under a database row lock when the assignment is submitted. Keeping an old browser tab open or posting a rider ID directly cannot bypass an offline status. A rejected assignment leaves the parcel stage and its earning recipient unchanged.

The rider check also applies when the configured rider service fee is zero, so the absence of an earning allocation cannot bypass eligibility. Going offline after assignment does not block the assigned rider from confirming an existing handoff, and does not cancel or reprice the work. Existing funding, hub, approval, custody and recipient-acceptance requirements remain in place.

Implementation:

- `ReturnSettlementService::riderForAssignment()` locks and validates the selected rider, including `is_online`.
- `ReturnWorkflowService` uses this check for pickup assignment and dispatch back to the seller, independently of fee allocations.
- Return earning-recipient binding and replacement dispatch reuse the same validation.
- Return inter-hub driver binding locks and checks the driver's online flag before updating the allocation.
- The shared `rider-select` partial is used by return pickup, seller delivery and both replacement interfaces.

Tests cover online/offline transitions after listing, rejected HTTP assignment requests, unchanged custody/payee after rejection, successful online assignments, later offline handoffs, replacement dispatch, inter-hub dispatch and zero-priced services. Legacy test schemas include the deployed workflow fields so results do not depend on Eloquent's process-wide column cache.

This online-eligibility change itself needed no database migration or APK rebuild. A later [return-dispatch and mobile-visibility update](return-dispatch-and-mobile-jobs.md) adds explicit driver workflow fields and rider app 1.0.5 (build 7). The existing Go Online controls remain in use. No live rider was forced online or offline, and no existing return assignment or payment record was changed by this eligibility update.

Validation: **179 tests / 1,337 assertions passed**, covering the return workflows, original payment/escrow behavior, seller visibility, buyer mobile integration and original last-mile routing scope. Results are recorded in `.tools/return-online-assignment-final-tests.log`; the user-facing instructions are in the [return/refund UI guide](../../flows/return-refund-ui-guide.html#logistics).
