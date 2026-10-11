<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$migration = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("users", function (Blueprint $table) {
            $table->enum("role", ["admin", "seller", "buyer", "rider", "staff", "logistics_admin", "logistics_staff"])->default("buyer")->change();
        });
    }

    public function down(): void
    {
        Schema::table("users", function (Blueprint $table) {
            $table->enum("role", ["admin", "seller", "buyer", "rider", "staff"])->default("buyer")->change();
        });
    }
};';
$filename = 'C:\Invoiz\database\migrations\\' . date('Y_m_d_His') . '_add_logistics_roles_to_users.php';
file_put_contents($filename, $migration);
echo "Migration created: $filename";