<?php

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
};