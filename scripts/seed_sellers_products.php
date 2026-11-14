<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

echo "Seeding 2 Sellers and Products for Toys & Hobbies and Books...\n";

// Function to download image and save to public storage
function downloadAndStoreImage(string $url, string $prefix): ?string {
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) InvoizSeeder/1.0',
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        $data = @file_get_contents($url, false, $context);
        if (!$data || strlen($data) < 1000) {
            echo "  [WARN] Failed to download from $url\n";
            return null;
        }

        $filename = Str::random(40) . '.jpg';
        $relPath = 'products/' . $filename;
        Storage::disk('public')->put($relPath, $data);

        // Also ensure public/storage symlink/copy has it
        $pubPath = public_path('storage/' . $relPath);
        @mkdir(dirname($pubPath), 0755, true);
        @file_put_contents($pubPath, $data);

        echo "  [OK] Saved image $relPath (" . strlen($data) . " bytes)\n";
        return $relPath;
    } catch (\Throwable $e) {
        echo "  [ERROR] " . $e->getMessage() . "\n";
        return null;
    }
}

// 1. Create or Find Seller 1: Toys & Hobbies
$toysEmail = 'apex_toys@invoiz.test';
$toysUser = User::firstOrCreate(
    ['email' => $toysEmail],
    [
        'first_name' => 'Marco',
        'last_name' => 'Rossi',
        'name' => 'Marco Rossi',
        'phone' => '09175550101',
        'password' => Hash::make('SellerPass123!'),
        'role' => 'seller',
        'status' => 'active',
        'account_status' => 'active',
        'approval_status' => 'approved',
        'email_verified_at' => now(),
        'address_line' => 'Unit 402, Toymakers Plaza, Banawe St.',
        'barangay' => 'Sienna',
        'city' => 'Quezon City',
        'municipality' => 'Quezon City',
        'province' => 'Metro Manila',
        'postal_code' => '1114',
    ]
);

$toysSeller = Seller::firstOrCreate(
    ['user_id' => $toysUser->id],
    [
        'store_name' => 'Apex Hobby & Toy Studio',
        'business_name' => 'Apex Hobby & Toy Studio',
        'line_of_business' => 'Toys, RC Vehicles & Hobby Kits',
        'seller_type' => 'individual',
        'status' => 'active',
        'approval_status' => 'approved',
        'terms_accepted' => true,
        'province' => 'Metro Manila',
        'city' => 'Quezon City',
        'barangay' => 'Sienna',
        'postal_code' => '1114',
        'address_line' => 'Unit 402, Toymakers Plaza, Banawe St.',
    ]
);
echo "Seller 1 ready: {$toysSeller->store_name} (User ID: {$toysUser->id})\n";

// 2. Create or Find Seller 2: Books
$booksEmail = 'chapters_books@invoiz.test';
$booksUser = User::firstOrCreate(
    ['email' => $booksEmail],
    [
        'first_name' => 'Clara',
        'last_name' => 'Mendoza',
        'name' => 'Clara Mendoza',
        'phone' => '09175550202',
        'password' => Hash::make('SellerPass123!'),
        'role' => 'seller',
        'status' => 'active',
        'account_status' => 'active',
        'approval_status' => 'approved',
        'email_verified_at' => now(),
        'address_line' => '88 Maginhawa St., Teachers Village',
        'barangay' => 'Teachers Village East',
        'city' => 'Quezon City',
        'municipality' => 'Quezon City',
        'province' => 'Metro Manila',
        'postal_code' => '1101',
    ]
);

$booksSeller = Seller::firstOrCreate(
    ['user_id' => $booksUser->id],
    [
        'store_name' => 'Chapters & Leaves Bookshop',
        'business_name' => 'Chapters & Leaves Bookshop',
        'line_of_business' => 'Literary Books, Novels & Reference Works',
        'seller_type' => 'individual',
        'status' => 'active',
        'approval_status' => 'approved',
        'terms_accepted' => true,
        'province' => 'Metro Manila',
        'city' => 'Quezon City',
        'barangay' => 'Teachers Village East',
        'postal_code' => '1101',
        'address_line' => '88 Maginhawa St., Teachers Village',
    ]
);
echo "Seller 2 ready: {$booksSeller->store_name} (User ID: {$booksUser->id})\n";

$toysCat = Category::where('name', 'like', '%Toys%')->firstOrFail();
$booksCat = Category::where('name', 'like', '%Books%')->firstOrFail();

echo "Categories: Toys ID={$toysCat->id}, Books ID={$booksCat->id}\n";

// Verified high quality real photos
$productDefs = [
    // Toys & Hobbies - Product 1 (ON SALE)
    [
        'seller_id' => $toysUser->id,
        'category_id' => $toysCat->id,
        'name' => 'High-Speed 4WD Remote Control Dune Stunt Buggy',
        'slug' => 'high-speed-4wd-remote-control-dune-stunt-buggy-' . Str::lower(Str::random(5)),
        'description' => 'Unleash high-octane racing thrills with this all-terrain 1:16 scale 4WD RC Dune Stunt Buggy! Equipped with high-torque magnetic motors reaching up to 36 km/h, full independent coil spring suspension, high-grip rubber compound off-road tires, and responsive 2.4GHz proportional radio controller with 80-meter range. Includes two 7.4V 1200mAh rechargeable lithium-ion battery packs providing up to 45 minutes of continuous drive time and USB smart charger. Perfect for dirt tracks, grass, gravel, and pavement stunts.',
        'price' => 1350.00,
        'compare_at_price' => 1750.00,
        'discount_percent' => 23,
        'cost_price' => 900.00,
        'stock' => 28,
        'low_stock_threshold' => 5,
        'weight' => '1.45 kg',
        'dimensions' => '32 × 21 × 14 cm',
        'sku' => 'APEX-RC-BUGGY-01',
        'brand' => 'Apex Speed',
        'model' => 'APX-BUGGY-16',
        'material' => 'Nylon & Alloy Shock Tower',
        'warranty' => '6 Months Service Warranty',
        'origin' => 'Philippines',
        'status' => 'active',
        'images' => [
            'https://images.unsplash.com/photo-1594787318286-3d835c1d207f?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1581235720704-06d3acfcb36f?w=800&auto=format&fit=crop&q=80',
        ],
    ],
    // Toys & Hobbies - Product 2 (Regular Price)
    [
        'seller_id' => $toysUser->id,
        'category_id' => $toysCat->id,
        'name' => 'Mech Striker Titan Articulated Model Kit',
        'slug' => 'mech-striker-titan-articulated-model-kit-' . Str::lower(Str::random(5)),
        'description' => 'Precision-engineered 1/144 scale sci-fi combat mech model kit featuring over 180 multi-colored snap-fit injection-molded parts. Boasts more than 30 points of articulation for dynamic battle stances, beam rifle, energy shield, and poseable thruster wings. Pre-separated runner colors eliminate the need for painting, while included water-slide decals and clear display stand let you create museum-grade tabletop displays. Recommended for hobbyists ages 12 and up.',
        'price' => 880.00,
        'compare_at_price' => null,
        'discount_percent' => 0,
        'cost_price' => 550.00,
        'stock' => 35,
        'low_stock_threshold' => 5,
        'weight' => '0.65 kg',
        'dimensions' => '26 × 19 × 7 cm',
        'sku' => 'APEX-MECH-TITAN-02',
        'brand' => 'Apex Mecha',
        'model' => 'AM-TITAN-MK3',
        'material' => 'High-Grade Polystyrene (PS/ABS)',
        'warranty' => 'No Warranty',
        'origin' => 'Japan Import',
        'status' => 'active',
        'images' => [
            'https://images.unsplash.com/photo-1585366119957-e9730b6d0f60?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1587654780291-39c9404d746b?w=800&auto=format&fit=crop&q=80',
        ],
    ],
    // Books - Product 3 (ON SALE)
    [
        'seller_id' => $booksUser->id,
        'category_id' => $booksCat->id,
        'name' => 'Illustrated Classic World Mythology Anthology (Clothbound)',
        'slug' => 'illustrated-classic-world-mythology-anthology-' . Str::lower(Str::random(5)),
        'description' => 'A lavishly designed 480-page hardbound collection chronicling legendary myths and epics from Norse, Greek, Celtic, and Asian folklore. Printed on 120gsm archival woodfree paper with gilded ribbon marker, gilded page edges, and over 60 full-color original historical woodcut and oil illustrations. Features authoritative introductory commentary, family trees of deities, and an extensive glossary. An heirloom-quality centerpiece for any personal library.',
        'price' => 1150.00,
        'compare_at_price' => 1499.00,
        'discount_percent' => 23,
        'cost_price' => 750.00,
        'stock' => 22,
        'low_stock_threshold' => 4,
        'weight' => '1.35 kg',
        'dimensions' => '25 × 18 × 5 cm',
        'sku' => 'CHPT-MYTH-CLOTH-01',
        'brand' => 'Chapters Classics',
        'model' => 'CC-MYTH-2026',
        'material' => 'Embossed Clothbound Hardcover',
        'warranty' => 'Replacement on print defects',
        'origin' => 'Philippines',
        'status' => 'active',
        'images' => [
            'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1512820790803-83ca734da794?w=800&auto=format&fit=crop&q=80',
        ],
    ],
    // Books - Product 4 (Regular Price)
    [
        'seller_id' => $booksUser->id,
        'category_id' => $booksCat->id,
        'name' => 'The Modern Novelist Craft & World-Building Handbook',
        'slug' => 'the-modern-novelist-craft-and-world-building-handbook-' . Str::lower(Str::random(5)),
        'description' => 'Essential reading for fiction writers, worldbuilders, and storytellers. This practical 320-page trade paperback breaks down character arc progression, pacing architecture, dialogue subtext, and rich environmental worldbuilding with concrete case studies and chapter-by-chapter exercises. Written by award-winning editors and praised by contemporary novelists as an indispensable desk reference.',
        'price' => 495.00,
        'compare_at_price' => null,
        'discount_percent' => 0,
        'cost_price' => 280.00,
        'stock' => 40,
        'low_stock_threshold' => 6,
        'weight' => '0.42 kg',
        'dimensions' => '22 × 15 × 2.2 cm',
        'sku' => 'CHPT-CRAFT-HANDBK-02',
        'brand' => 'Writers Guild Press',
        'model' => 'WG-WBLD-2025',
        'material' => 'Softcover Acid-Free Paperback',
        'warranty' => 'No Warranty',
        'origin' => 'Philippines',
        'status' => 'active',
        'images' => [
            'https://images.unsplash.com/photo-1578632767115-351597cf2477?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1618365908648-e71bd5716cba?w=800&auto=format&fit=crop&q=80',
        ],
    ],
];

foreach ($productDefs as $def) {
    echo "Creating product: {$def['name']}...\n";
    $imageUrls = $def['images'];
    unset($def['images']);

    // Download real images
    $savedImages = [];
    foreach ($imageUrls as $idx => $url) {
        $path = downloadAndStoreImage($url, 'prod');
        if ($path) {
            $savedImages[] = $path;
        }
    }

    if (!empty($savedImages)) {
        $def['image'] = $savedImages[0];
    }

    // Check if product already exists with same SKU or name
    $product = Product::where('sku', $def['sku'])->orWhere('name', $def['name'])->first();
    if ($product) {
        $product->update($def);
        echo "  Updated existing product ID: {$product->id}\n";
    } else {
        $product = Product::create($def);
        echo "  Created new product ID: {$product->id}\n";
    }

    // Delete existing ProductImage rows to refresh gallery
    ProductImage::where('product_id', $product->id)->delete();

    // Create ProductImage records for all pictures
    foreach ($savedImages as $sortOrder => $imgPath) {
        ProductImage::create([
            'product_id' => $product->id,
            'path' => $imgPath,
            'image_path' => $imgPath,
            'is_main' => ($sortOrder === 0) ? 1 : 0,
            'sort_order' => $sortOrder + 1,
        ]);
        echo "  Attached image: $imgPath (is_main: " . ($sortOrder === 0 ? 1 : 0) . ")\n";
    }
}

echo "\nAll sellers and products seeded successfully!\n";
