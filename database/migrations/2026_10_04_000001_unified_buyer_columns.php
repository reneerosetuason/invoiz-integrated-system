<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified buyer columns for shared invoizdb.
 * Non-destructive: only adds missing nullable columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'first_name' => 'string', 'last_name' => 'string', 'middle_initial' => 'string',
                'sex' => 'string', 'birthday' => 'date', 'age' => 'integer',
                'province' => 'string', 'municipality' => 'string', 'barangay' => 'string',
                'city' => 'string', 'address_line' => 'string', 'postal_code' => 'string',
                'id_image' => 'string', 'profile_picture' => 'string', 'bio' => 'text',
                'approval_status' => 'string', 'status' => 'string', 'role' => 'string',
                'otp' => 'string', 'otp_expires_at' => 'timestamp',
            ] as $col => $type) {
                if (Schema::hasColumn('users', $col)) continue;
                if ($type === 'text') $table->text($col)->nullable();
                elseif ($type === 'integer') $table->integer($col)->nullable();
                elseif ($type === 'date') $table->date($col)->nullable();
                elseif ($type === 'timestamp') $table->timestamp($col)->nullable();
                else $table->string($col)->nullable();
            }
        });

        if (! Schema::hasTable('carts')) {
            Schema::create('carts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('buyer_id')->unique();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('cart_items')) {
            Schema::create('cart_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cart_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('variant_id')->nullable();
                $table->integer('quantity')->default(1);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('addresses')) {
            Schema::create('addresses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('buyer_id');
                $table->string('recipient_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('address_line')->nullable();
                $table->string('barangay')->nullable();
                $table->string('city')->nullable();
                $table->string('province')->nullable();
                $table->string('postal_code')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void {}
};
