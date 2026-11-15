# INVOIZ E-Commerce, Logistics & Escrow Platform

## LAB ACT 3: CI/CD

The existing Laravel/Blade application is the laboratory target. Buyer and rider
Flutter apps continue to use the same backend; this pipeline does not rebuild mobile apps.
All supporting laboratory files are organized in [`devops/lab-act-3/`](devops/lab-act-3/README.md).

The new [GitHub Actions workflow](.github/workflows/ci-cd.yml) runs on pushes to
`main`, pull requests targeting `main`, `v*` tags, and manual dispatch. It installs
locked Composer/npm dependencies, checks PHP syntax, runs four existing test
classes against isolated in-memory SQLite fixtures, and builds Vite assets.
It verifies the presence of `DEMO_API_KEY` without displaying it (fork PRs skip
secret verification). After CI succeeds, trusted main/version runs build and
publish a Docker image to GHCR using `GITHUB_TOKEN`. PRs never publish images.
Publishing an image provides continuous delivery; it does not deploy the website.
The existing Playwright workflow is preserved as a separate workflow.

Required tools: Git, PHP 8.4.1+ for the current lockfile (CI/container: 8.4), Composer 2, Node.js/npm
(CI/container: Node 22), SQLite PHP extension for CI fixtures, and optional Docker
Desktop with Linux containers. GitHub Actions/GHCR require repository access.
Existing application dependencies and lockfiles are unchanged.

Safe local checks from the repository root:

```powershell
php devops/lab-act-3/scripts/lint-php.php
php vendor/phpunit/phpunit/phpunit -c devops/lab-act-3/phpunit.ci.xml
node devops/lab-act-3/scripts/validate-workflow.mjs
# Keep generated assets separate from the currently running website:
npm.cmd run build -- --outDir devops/lab-act-3/.runtime/build --emptyOutDir
```

Install dependencies with `composer install` and `npm ci` in a fresh checkout.
Do not overwrite an existing `.env`, generate a new key for a running installation,
or run development database commands for this lab. The CI bootstrap forces SQLite,
array cache/session/mail, and separate runtime storage/cache paths.

Container build (when Docker is available):

```powershell
docker build -t invoiz-lab:local .
```

For low-RAM verification, build `--target web`, then `--target vendor`, then the
final image sequentially. Run
`powershell -NoProfile -File devops/lab-act-3/scripts/Test-Docker.ps1` to verify
one resource-limited container using SQLite memory on port 8085 and remove it
afterward. No existing MySQL database or host application files are mounted.

For independent container setup, health checks, GHCR pulls/runs, secret setup,
release tags, and safe challenge procedures, follow the
[lab guide](devops/lab-act-3/README.md),
[GitHub setup](devops/lab-act-3/docs/github-setup.md), and
[challenge instructions](devops/lab-act-3/docs/challenges.md).
The [verification report](devops/lab-act-3/docs/verification-results.md) distinguishes
verified local Docker results from unexecuted Actions and GHCR checks.
No push, remote tag, publication, or deployment is performed by local validation.

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
