# INVOIZ Developer Guide

This guide provides instructions for configuring your local development environment, running the backend server, compiling frontends, building the mobile applications, and executing test suites.

---

## 1. Prerequisites

Ensure the following runtimes and tools are installed on your machine:

* **PHP**: version 8.2 or 8.3 with extensions: `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `curl`, `fileinfo`, `gd` (or `imagick`).
* **Composer**: version 2.5+.
* **Node.js**: version 18.x or 20.x LTS & **npm**.
* **Database**: MySQL 8.0+ (or MariaDB 10.5+).
* **Flutter SDK**: version 3.x+ (required for `buyer_mobile` and `rider_mobile`).
* **Android Studio / Command Line Tools**: required for compiling Android APKs.

---

## 2. Backend & Web Portal Setup

### Step 1: Clone and Install Dependencies
From the repository root:

```powershell
# Install PHP vendor packages
composer install

# Install frontend JavaScript/CSS dependencies
npm ci
```

### Step 2: Environment Configuration
Copy the environment template if `.env` does not already exist:

```powershell
cp .env.example .env
php artisan key:generate
```

Open `.env` and verify your local database credentials:

```env
APP_NAME=INVOIZ
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=invoiz
DB_USERNAME=root
DB_PASSWORD=your_password
```

### Step 3: Run Database Migrations
Run the standard Laravel migration command:

```powershell
php artisan migrate
```

> [!TIP]
> If you are setting up fresh sample data for testing, run seeders:
> ```powershell
> php artisan db:seed
> ```

### Step 4: Storage Symlink
Link the public storage directory so that uploaded product images and return proofs are accessible to browsers:

```powershell
php artisan storage:link
```

### Step 5: Build Frontend Assets
Compile the Vite assets:

```powershell
# For production build:
npm run build

# Or for active local UI development with hot reloading:
npm run dev
```

### Step 6: Start the Local Server
```powershell
php artisan serve --host=127.0.0.1 --port=8000
```
Open your browser at `http://127.0.0.1:8000`.

---

## 3. Buyer Mobile App (`buyer_mobile`)

The `buyer_mobile` project is a cross-platform Flutter application providing native shopping, order tracking, real-time seller chat, and return dispute filing.

### Running on an Emulator or Connected Device
```powershell
cd buyer_mobile
flutter pub get
flutter run
```

### Configuring API Base URL
In `buyer_mobile/lib/config/api_config.dart` (or your environment settings), ensure the API URL points to your running backend:
* **Android Emulator**: `http://10.0.2.2:8000/api`
* **Physical Device**: Use your local LAN IP (e.g., `http://192.168.1.50:8000/api`) or a tunnel URL (e.g. Cloudflare Tunnel or ngrok).

### Web Preview Build
The backend can serve the Flutter web build directly at `http://127.0.0.1:8000/buyer-app/`:
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File buyer_mobile/scripts/Build-Web.ps1
```

### Compiling Android APK
To produce a debug APK for device testing:
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File buyer_mobile/scripts/Build-Android.ps1
# The output APK will be generated at:
# buyer_mobile/build/app/outputs/flutter-apk/app-debug.apk
```

---

## 4. Rider Mobile App (`rider_mobile`)

The `rider_mobile` project serves delivery couriers and logistics transport drivers.

```powershell
cd rider_mobile
flutter pub get
flutter run
```

Key features implemented in the rider client:
* Real-time delivery queue and pickup assignments.
* Barcode / QR scanner for parcel tracking verification.
* Camera capture for proof-of-delivery (recipient signature and dropoff photo).
* Reverse-fulfillment pickup collection for customer returns.

---

## 5. Testing & Quality Assurance

### Checking Shared Backend Health
You can verify the database schema, models, auth guards, and cache configurations without modifying data:

```powershell
php artisan invoiz:check-backend
```

### Running the PHPUnit Test Suite
The repository includes dedicated MySQL integration tests and isolated unit tests.

#### Focused & Isolated Tests (Fast):
```powershell
php vendor/phpunit/phpunit/phpunit --filter "ViewStructureTest|BuyerWebShoppingTest|CommerceRegressionTest|SharedBackendTest|BuyerMobileIntegrationTest|SellerProductShippingDimensionsTest"
```

#### Dedicated MySQL Database Test Suite:
When running the full feature test suite against the test MySQL database configured in `phpunit.mysql.xml`:

```powershell
php artisan test -c phpunit.mysql.xml
```

---

## 6. Directory Structure Overview

```
c:\Invoiz\
├── app/
│   ├── Http/Controllers/
│   │   ├── Admin/         # Superadmin controllers
│   │   ├── Buyer/         # Web storefront & mobile API controllers
│   │   ├── Seller/        # Seller Center management
│   │   ├── Logistics/     # Hub operations & rider APIs
│   │   └── Shared/        # Unified auth, messaging, and common services
│   ├── Models/            # Eloquent ORM entity models
│   └── Services/          # Core business services (ReturnWorkflow, Escrow, etc.)
├── buyer_mobile/          # Flutter Buyer mobile application
├── rider_mobile/          # Flutter Rider mobile application
├── database/
│   ├── migrations/        # Database schema migrations
│   └── seeders/           # Default dataset seeders
├── docs/                  # Architecture, guides, and audit logs
├── public/                # Public assets, entry point (index.php), images
├── resources/
│   ├── views/             # Blade templates categorized by role
│   ├── css/               # Tailwind & custom stylesheets
│   └── js/                # Client-side scripts
├── routes/
│   ├── admin/             # Admin web & API routes
│   ├── buyer/             # Buyer web storefront & REST API routes
│   ├── seller/            # Seller Center routes
│   ├── logistics/         # Logistics hub & courier routes
│   └── shared/            # Shared authentication & messaging routes
└── tests/                 # Automated feature & integration test suite
```
