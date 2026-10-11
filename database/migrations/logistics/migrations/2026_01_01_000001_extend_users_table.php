<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->rememberToken()->after('email_verified_at');
        });

        DB::table('users')->whereNull('name')->get(['id', 'first_name', 'last_name'])
            ->each(function ($user) {
                DB::table('users')->where('id', $user->id)->update([
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['name', 'email_verified_at', 'remember_token']);
        });
    }
};