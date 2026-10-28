<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;
use Throwable;

class CheckSharedBackend extends Command
{
    protected $signature = 'invoiz:check-backend';

    protected $description = 'Verify shared role models, accounts and core tables without changing data';

    public function handle(): int
    {
        try {
            $connection = DB::connection();
            $pdo = $connection->getPdo();
            $errors = [];
            $modelCount = 0;

            foreach (File::allFiles(app_path('Models')) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $class = 'App\\Models\\'.str_replace(['/', '\\'], '\\', substr($file->getRelativePathname(), 0, -4));
                if (! is_subclass_of($class, Model::class) || (new ReflectionClass($class))->isAbstract()) {
                    continue;
                }

                $modelCount++;
                if ((new $class)->getConnection()->getPdo() !== $pdo) {
                    $errors[] = "{$class} uses a separate database connection.";
                }
            }

            $tokenModel = Sanctum::$personalAccessTokenModel;
            if ((new $tokenModel)->getConnection()->getPdo() !== $pdo) {
                $errors[] = 'API tokens use a separate database connection.';
            }

            if (config('auth.providers.users.model') !== User::class) {
                $errors[] = 'Authentication must use the shared App\\Models\\User model.';
            }
            foreach (config('auth.guards', []) as $name => $guard) {
                if (isset($guard['provider']) && config('auth.providers.'.$guard['provider'].'.model') !== User::class) {
                    $errors[] = "Authentication guard {$name} uses a separate account provider.";
                }
            }

            $stores = [
                'Sessions' => config('session.driver') === 'database' ? config('session.connection') : null,
                'Cache' => config('cache.stores.'.config('cache.default').'.driver') === 'database'
                    ? config('cache.stores.'.config('cache.default').'.connection') : null,
                'Queue' => config('queue.connections.'.config('queue.default').'.driver') === 'database'
                    ? config('queue.connections.'.config('queue.default').'.connection') : null,
            ];
            foreach ($stores as $name => $storeConnection) {
                if ($storeConnection && DB::connection($storeConnection)->getPdo() !== $pdo) {
                    $errors[] = "{$name} uses a separate database connection.";
                }
            }

            foreach (['users', 'sellers', 'riders', 'products', 'orders', 'order_items', 'deliveries', 'remittances', 'payments', 'personal_access_tokens'] as $table) {
                if (! $connection->getSchemaBuilder()->hasTable($table)) {
                    $errors[] = "Shared database is missing the {$table} table.";
                }
            }

            if ($errors) {
                foreach ($errors as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }

            $this->info("PASS: {$modelCount} application models, API tokens and accounts share one database connection.");
            $this->line('Connection: '.$connection->getName());
            $this->line('Database: '.$connection->getDatabaseName());
            $this->line('Core tables verified. No data was changed.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            // Connection exception messages can contain deployment credentials.
            $this->error('Unable to verify the shared backend ('.$e::class.'). Check the database configuration and connectivity.');

            return self::FAILURE;
        }
    }
}
