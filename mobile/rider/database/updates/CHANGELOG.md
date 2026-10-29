# Database Updates — Rider App Integration

> **SHARED DATABASE:** `invoizdb` — same MySQL database as Logistics Web.  
> **Rule:** Additive only. No DROP/RENAME/DELETE of existing tables or columns.

---

## 2026-08-27 — Rider Mobile App Integration

**File:** `updates/2026_08_27_rider_app_integration.sql`  
**Applied to:** `invoizdb` (MySQL 8.0, localhost:3306)  
**Status:** ✅ Applied on MySQL Workbench — verified with `SHOW COLUMNS` / `SHOW INDEX`

### What was added (additive, safe)

| # | Table | Change | Why | Risk |
|---|-------|--------|-----|------|
| 1 | `rider_applications` | New column `submitted_via` ENUM('web','mobile') DEFAULT 'web' | Distinguish Mobile vs Web applications for Logistics admin audit | None — existing rows default to 'web' |
| 2 | `rider_applications` | Index `idx_rider_applications_email` on `email` | Faster Rider App status lookup by email | None — read-only speedup |
| 3 | `rider_applications` | Index `idx_rider_applications_status` on `status` | Faster filtering pending/approved/rejected | None |
| 4 | `deliveries` | Index `idx_deliveries_rider_status` on `(rider_id, status)` | Faster polling of assigned deliveries | None |

### What was REUSED (no new tables)

All Rider App features reuse existing tables — **no duplicate Rider DB**:

- `riders`, `riders`←`users` (rider profile, vehicle, is_online, is_verified)
- `rider_applications` + `rider_application_documents` + `rider_application_logs` (Apply flow)
- `vehicle_types` (vehicle selection)
- `deliveries` + `delivery_items` + `delivery_status_logs` + `delivery_proofs` + `delivery_failures` (Delivery workflow)
- `rider_earnings`, `rider_locations`, `rider_notifications` (Earnings, tracking, notifications)
- `logistics_conversations`, `logistics_messages`, `logistics_message_attachments` (Messages Rider↔Logistics/Seller/Buyer)
- `users`, `orders`, `sellers`, `personal_access_tokens` (Auth, shared entities)

### How to verify in MySQL Workbench

```sql
-- Check new column
SHOW COLUMNS FROM rider_applications LIKE 'submitted_via';

-- Check new indexes
SHOW INDEX FROM rider_applications WHERE Key_name LIKE 'idx_rider%';
SHOW INDEX FROM deliveries WHERE Key_name = 'idx_deliveries_rider_status';

-- Existing shared tables still intact (no deletes)
SHOW TABLES; -- should still show 46 tables
```

### Team Notes

- **For Logistics Web:** You can now display `submitted_via` badge in `rider-applications/show.blade.php` if desired. Mobile apps will send `submitted_via=mobile` (optional param, defaults to web).
- **For Buyer/Seller teams:** No impact — no changes to `users`, `orders`, `products`, `carts` etc.
- **Rollback:** To undo, run `ALTER TABLE rider_applications DROP COLUMN submitted_via;` and `DROP INDEX idx_...` — but not needed.

---

## 2026-08-31 — Rider Account Provisioning

**File:** `updates/2026_08_31_rider_account_provisioning.sql`  
**Applied to:** `invoizdb` (MySQL 8.0, localhost:3306)  
**Status:** ✅ Applied via `php artisan migrate --force` (also available as standalone SQL)

### What was added (additive, safe)

| # | Table | Change | Why | Risk |
|---|-------|--------|-----|------|
| 1 | `rider_applications` | New column `center_id` (FK → `logistics_centers.id` ON DELETE SET NULL) | Link an approved application to the assigned Logistics center | None |
| 2 | `rider_applications` | New column `service_area_id` (FK → `service_areas.id` ON DELETE SET NULL) | Link an approved application to the assigned service area | None |
| 3 | `rider_applications` | New column `approved_by` (FK → `users.id` ON DELETE SET NULL) | Record which admin approved the application | None |
| 4 | `rider_applications` | New column `provisioned_at` TIMESTAMP NULL | Timestamp when the rider account was provisioned | None |

### What this feature does (Logistics Web)

- Adds an admin **review UI** (`rider-applications.index` / `.show`) to list pending applications.
- On **approve** (admin sets the rider's login password + center/area), `App\Services\RiderAccountProvisioner::approve()` atomically (single `DB::transaction`):
  - creates/updates the `users` row (`role='rider'`, `status='active'`, hashed login password),
  - creates/links the `riders` row (`user_id`, center, service area, verification flags),
  - marks the application `approved` + writes an `rider_application_logs` audit row + a `notifications` row.
- On **reject**, marks the application `rejected` + audit log + notification (no account created).
- This is the proper path that produces a working rider login (`email` + admin-set password) — the replacement for the missing `rider@invoiz.test` live account.

### How to verify

```sql
SHOW COLUMNS FROM rider_applications LIKE 'center_id';
SHOW COLUMNS FROM rider_applications LIKE 'service_area_id';
SHOW COLUMNS FROM rider_applications LIKE 'approved_by';
SHOW COLUMNS FROM rider_applications LIKE 'provisioned_at';
```

### Team Notes

- **For Logistics Web:** New admin routes group `rider-applications.*` (admin-only). View files: `resources/views/rider-applications/{index,show}.blade.php`; sidebar link added (admin only).
- **For Rider team:** No impact — provisioning only adds a system path; the existing mobile `store()`/`status()` API is unchanged. Login uses the same `users.email` + `Hash::check` flow.
- **Rollback:** Not needed — all columns additive; `down()` in the migration drops the FKs then the columns.

---

## Earlier Snapshots

- `../schema.sql` — Full `mysqldump --no-data` of `invoizdb` (46 tables) as of 2026-08-27.
- `../test_rider_account.sql` — Test rider `rider@invoiz.test` / `password` for login testing.

---

## How to add future updates

1. Create `updates/YYYY_MM_DD_description.sql` with **only** `ALTER TABLE ... ADD ...` or `CREATE INDEX ...` (no DROP).
2. Document it here in this CHANGELOG.
3. Run it once in MySQL Workbench on `invoizdb`.
4. Commit both `.sql` and this `.md` so all members see the change.
