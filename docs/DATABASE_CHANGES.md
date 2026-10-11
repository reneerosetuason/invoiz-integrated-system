# Invoize — Rider App Database Changes

> **Scope:** `invoizdb` (MySQL 8)
> **Date:** 2026-08-19
> **SQL file:** [`database/changes/2026_08_19_invoize_rider.sql`](../database/changes/2026_08_19_invoize_rider.sql)
>
> These changes add the **rider delivery system** on top of the existing logistics
> schema. Run the migrations (`php artisan migrate`) to apply them, or use the SQL
> file above for manual application.

---

## New columns

### `riders` (rider profile)

| Column      | Type      | Default | Purpose                                   |
|-------------|-----------|---------|-------------------------------------------|
| `is_online` | boolean   | `false` | Rider availability switch (online/offline) |
| `avatar`    | varchar   | null    | Profile photo path (stored in `storage/`)  |

> Existing riders are linked to `users` accounts (`riders.user_id`) with
> `role = 'rider'` so they can log in to the mobile app.
> **Seeder logins** (password = `password`):
> - `ahmad@riders.com`
> - `siti@riders.com`
> - `ali@riders.com`
> - `fatimah@riders.com`
> - `hassan@riders.com`

### `deliveries` (delivery / shipment)

The status enum is extended from the old 6-state courier flow to the full rider
workflow. `sender_*` = **shop**, `recipient_*` = **customer**.

| Column             | Type        | Purpose                                              |
|--------------------|-------------|------------------------------------------------------|
| `status` (enum)    | varchar     | `waiting_for_rider, assigned, accepted, going_to_pickup, arrived_at_shop, picked_up, out_for_delivery, arrived_at_customer, delivered, delivery_failed, cancelled` |
| `payment_method`   | varchar     | `cash_on_delivery`, `gcash`, `bank_transfer`          |
| `amount_to_collect`| decimal(12,2)| COD amount the rider must collect                    |
| `delivery_fee`     | decimal(12,2)| Rider earnings per delivery                          |
| `pickup_pin`       | varchar(10) | Optional pickup verification PIN                     |
| `sender_lat/lng`   | decimal(10,7)| Shop coordinates                                    |
| `recipient_lat/lng`| decimal(10,7)| Customer coordinates                                |
| `accepted_at`      | timestamp   | When rider accepted                                  |
| `cancelled_at`     | timestamp   | When cancelled                                       |
| `failed_at`        | timestamp   | When delivery failed                                 |
| `failure_reason`   | varchar     | Failure reason text                                  |

> **Multi-shop orders:** one `orders` row may produce **one `deliveries` row per
> shop** (each with its own tracking number and rider). `deliveries.order_id`
> groups them back to the parent order. The rider only sees deliveries where
> `deliveries.rider_id = <their rider id>`.

---

## New tables

### `delivery_items`
Order line items carried on a delivery (snapshot of product name / variant /
qty / price), so the rider can verify the package at pickup.

| Column          | Type           | Purpose              |
|-----------------|----------------|----------------------|
| `delivery_id`   | FK → deliveries | Owning delivery     |
| `name`          | varchar        | Product name         |
| `variant_label` | varchar, null  | Variant (e.g. size)  |
| `quantity`      | int unsigned   | Quantity             |
| `price`         | decimal(12,2)  | Unit price           |

### `delivery_proofs`
Proof of delivery (photo / signature / OTP + GPS + timestamp).

| Column           | Type           | Purpose                          |
|------------------|----------------|----------------------------------|
| `delivery_id`    | FK → deliveries | Delivery                       |
| `rider_id`       | FK → riders    | Rider who completed it           |
| `type`           | varchar        | `photo`, `signature`, `otp`      |
| `file_path`      | varchar, null  | Stored proof photo               |
| `signature_name` | varchar, null  | Customer name (signature)        |
| `otp`            | varchar, null  | Customer OTP                     |
| `latitude/longitude` | varchar  | GPS at delivery                  |
| `verified_at`    | timestamp      | Verification time                |

### `delivery_failures`
Failed-delivery reports.

| Column       | Type           | Purpose                    |
|--------------|----------------|----------------------------|
| `delivery_id`| FK → deliveries | Delivery                 |
| `rider_id`   | FK → riders    | Rider who reported         |
| `reason`     | varchar        | Failure reason (required)  |
| `notes`      | text, null     | Rider notes                |
| `reported_at`| timestamp      | Report time                |

### `rider_earnings`
Rider compensation ledger (authoritative earnings source — never computed in the app).

| Column       | Type           | Purpose                  |
|--------------|----------------|--------------------------|
| `rider_id`   | FK → riders    | Rider                    |
| `delivery_id`| FK → deliveries, null | Source delivery   |
| `type`       | varchar        | `delivery`, `bonus`, `adjustment` |
| `amount`     | decimal(12,2)  | Amount earned            |
| `earned_on`  | date           | Earning date             |
| `description`| text, null     | Description              |

### `rider_locations`
Periodic GPS pings for live rider tracking.

| Column       | Type           | Purpose                  |
|--------------|----------------|--------------------------|
| `rider_id`   | FK → riders    | Rider                    |
| `delivery_id`| FK → deliveries, null | Active delivery  |
| `latitude` / `longitude` | decimal(10,7) | Coordinates |
| `recorded_at`| timestamp      | Ping time                |

> Pings are only accepted while `riders.is_online = true`.

### `rider_notifications`
In-app notifications for riders (push/FCM-ready).

| Column       | Type           | Purpose                    |
|--------------|----------------|----------------------------|
| `rider_id`   | FK → riders    | Rider                      |
| `type`       | varchar        | `delivery`, `earnings`, `system`, `announcement` |
| `title` / `body` | varchar / text | Notification content   |
| `data`       | json, null     | Extra payload (e.g. delivery id) |
| `is_read`    | boolean        | Read state                 |

---

## API endpoints (added in `routes/api.php`)

| Method | Endpoint                                  | Purpose                    |
|--------|-------------------------------------------|----------------------------|
| POST   | `/api/login`                              | Rider login (token)        |
| POST   | `/api/logout`                             | Revoke token               |
| GET    | `/api/user`                               | Current user               |
| GET    | `/api/rider/profile`                      | Rider profile              |
| PATCH  | `/api/rider/profile`                      | Update phone / vehicle     |
| PATCH  | `/api/rider/status`                       | Online / offline toggle    |
| GET    | `/api/rider/dashboard`                    | Stats + current delivery   |
| GET    | `/api/rider/deliveries`                   | Delivery list (filterable) |
| GET    | `/api/rider/deliveries/{id}`              | Delivery detail            |
| POST   | `/api/rider/deliveries/{id}/accept`       | Accept assignment          |
| PATCH  | `/api/rider/deliveries/{id}/status`       | Advance workflow step      |
| POST   | `/api/rider/deliveries/{id}/pickup`       | Confirm pickup (PIN)       |
| POST   | `/api/rider/deliveries/{id}/complete`     | Complete + proof + COD     |
| POST   | `/api/rider/deliveries/{id}/failed`       | Report failed delivery     |
| POST   | `/api/rider/location`                     | Send GPS ping              |
| GET    | `/api/rider/earnings`                     | Daily / weekly / monthly   |
| GET    | `/api/rider/history`                      | Delivery history           |
| GET    | `/api/rider/notifications`                | Notifications              |
| PATCH  | `/api/rider/notifications/{id}/read`      | Mark one read              |
| PATCH  | `/api/rider/notifications/read-all`       | Mark all read              |

**Delivery state machine (enforced on the backend):**

```
assigned → accepted → going_to_pickup → arrived_at_shop → picked_up
        → out_for_delivery → arrived_at_customer → delivered

assigned / accepted  → cancelled          (rejection path)
picked_up/out_for_delivery/arrived_at_customer → delivery_failed
```

Any other transition returns `409 Conflict`. Cross-rider access returns `403`.