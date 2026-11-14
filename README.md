# INVOIZ E-Commerce, Logistics & Escrow Platform

An integrated multi-role e-commerce marketplace, Seller Center, logistics hub fulfillment network, and escrow platform built on Laravel 11 and Flutter.

---

## 📚 Documentation Index

Comprehensive documentation is available under [`docs/`](docs/):

* **[System Architecture](docs/ARCHITECTURE.md)**: High-level system architecture, subsystem boundaries, data models, entity relationships, and security model.
* **[Developer Guide](docs/DEVELOPER_GUIDE.md)**: Local environment setup, dependencies, running services, building mobile apps, and running test suites.
* **[Core Business Workflows](docs/CORE_WORKFLOWS.md)**: Detailed breakdown of the order lifecycle, escrow settlement, reverse fulfillment (returns & refunds parity), volumetric shipping, and hub transit.
* **[REST API Documentation](docs/API_DOCUMENTATION.md)**: REST API reference for mobile apps and integrations (authentication, products, cart, checkout, returns, and logistics rider APIs).
* **[Role, Auth & Authorization Reference](README-INTEGRATED.md)**: Authoritative access rules, guard configurations, OTP flows, and role segregation.
* **[Buyer Mobile Integration](docs/buyer-mobile-integration.md)**: Architecture and parity audit for the Flutter buyer client.
* **[Logistics Terminology & Role Mapping](docs/logistics-terminology-and-role-mapping.md)**: Operational hierarchy for hubs, dispatchers, and riders.

---

## 🚀 Quick Start (Local Development)

### Requirements
* PHP 8.2+ with PDO MySQL, Composer
* Node.js 18+ and npm
* MySQL 8.0+

### Setup Commands
From the project root:

```powershell
# 1. Install PHP and JS dependencies
composer install
npm ci

# 2. Configure environment (if not already done)
# cp .env.example .env
# php artisan key:generate

# 3. Build frontend assets
npm run build

# 4. Start local web server
php artisan serve --host=127.0.0.1 --port=8000
```

Open `http://127.0.0.1:8000/` in your browser. (The local Cloudflare tunnel snippet is available in `run.txt`).

---

## 📱 Mobile Applications

The platform includes two Flutter clients that connect directly to the shared Laravel REST API:

1. **Buyer Mobile App** (`buyer_mobile`):
   * 18+ screens covering search, product catalog, cart, checkout, order tracking, real-time chat, and return dispute filing.
   * **Web Preview**: Run `powershell -ExecutionPolicy Bypass -File buyer_mobile/scripts/Build-Web.ps1` to view at `http://127.0.0.1:8000/buyer-app/`.
   * **Build APK**: Run `powershell -ExecutionPolicy Bypass -File buyer_mobile/scripts/Build-Android.ps1`.
2. **Rider Mobile App** (`rider_mobile`):
   * Courier application with real-time pickup assignments, barcode scanning, proof-of-delivery capture (signature/photo), and return collections.

---

## 🏢 Platform Roles & Portals

All users share a unified identity schema while having strictly separated role views:

| Role | Portal / URL | Key Responsibilities |
| :--- | :--- | :--- |
| **Buyer** | `/`, `/shop`, `/buyer-app/` | Product search, purchasing, escrow payments (COD/GCash), delivery tracking, return/refund dispute submission. |
| **Seller** | `/seller/*` | Product management (weights, cm dimensions, variants), order fulfillment, voucher creation, return request review. |
| **Logistics** | `/logistics/*` | Hub package sorting, trip manifests, driver assignments, barcode parcel tracking. |
| **Rider** | Flutter App (`rider_mobile`) | Package pickup, last-mile delivery, proof of delivery upload, reverse-fulfillment collection. |
| **Admin** | `/admin/*` | Merchant approval, rider auditing, commission settings, dispute resolution, financial escrow release. |

---

## 🧪 Testing & Verification

Verify the system installation and shared backend health:

```powershell
php artisan invoiz:check-backend
```

Run isolated unit and feature regression tests:

```powershell
php vendor/phpunit/phpunit/phpunit --filter "ViewStructureTest|BuyerWebShoppingTest|CommerceRegressionTest|SharedBackendTest|BuyerMobileIntegrationTest|SellerProductShippingDimensionsTest"
```

To run the complete feature test suite against your dedicated MySQL test database:

```powershell
php artisan test -c phpunit.mysql.xml
```

---

## 📁 Repository Structure

```
c:\Invoiz\
├── app/                  # Laravel application, models, services, controllers
├── buyer_mobile/         # Flutter Buyer mobile application
├── rider_mobile/         # Flutter Rider mobile application
├── database/             # Schema migrations, seeders, and factories
├── docs/                 # System architecture, developer guides, workflow specs
├── public/               # Public web assets and compiled Vite bundles
├── resources/            # Blade templates and source styles
├── routes/               # Role-segmented routing (admin, buyer, seller, logistics, shared)
└── tests/                # Automated feature and regression test suite
```
