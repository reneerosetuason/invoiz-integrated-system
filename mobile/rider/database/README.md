# Invoiz Database Configuration

## Connection Details

| Setting       | Value                    |
|---------------|--------------------------|
| DB Engine     | MySQL 8.0                |
| Host          | 127.0.0.1 (localhost)    |
| Port          | 3306                     |
| Database      | invoizdb                 |
| Username      | root                     |
| Password      | 1234                     |

## API Server (Laravel)

| Setting       | Value                              |
|---------------|-------------------------------------|
| Framework     | Laravel                             |
| PHP           | C:\php\php.exe                      |
| Dev URL       | http://localhost:8000/api           |
| LAN URL       | http://192.168.1.22:8000/api        |
| Source        | C:\Users\ilaga\logistics-web        |

## How the Driver_app Connects

```
Phone (USB)  ──Wi-Fi──>  PC (192.168.1.22:8000)  ──>  Laravel API  ──>  MySQL (invoizdb)
Emulator     ──virtual──>  PC (10.0.2.2:8000)     ──>  Laravel API  ──>  MySQL (invoizdb)
Desktop      ──local──>   PC (localhost:8000)      ──>  Laravel API  ──>  MySQL (invoizdb)
```

## Phone Setup (USB)

1. Phone and PC must be on the same Wi-Fi network
2. Laravel server must bind to `0.0.0.0` (not just `127.0.0.1`)
3. In `AppConfig`, the `devHost` default is for emulator (`10.0.2.2`)
4. For physical phone, run with:
   ```
   flutter run -d <device> --dart-define=API_BASE_URL=http://192.168.1.22:8000/api
   ```

## Tables (46 total)

### Rider App Tables (primary)
- `riders` - Rider profiles, vehicles, verification
- `rider_applications` - Rider registration applications
- `rider_application_documents` - Uploaded documents for applications
- `rider_application_logs` - Application status change history
- `rider_earnings` - Delivery earnings per rider
- `rider_locations` - GPS location tracking
- `rider_notifications` - Rider-specific notifications
- `vehicle_types` - Available vehicle type definitions

### Delivery Tables
- `deliveries` - Core delivery records with full workflow status
- `delivery_items` - Items in each delivery
- `delivery_status_logs` - Status change audit trail
- `delivery_proofs` - Photo/signature/OTP proof of delivery
- `delivery_failures` - Failed delivery reports

### E-commerce Tables (shared with Seller/Buyer apps)
- `users` - All users (admin, seller, buyer, rider roles)
- `orders` - Customer orders
- `order_items` - Items per order
- `products`, `product_variants`, `product_images`
- `categories`, `sellers`, `addresses`
- `payments`, `vouchers`, `order_vouchers`
- `carts`, `cart_items`
- `conversations`, `messages`
- `logistics_conversations`, `logistics_messages`, `logistics_message_attachments`
- `logistics_settings`, `notifications`
- `reviews`, `favorites`, `store_follows`

### System Tables
- `migrations`, `sessions`, `jobs`, `job_batches`, `failed_jobs`
- `personal_access_tokens`, `password_reset_tokens`

## Database Updates (Additive Only)

> All changes are **additive only** — no DROP/RENAME/DELETE.  
> See `updates/CHANGELOG.md` for what was added and how to verify in MySQL Workbench.  
> Latest: `updates/2026_08_27_rider_app_integration.sql` (submitted_via column + 3 indexes, all verified on `invoizdb`).

## Schema Files

- `schema.sql` - Full MySQL schema dump (no data, includes routines/triggers)

## SQL Queries for Rider App

### Get rider by email (for login)
```sql
SELECT r.*, u.id AS user_id
FROM riders r
LEFT JOIN users u ON u.id = r.user_id
WHERE r.email = 'rider@invoiz.test';
```

### Get active deliveries for rider
```sql
SELECT d.*, dsl.status AS last_status
FROM deliveries d
LEFT JOIN delivery_status_logs dsl ON dsl.delivery_id = d.id
  AND dsl.id = (SELECT MAX(id) FROM delivery_status_logs WHERE delivery_id = d.id)
WHERE d.rider_id = ?
  AND d.status NOT IN ('delivered', 'delivery_failed', 'cancelled')
ORDER BY d.assigned_at DESC;
```

### Get rider earnings summary
```sql
SELECT
  COUNT(*) AS total_deliveries,
  SUM(amount) AS total_earnings,
  SUM(CASE WHEN earned_on = CURDATE() THEN amount ELSE 0 END) AS today_earnings
FROM rider_earnings
WHERE rider_id = ?;
```

### Get rider notifications (unread count)
```sql
SELECT COUNT(*) AS unread_count
FROM rider_notifications
WHERE rider_id = ? AND is_read = 0;
```
