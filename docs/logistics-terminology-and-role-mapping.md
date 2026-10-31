# INVOIZ Logistics — Terminology and Role Mapping

Status: required pre-implementation deliverable for the logistics workflow review.
Date: 2026-10-31
Branch: `feature/logistics-rider-integration`

This document fixes one vocabulary for the INVOIZ logistics domain and maps every
term to the code that already implements it. It changes no schema, route, or model.
Implementation work follows this document; the document is updated first when a
term changes.

---

## 1. Canonical terms

| Term | Definition | Owns / responsible for | Canonical code identity |
|---|---|---|---|
| **Logistics Company** | The organization that operates logistics hubs and staff under the INVOIZ platform. | Hubs, drivers/staff, fleet, transfer operations inside its coverage. | `LogisticsCompany`, `logistics_companies`, `users.logistics_company_id` |
| **Logistics Hub** | A physical facility where parcels are received, sorted, stored, dispatched, or transferred. The standard name for the facility. | Receiving, sorting, holding, dispatch, transfer origin/destination. | `LogisticsCenter`, `logistics_centers`, `deliveries.center_id`, `deliveries.destination_center_id`, `hub_transfers.origin_center_id`, `hub_transfers.destination_center_id`, route prefix `centers.*` |
| **Sorting Operations** | An activity performed inside a Logistics Hub (receive → scan → verify → sort → stage for delivery or transfer). **Not** a company, facility, or table. | Verifying and grouping parcels; no separate custody identity. | `SortingCenterController` (read-only view), `sorting-center.index` / `sorting-center.export` routes, `deliveries.parcel_status` pipeline |
| **Logistics Admin** | The administrator authorized to manage one Logistics Company's operations (hubs, staff, parcels, transfers, reports). | Everything inside one company. | `users.role = admin` + `is_admin = 0` → `isCompanyManager()`; display label today: "Logistics Manager" |
| **Global Logistics Admin** | The single platform-designated account that oversees all companies' logistics operations. | Company-wide oversight; not a company employee. | `users.role = logistics_admin`, id `18`, `logistics_admin@test.com` → `isLogisticsAdmin()` |
| **Logistics Hub Staff** | Staff assigned to one Logistics Hub. | Receive, scan, sort, dispatch, escalate exceptions inside the assigned hub. | `users.role` in `staff` / `logistics_staff` + `center_id` → `isStaff()` |
| **Delivery Rider** | Two-wheel rider doing seller pickup and last-mile delivery. Never moves parcels between hubs. | Pickup, last-mile, proof of delivery, COD collection, rider remittances. | `users.role = rider` + `Rider` profile, `mobile/rider` Flutter app, `/logistics/rider/*` API |
| **Hub Transport Driver** | Driver of a four-wheel truck moving parcels between Logistics Hubs. | Loading, dispatch, arrival, receiving hand-off for a `HubTransfer`. | `HubTransportDriver`, `hub_transport_drivers`, Sanctum ability `hub-courier`, `/logistics/courier/*` API |
| **Platform Admin** | Marketplace administrator with platform-level oversight (escrow release, commission configuration, user management). | Platform-level functions; not hub operations. | `users.is_admin = 1` → `isAdmin()` |
| **Hub Transfer** | One fleet-based transport leg between two Logistics Hubs. | Custody movement; one record per leg. | `HubTransfer`, `hub_transfers`, `HubTransferService` |
| **Transfer Leg** | A single `HubTransfer` in a multi-hop route (A → B → C has two legs). | Dispatch, arrival, receiving, event history per leg. | `hub_transfers` rows + `hub_transfer_parcels` + `hub_transfer_events` |

### 1.1 Role count

The workflow spec names five operational roles. This system implements those five
plus two identities that already exist and must not be collapsed:

- **Global Logistics Admin** — platform-wide oversight (spec's "Logistics Admin"
  is the company-level role, which maps to the existing company manager).
- **Platform Admin** — marketplace administrator with escrow/commission powers.

No new user role string is introduced. Existing role strings and protected-account
rules in `app/Models/User.php` stay unchanged.

---

## 2. Sorting Center determination

The spec asks to inspect the existing "Sorting Center" before changing it. Evidence:

1. `app/Http/Controllers/Logistics/SortingCenterController.php` is documented as a
   "Standalone Sorting Center operational hub … Read-only aggregation over the
   existing parcel pipeline … every mutation posts to the existing `deliveries.*`
   routes, so no workflow logic is duplicated here."
2. `routes/logistics/web.php` exposes only `sorting-center.index` and
   `sorting-center.export` for it. There is no model, table, or migration named
   sorting center.
3. The data it reads is `deliveries.parcel_status` (`pending_arrival`, `received`,
   `scanned`, `sorted`, `dispatched`) and active `HubTransfer` rows already
   received by the hub.

**Decision:** Sorting Center is a *view of sorting operations inside a Logistics
Hub*, not an entity. Therefore:

- UI label becomes **Sorting Operations** (subtitle: "inside your Logistics Hub").
- Route names `sorting-center.*`, controller name, and file paths stay unchanged
  to avoid breaking the sidebar, dashboard, and export links.
- No table or model is deleted or renamed.

---

## 3. Terminology rename matrix (UI copy only)

Rules:
- Rename **visible copy** only (`aria-label`, `data-tip`, headings, helper text,
  button labels, flash messages, PDF/waybill copy).
- Keep **identifiers**: table names, column names, route *names*, route *paths*,
  controller/class names, JSON keys.
- Never rename "Seller Center", "Help Center", "Account Center", or other
  marketplace uses of "Center".

| Current copy | Target copy | Locations (file:line) |
|---|---|---|
| `Sorting Center` (sidebar label, aria, tooltip) | `Sorting Operations` | `resources/views/shared/navigation/sidebar.blade.php:390,392,399` |
| `Centers` (sidebar label, aria, tooltip) | `Logistics Hubs` | `resources/views/shared/navigation/sidebar.blade.php:511,518` |
| `Center Applications` | `Logistics Hub Applications` | `resources/views/shared/navigation/sidebar.blade.php:499,502,505` |
| `Sorting Center` (page title) | `Sorting Operations` | `resources/views/logistics/sorting-center/index.blade.php:5` |
| `Sorting center status filters` (aria) | `Sorting operations status filters` | `resources/views/logistics/sorting-center/index.blade.php:37` |
| `Move parcels between approved sorting centers.` | `Move parcels between approved Logistics Hubs.` | `resources/views/logistics/hub-transfers/index.blade.php:6` |
| `Origin sorting center` | `Origin Logistics Hub` | `resources/views/logistics/hub-transfers/create.blade.php:12` |
| `Destination sorting center` | `Destination Logistics Hub` | `resources/views/logistics/hub-transfers/create.blade.php:13` |
| `Handed to Center:` | `Handed to Logistics Hub:` | `resources/views/logistics/deliveries/show.blade.php:662` |
| `Picked from Center:` | `Picked from Logistics Hub:` | `resources/views/logistics/deliveries/show.blade.php:668` |
| `Sorting Center` (event badge) | `Hub Sorting` | `resources/views/logistics/deliveries/show.blade.php:743`, `resources/views/logistics/tracking/show.blade.php:367` |
| `…returned to the sorting center …` | `…returned to the Logistics Hub …` | `resources/views/logistics/deliveries/show.blade.php:1218`, `resources/views/seller/orders/show.blade.php:253` |
| `Where the physical parcel is inside the sorting center.` | `Where the physical parcel is inside the Logistics Hub.` | `resources/views/logistics/tracking/show.blade.php:146` |
| `Scan at sorting center` | `Scan at Logistics Hub` | `resources/views/seller/orders/waybill.blade.php:122` |
| `Sorting center ownership` | `Logistics Hub ownership` | `resources/views/logistics/companies/index.blade.php:8` |
| `Awaiting sorting or dispatch` | unchanged (describes an activity) | `resources/views/logistics/partials/dashboard-content.blade.php:41` |
| `Center` / `Centers` in `logistics/centers/*` forms and tables | `Logistics Hub` / `Logistics Hubs` | `resources/views/logistics/centers/{index,create,edit,show}.blade.php` |
| `Center` in delivery/booking filters and rider assignment copy | `Logistics Hub` | `resources/views/logistics/deliveries/*`, `logistics/pickup-requests/*`, `logistics/riders/*`, `logistics/staff/*`, `logistics/service-areas/*`, `logistics/reports/*`, `logistics/tracking/*` |

The full visible-string inventory is 171 matches across 49 Blade files
(`grep -rniE '(aria-label|data-tip|placeholder)="[^"]*center|>[^<>]*cent(er|re)[^<>]*<' resources/views`).
Each match is classified as logistics (rename), marketplace (leave), or code
identifier (leave) during implementation. The table above lists every
"Sor(ting) center" occurrence in the current tree.

---

## 4. Status vocabulary

### 4.1 Delivery status (rider-facing pipeline)

`waiting_for_rider` → `assigned` → `accepted` → `going_to_pickup` →
`arrived_at_shop` → `picked_up` → `out_for_delivery` → `arrived_at_customer` →
`delivered`; exceptions: `delivery_failed`, `lost`, `damaged`; terminal:
`cancelled`. Defined in `app/Models/Delivery.php`.

### 4.2 Parcel status (hub custody pipeline)

`pending_arrival` → `received` → `scanned` → `sorted` → `dispatched`. This is the
physical-custody axis used by Sorting Operations. It is independent of delivery
status and of transfer status.

### 4.3 Hub transfer status

`pending` → `assigned` → `loading` → `ready_for_dispatch` → `in_transit` →
`arrived` → `receiving` → `received`; plus `cancelled` and `exception`.
`HubTransfer::ACTIVE_STATUSES` covers every state that still owns custody.
Parcel row: `loading_status` (`loaded` / `removed`), `receiving_status`
(`pending` / `received` / `missing`), `condition`.

### 4.4 Custody rule

While a parcel is linked to a transfer in `ACTIVE_STATUSES` and not `removed`,
custody is with the transfer. Last-mile assignment is blocked by
`FulfillmentWorkflow::hasActiveHubTransfer()`. Arrival (`arrived`) does not
transfer custody; only per-parcel receiving (`receiving_status = received`) does.

---

## 5. Role → permission summary

| Capability | Platform Admin | Global Logistics Admin | Logistics Admin (company manager) | Hub Staff | Delivery Rider | Hub Transport Driver |
|---|---|---|---|---|---|---|
| Manage logistics companies | yes | yes | no | no | no | no |
| Create/edit Logistics Hubs | yes | yes | own company | no | no | no |
| Manage hub staff | yes | yes | own company | no | no | no |
| View all companies' parcels | yes | yes | no | no | no | no |
| View own company's hubs/parcels | yes | yes | yes | own hub only | no | no |
| Receive / scan / sort parcels | yes (read) | yes (read) | yes | own hub | no | no |
| Assign pickup / last-mile rider | no (platform) | oversight | yes | yes, own hub | no | no |
| Create transfer manifest | yes | yes | yes | yes, own hub | no | no |
| Confirm manifest / dispatch transfer | yes | yes | yes | yes, own hub | no | no |
| Confirm destination receiving | yes | yes | yes | destination hub staff | no | no |
| Report missing/damaged, hold, correction | oversight | oversight | yes | yes, own hub | own deliveries | transfer parcels |
| Record pickup / delivery / COD | no | no | no | no | own assignments | no |
| Accept rider remittance | oversee | oversee | yes | yes, own hub | own remittance | no |
| Release escrow payout / configure commission | yes | no | no | no | no | no |
| View own transfer trips | no | no | no | no | no | own trips only |
| View own earnings | no | no | no | no | rider earnings only | transfer earnings only |

Scoping source of truth: `app/Support/LogisticsAccess.php` (`companyId()`,
`centerId()`), `FulfillmentWorkflow` guards, and per-request ownership checks in
`HubCourierController` (company + truck) and the rider API (own rider id).

---

## 6. Automation vs manual control

| Step | Automated | Manual (who) |
|---|---|---|
| Identify origin/destination hub from service area | `Deliveries` service-area resolution | Correct destination hub (Logistics Admin, Hub Staff) |
| Choose same-hub vs transfer | `EscrowEarnings::resolveHubRoute()` and transfer eligibility | Override route before dispatch (Logistics Admin) |
| Status updates after valid actions | Controllers + `HubTransferService` | Authorized correction with reason (Admin/Staff) |
| Transfer record + events | `HubTransferController@store`, `HubTransferService::event()` | Confirm manifest, dispatch, cancel (Logistics Admin, Hub Staff) |
| Truck/driver assignment | Suggests from available fleet | Select/replace truck or driver (Logistics Admin) |
| Custody update | On per-parcel receiving only | Physical receipt confirmation (destination Hub Staff) |
| Last-mile eligibility | Blocked during active transfer (`FulfillmentWorkflow`) | Assign rider after receiving (Logistics Admin, Hub Staff) |
| Fee/earnings allocation | `EscrowEarnings::calculateForSeller()` | None — configuration only via Admin commission page |
| Duplicate prevention | Unique keys, `firstOrCreate`/`updateOrCreate`, status guards | — |
| Missing/damaged | Transfer status `exception`, parcel `condition` | Report + resolve (Hub Staff, Logistics Admin) |

Manual mutations are recorded through the existing audit path (`AuditLogger` /
`HubTransferEvent`) where available; gaps are listed in the final report.

---

## 7. API and Flutter terminology

- **Delivery Rider API** (`/logistics/rider/*`): only pickup and last-mile data.
  Verified it returns no `HubTransfer`, truck, or inter-hub records. Labels use
  "Logistics Hub" where a facility is meant.
- **Hub Transport Driver API** (`/logistics/courier/*`): separate Sanctum
  authenticatable (`HubTransportDriver`). Human-readable copy should say
  "Hub Transport Driver"; the `courier` route segment and `courier` JSON key are
  retained because an external driver client may already consume them, and no
  client exists in this repository to update. This is recorded as a known
  terminology exception.
- **Flutter Rider App** (`mobile/rider`): no hub-transfer or truck surface. No
  Hub Transport Driver UI is added to it.
- **Web logistics UI**: canonical copy per the rename matrix in §3.

---

## 8. Escrow integration boundary

The leader's escrow semantics (seven recipients, configurable commission,
Admin Payments page, Release Payout, commission configuration) are the contract.
This branch's allocation engine (`app/Support/EscrowEarnings.php`) is fleet-aware
and is the sole writer for:

- `escrow_remittances` (statuses `held_in_escrow`, `pending_remittance`,
  `remitted`, `cancelled`),
- `hub_transfer_earnings` (one row per delivery + `inter_hub_driver`),
- `payment_transactions` fee columns and `transactions` detail columns.

The leader's Admin escrow UI and release actions are ported into this branch and
call this engine. The leader migration's `deliveries.origin_center_id`,
`deliveries.inter_hub_rider_id`, and `deliveries.inter_hub_dispatched_at`
columns are **not** adopted: fleet transfer legs are the authoritative route
record.

---

## 9. Open decisions requiring approval

1. Renaming the display label `Logistics Manager` → `Logistics Admin` for
   `role = admin` (company manager). Touches `User::roleLabel()`, report copy,
   and any saved reports that embed the label.
2. Renaming route names/paths (`centers.*`, `sorting-center.*`, `courier.*`).
   Not recommended; breaks bookmarks and clients.
3. Whether "Center Applications" should become "Logistics Hub Applications"
   in emails sent to applicants (currently branded "Center").
4. Whether to build a dedicated Hub Transport Driver mobile client. Not done
   here; the API exists and is driver-scoped.

---

## 10. Verification

- UI copy: `grep -rni "sorting cent" resources/views` returns only code
  identifiers (route names, classes, file paths), no visible copy.
- No schema change from terminology work.
- No change to `routes/logistics/web.php` route names.
- Rider API payload scan confirms no `HubTransfer` / `truck` / `inter_hub` keys.
- Role scoping covered by `LogisticsAllocationReconciliationTest` and the
  company/center isolation tests.
