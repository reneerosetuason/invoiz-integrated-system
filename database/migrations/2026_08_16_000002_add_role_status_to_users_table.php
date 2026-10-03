<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'seller', 'buyer', 'rider'])->default('buyer')->after('email');
            $table->enum('account_status', ['active', 'suspended', 'deactivated'])->default('active')->after('role');
            $table->string('phone')->nullable()->after('account_status');
            $table->timestamp('email_verified_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'account_status', 'phone']);
        });
    }
};
