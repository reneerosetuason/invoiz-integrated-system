<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/*
 * Admin compatibility layer for the SHARED `invoizdb` database.
 *
 * Goal: make `invoiz admin acc/backend` work against the EXISTING invoizdb
 * (used by buyer + seller apps) WITHOUT creating a new database and WITHOUT
 * dropping/altering existing columns in a breaking way.
 *
 * Every change below is guarded:
 *  - tables are only created when missing
 *  - columns are only added when missing (all NULLABLE or with defaults)
 *  - no existing table/column is dropped or renamed
 */
return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------------------
        // 1. users: admin needs name / is_admin / account_status
        // invoizdb users currently has first_name,last_name,role,status,...
        // -----------------------------------------------------------------
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'name')) {
                $table->string('name', 255)->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false)->after('password');
            }
            if (! Schema::hasColumn('users', 'account_status')) {
                $table->enum('account_status', ['active', 'suspended', 'deactivated'])->default('active')->after('role');
            }
            if (! Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken();
            }
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Backfill users.name from first_name + last_name where possible.
        try {
            if (Schema::hasColumn('users', 'first_name') && Schema::hasColumn('users', 'name')) {
                DB::statement("UPDATE `users` SET `name` = TRIM(CONCAT(COALESCE(`first_name`,''),' ',COALESCE(`last_name`,''))) WHERE (`name` IS NULL OR `name` = '')");
            }
            // Keep account_status in sync with legacy `status` column on first run.
            if (Schema::hasColumn('users', 'status') && Schema::hasColumn('users', 'account_status')) {
                DB::statement("UPDATE `users` SET `account_status` = CASE WHEN `status` = 'suspended' THEN 'suspended' WHEN `status` = 'inactive' THEN 'deactivated' ELSE 'active' END WHERE 1=1");
            }
        } catch (\Throwable $e) {
            // non-fatal backfill
        }

        // -----------------------------------------------------------------
        // 2. sellers: admin needs store_name (+ soft deletes)
        // invoizdb sellers has business_name, approval_status, status, ...
        // -----------------------------------------------------------------
        Schema::table('sellers', function (Blueprint $table) {
            if (! Schema::hasColumn('sellers', 'store_name')) {
                $table->string('store_name', 255)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('sellers', 'primary_color')) {
                $table->string('primary_color', 9)->default('#16697A')->after('status');
            }
            if (! Schema::hasColumn('sellers', 'accent_color')) {
                $table->string('accent_color', 9)->default('#F0A202')->after('primary_color');
            }
            if (! Schema::hasColumn('sellers', 'logo')) {
                $table->string('logo')->nullable()->after('accent_color');
            }
            if (! Schema::hasColumn('sellers', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        try {
            if (Schema::hasColumn('sellers', 'business_name') && Schema::hasColumn('sellers', 'store_name')) {
                DB::statement("UPDATE `sellers` SET `store_name` = `business_name` WHERE (`store_name` IS NULL OR `store_name` = '')");
            }
        } catch (\Throwable $e) {
        }

        // -----------------------------------------------------------------
        // 3. categories: admin needs slug / parent_id / active
        // -----------------------------------------------------------------
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'slug')) {
                $table->string('slug', 255)->nullable()->unique()->after('name');
            }
            if (! Schema::hasColumn('categories', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('categories', 'active')) {
                $table->boolean('active')->default(true)->after('description');
            }
            if (! Schema::hasColumn('categories', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        try {
            if (Schema::hasColumn('categories', 'slug')) {
                DB::statement("UPDATE `categories` SET `slug` = LOWER(REPLACE(`name`,' ','-')) WHERE (`slug` IS NULL OR `slug` = '')");
            }
        } catch (\Throwable $e) {
        }

        // -----------------------------------------------------------------
        // 4. products: admin needs slug / analytics / soft deletes
        // -----------------------------------------------------------------
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'slug')) {
                $table->string('slug', 255)->nullable()->unique()->after('name');
            }
            if (! Schema::hasColumn('products', 'short_description')) {
                $table->string('short_description', 500)->nullable()->after('description');
            }
            if (! Schema::hasColumn('products', 'views_count')) {
                $table->unsignedBigInteger('views_count')->default(0)->after('status');
            }
            if (! Schema::hasColumn('products', 'cart_additions')) {
                $table->unsignedBigInteger('cart_additions')->default(0)->after('views_count');
            }
            if (! Schema::hasColumn('products', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->after('seller_id');
            }
            if (! Schema::hasColumn('products', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        try {
            if (Schema::hasColumn('products', 'slug')) {
                DB::statement("UPDATE `products` SET `slug` = LOWER(REPLACE(`name`,' ','-')) WHERE (`slug` IS NULL OR `slug` = '')");
            }
        } catch (\Throwable $e) {
        }

        // -----------------------------------------------------------------
        // 5. product_variants: admin needs sku/attributes/price/reserved
        // -----------------------------------------------------------------
        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'sku')) {
                $table->string('sku', 100)->nullable()->after('product_id');
            }
            if (! Schema::hasColumn('product_variants', 'attributes')) {
                $table->json('attributes')->nullable()->after('sku');
            }
            if (! Schema::hasColumn('product_variants', 'price')) {
                $table->decimal('price', 12, 2)->default(0)->after('attributes');
            }
            if (! Schema::hasColumn('product_variants', 'reserved')) {
                $table->integer('reserved')->default(0)->after('stock');
            }
            if (! Schema::hasColumn('product_variants', 'weight')) {
                $table->decimal('weight', 8, 2)->nullable()->after('reserved');
            }
            if (! Schema::hasColumn('product_variants', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // -----------------------------------------------------------------
        // 6. product_images: admin needs path / is_main
        // -----------------------------------------------------------------
        Schema::table('product_images', function (Blueprint $table) {
            if (! Schema::hasColumn('product_images', 'path')) {
                $table->string('path', 255)->nullable()->after('product_id');
            }
            if (! Schema::hasColumn('product_images', 'is_main')) {
                $table->boolean('is_main')->default(false)->after('path');
            }
            if (! Schema::hasColumn('product_images', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
            if (! Schema::hasColumn('product_images', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        try {
            if (Schema::hasColumn('product_images', 'image_path') && Schema::hasColumn('product_images', 'path')) {
                DB::statement("UPDATE `product_images` SET `path` = `image_path` WHERE (`path` IS NULL OR `path` = '')");
            }
        } catch (\Throwable $e) {
        }

        // -----------------------------------------------------------------
        // 7. orders: admin needs order_number/seller_id/payment fields
        // invoizdb orders is buyer-centric (buyer_id,address_id,total_amount).
        // We ADD admin columns as nullable so both apps keep working.
        // -----------------------------------------------------------------
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'order_number')) {
                $table->string('order_number', 100)->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('orders', 'seller_id')) {
                $table->unsignedBigInteger('seller_id')->nullable()->after('order_number');
            }
            if (! Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status', 50)->default('pending')->after('status');
            }
            if (! Schema::hasColumn('orders', 'total')) {
                $table->decimal('total', 12, 2)->default(0)->after('payment_status');
            }
            if (! Schema::hasColumn('orders', 'shipping_address')) {
                $table->json('shipping_address')->nullable()->after('total');
            }
            if (! Schema::hasColumn('orders', 'sub_total')) {
                $table->decimal('sub_total', 12, 2)->default(0)->after('shipping_address');
            }
            if (! Schema::hasColumn('orders', 'discount_total')) {
                $table->decimal('discount_total', 12, 2)->default(0)->after('sub_total');
            }
            if (! Schema::hasColumn('orders', 'shipping_fee')) {
                $table->decimal('shipping_fee', 12, 2)->default(0)->after('discount_total');
            }
            if (! Schema::hasColumn('orders', 'tax_total')) {
                $table->decimal('tax_total', 12, 2)->default(0)->after('shipping_fee');
            }
            if (! Schema::hasColumn('orders', 'commission_amount')) {
                $table->decimal('commission_amount', 12, 2)->default(0)->after('tax_total');
            }
            if (! Schema::hasColumn('orders', 'delivery_status')) {
                $table->string('delivery_status', 50)->default('pending')->after('commission_amount');
            }
            if (! Schema::hasColumn('orders', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        try {
            if (Schema::hasColumn('orders', 'total_amount') && Schema::hasColumn('orders', 'total')) {
                DB::statement("UPDATE `orders` SET `total` = `total_amount` WHERE `total` = 0");
            }
            if (Schema::hasColumn('orders', 'order_number')) {
                DB::statement("UPDATE `orders` SET `order_number` = CONCAT('INV-', LPAD(`id`, 6, '0')) WHERE (`order_number` IS NULL OR `order_number` = '')");
            }
        } catch (\Throwable $e) {
        }

        // -----------------------------------------------------------------
        // 8. order_items
        // -----------------------------------------------------------------
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'product_variant_id')) {
                $table->unsignedBigInteger('product_variant_id')->nullable()->after('product_id');
            }
            if (! Schema::hasColumn('order_items', 'unit_price')) {
                $table->decimal('unit_price', 12, 2)->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('order_items', 'total_price')) {
                $table->decimal('total_price', 12, 2)->nullable()->after('unit_price');
            }
            if (! Schema::hasColumn('order_items', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        try {
            if (Schema::hasColumn('order_items', 'price') && Schema::hasColumn('order_items', 'unit_price')) {
                DB::statement("UPDATE `order_items` SET `unit_price` = `price` WHERE `unit_price` IS NULL");
            }
            if (Schema::hasColumn('order_items', 'subtotal') && Schema::hasColumn('order_items', 'total_price')) {
                // `subtotal` may be a generated column in invoizdb - copy where possible
                DB::statement("UPDATE `order_items` SET `total_price` = `subtotal` WHERE `total_price` IS NULL");
            }
        } catch (\Throwable $e) {
        }

        // -----------------------------------------------------------------
        // 9. order_status_histories: admin needs metadata/updated_at
        // -----------------------------------------------------------------
        Schema::table('order_status_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('order_status_histories', 'notes')) {
                $table->text('notes')->nullable()->after('to_status');
            }
            if (! Schema::hasColumn('order_status_histories', 'metadata')) {
                $table->json('metadata')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('order_status_histories', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        // -----------------------------------------------------------------
        // 10. messages: admin needs receiver_id
        // -----------------------------------------------------------------
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'receiver_id')) {
                $table->unsignedBigInteger('receiver_id')->nullable()->after('sender_id');
            }
            if (! Schema::hasColumn('messages', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        // -----------------------------------------------------------------
        // 11. vouchers: admin needs type/value/starts_at/ends_at
        // -----------------------------------------------------------------
        Schema::table('vouchers', function (Blueprint $table) {
            if (! Schema::hasColumn('vouchers', 'type')) {
                $table->enum('type', ['fixed', 'percent'])->default('fixed')->after('code');
            }
            if (! Schema::hasColumn('vouchers', 'value')) {
                $table->decimal('value', 10, 2)->nullable()->after('type');
            }
            if (! Schema::hasColumn('vouchers', 'starts_at')) {
                $table->dateTime('starts_at')->nullable()->after('min_spend');
            }
            if (! Schema::hasColumn('vouchers', 'ends_at')) {
                $table->dateTime('ends_at')->nullable()->after('starts_at');
            }
        });

        try {
            if (Schema::hasColumn('vouchers', 'discount_type') && Schema::hasColumn('vouchers', 'type')) {
                DB::statement("UPDATE `vouchers` SET `type` = `discount_type` WHERE 1=1");
            }
            if (Schema::hasColumn('vouchers', 'discount_value') && Schema::hasColumn('vouchers', 'value')) {
                DB::statement("UPDATE `vouchers` SET `value` = `discount_value` WHERE `value` IS NULL");
            }
        } catch (\Throwable $e) {
        }

        // -----------------------------------------------------------------
        // 12. Missing admin-only tables (create only if missing)
        // -----------------------------------------------------------------
        if (! Schema::hasTable('seller_profiles')) {
            Schema::create('seller_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
                $table->string('logo_path')->nullable();
                $table->string('banner_path')->nullable();
                $table->text('description')->nullable();
                $table->string('contact_email')->nullable();
                $table->string('contact_phone')->nullable();
                $table->text('address')->nullable();
                $table->text('business_info')->nullable();
                $table->string('operating_hours')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('inventories')) {
            Schema::create('inventories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
                $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
                $table->integer('quantity')->default(0);
                $table->integer('reserved')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_id')->constrained('inventories')->cascadeOnDelete();
                $table->string('type');
                $table->integer('quantity');
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action');
                $table->string('entity')->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('complaints')) {
            Schema::create('complaints', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
                $table->string('subject');
                $table->text('description');
                $table->enum('status', ['open', 'in_review', 'resolved', 'closed'])->default('open');
                $table->enum('type', ['order', 'product', 'seller', 'delivery', 'other'])->default('other');
                $table->text('resolution')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('body');
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('platform_policies')) {
            Schema::create('platform_policies', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('content');
                $table->string('slug')->unique();
                $table->boolean('is_active')->default(true);
                $table->integer('version')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notifications_log')) {
            Schema::create('notifications_log', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type');
                $table->string('subject');
                $table->text('body')->nullable();
                $table->string('email')->nullable();
                $table->boolean('sent')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('commission_rates')) {
            Schema::create('commission_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->nullable()->unique()->constrained('categories')->nullOnDelete();
                $table->decimal('rate', 5, 2)->default(10.00);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payment_transactions')) {
            Schema::create('payment_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->string('transaction_ref')->unique();
                $table->string('gateway')->default('GCash');
                $table->decimal('amount', 12, 2)->default(0);
                $table->enum('status', ['successful', 'pending', 'failed', 'refunded', 'partially_refunded'])->default('pending');
                $table->decimal('seller_earning', 12, 2)->default(0);
                $table->decimal('platform_commission', 12, 2)->default(0);
                $table->decimal('rider_fee', 12, 2)->default(0);
                $table->decimal('refunded_amount', 12, 2)->default(0);
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('seller_payouts')) {
            Schema::create('seller_payouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
                $table->decimal('amount', 12, 2)->default(0);
                $table->enum('status', ['pending', 'processing', 'paid'])->default('pending');
                $table->string('method')->default('Bank Transfer');
                $table->string('reference')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('withdrawal_requests')) {
            Schema::create('withdrawal_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
                $table->decimal('amount', 12, 2)->default(0);
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->string('method')->default('Bank Transfer');
                $table->text('note')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payment_disputes')) {
            Schema::create('payment_disputes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('reason');
                $table->enum('status', ['open', 'under_review', 'resolved', 'rejected'])->default('open');
                $table->text('resolution_note')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('product_reviews')) {
            Schema::create('product_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedTinyInteger('rating')->default(5);
                $table->text('review')->nullable();
                $table->enum('status', ['pending', 'approved', 'hidden'])->default('approved');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: do not drop shared invoizdb tables.
    }
};
