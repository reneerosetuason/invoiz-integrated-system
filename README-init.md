# Initialize Laravel inside the `backend/` folder

This project uses Docker for local development. The backend folder will contain the Laravel application.

Quick start (from project root):

```bash
# start db and helper containers
docker compose up -d db

# create laravel project in backend (runs composer inside a php container)
docker compose up -d
docker exec -it bk_node sh -c "cd /var/www && npm install"
docker exec -it bk_app php artisan key:generate
docker exec -it bk_app php artisan migrate
1) Using Docker (recommended):

```powershell
# start db only
docker compose up -d db

# create laravel project in backend (one-time):
docker run --rm -v "%cd%/backend:/app" -w /app composer create-project laravel/laravel:^11.0 .

# copy env (PowerShell):
Copy-Item backend\.env.example backend\.env

# start all services
docker compose up -d

# install node deps inside node container
docker exec -it bk_node sh -c "cd /var/www && npm install"

# generate app key and run migrations
docker exec -it bk_app php artisan key:generate
docker exec -it bk_app php artisan migrate --seed

# Visit http://localhost:8000
```

2) Without Docker (native Windows):

```powershell
# from project root
cd backend
Copy-Item .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run dev
```

Notes:
- If `docker` is not found, install Docker Desktop for Windows or use the native steps above.
- Ensure PHP 8.2+, Composer, Node 18+, and MySQL 8+ are installed for native setup.
```

Notes:
- Adjust paths/permissions on Windows as needed.
- Alternatively run the composer commands locally if you have PHP/Composer installed.
