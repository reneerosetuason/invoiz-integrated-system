<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Seller;
use App\Models\Category;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $sellers = Seller::with('user')->get();
        if ($sellers->isEmpty()) return;

        $byEmail = $sellers->keyBy(fn ($s) => $s->user->email ?? '');
        $approvedSellers = $sellers->where('status', 'approved');
        $anySeller = fn () => ($approvedSellers->isNotEmpty() ? $approvedSellers : $sellers)->random()->id;

        $add = function (string $categoryName, ?string $sellerEmail, array $items) use ($byEmail, $anySeller) {
            $categoryId = Category::where('name', $categoryName)->value('id');
            if (! $categoryId) return;

            $sellerId = $sellerEmail && isset($byEmail[$sellerEmail])
                ? $byEmail[$sellerEmail]->id
                : $anySeller();

            foreach ($items as $p) {
                Product::updateOrCreate(
                    ['sku' => $p['sku']],
                    [
                        'seller_id' => $sellerId,
                        'category_id' => $categoryId,
                        'name' => $p['name'],
                        'slug' => \Str::slug($p['name']),
                        'short_description' => $p['description'],
                        'description' => $p['description'],
                        'brand' => $p['brand'],
                        'price' => $p['price'],
                        'cost_price' => $p['cost'],
                        'status' => 'active',
                        'views_count' => rand(150, 4800),
                        'cart_additions' => rand(10, 420),
                    ]
                );
            }
        };

        // ------------------------------------------------------------------
        // Original baby/kids demo products (organized into their categories)
        // ------------------------------------------------------------------
        $add('Baby Clothes & Accessories', null, [
            ['name' => 'Baby Onesie Set', 'brand' => 'TinyTots', 'sku' => 'BT-001', 'price' => 24.99, 'cost' => 12.00, 'description' => 'Soft cotton onesie set for newborns'],
            ['name' => 'Diaper Bag Backpack', 'brand' => 'MomEssentials', 'sku' => 'BA-001', 'price' => 44.99, 'cost' => 20.00, 'description' => 'Multi-function diaper bag backpack'],
        ]);
        $add('Toys & Games', null, [
            ['name' => 'Kids Wooden Blocks', 'brand' => 'PlayLearn', 'sku' => 'TG-001', 'price' => 19.99, 'cost' => 8.00, 'description' => 'Educational wooden building blocks'],
            ['name' => 'Kids Puzzle Set', 'brand' => 'PlayLearn', 'sku' => 'TG-002', 'price' => 15.99, 'cost' => 6.00, 'description' => 'Educational puzzle set for ages 3-6'],
        ]);
        $add('Strollers & Gear', null, [
            ['name' => 'Stroller Compact Fold', 'brand' => 'BabyRide', 'sku' => 'SG-001', 'price' => 149.99, 'cost' => 75.00, 'description' => 'Lightweight compact fold stroller'],
            ['name' => 'Baby Car Seat', 'brand' => 'SafeRide', 'sku' => 'SG-002', 'price' => 199.99, 'cost' => 100.00, 'description' => 'Rear-facing baby car seat with safety harness'],
        ]);
        $add('Nursery Furniture', null, [
            ['name' => 'Nursery Crib Adjustable', 'brand' => 'SleepWell', 'sku' => 'NF-001', 'price' => 299.99, 'cost' => 150.00, 'description' => 'Adjustable height nursery crib'],
        ]);
        $add('Safety & Health', null, [
            ['name' => 'Baby Safety Gate', 'brand' => 'SafeKids', 'sku' => 'SH-001', 'price' => 34.99, 'cost' => 15.00, 'description' => 'Pressure-mounted safety gate'],
            ['name' => 'Baby Bath Tub', 'brand' => 'CleanBaby', 'sku' => 'SH-002', 'price' => 27.99, 'cost' => 12.00, 'description' => 'Foldable baby bath tub with thermometer'],
        ]);
        $add('Educational Materials', null, [
            ['name' => 'Kids Art Set', 'brand' => 'CreativeKids', 'sku' => 'EM-001', 'price' => 29.99, 'cost' => 10.00, 'description' => 'Complete art supplies set for kids'],
        ]);

        // ------------------------------------------------------------------
        // Marketplace categories (organized by store)
        // ------------------------------------------------------------------
        $add('School Supplies', 'school@invoiz.test', [
            ['name' => 'Ballpoint Pen Pack 50pcs', 'brand' => 'WriteWell', 'sku' => 'SS-PEN-001', 'price' => 149.00, 'cost' => 70.00, 'description' => 'Smooth-writing ballpoint pens, box of 50'],
            ['name' => 'Spiral Notebook Set of 5', 'brand' => 'PageOne', 'sku' => 'SS-NTB-002', 'price' => 199.00, 'cost' => 90.00, 'description' => 'College-ruled spiral notebooks, assorted colors'],
            ['name' => 'Student Backpack Waterproof', 'brand' => 'CarryAll', 'sku' => 'SS-BAG-003', 'price' => 549.00, 'cost' => 260.00, 'description' => 'Water-resistant backpack with laptop sleeve'],
            ['name' => 'Geometry Tool Set', 'brand' => 'MathPro', 'sku' => 'SS-GEO-004', 'price' => 129.00, 'cost' => 55.00, 'description' => 'Complete geometry set in metal tin case'],
        ]);

        $add('Makeup', 'glam@invoiz.test', [
            ['name' => 'Velvet Matte Lipstick', 'brand' => 'GlowGirl', 'sku' => 'MK-LIP-001', 'price' => 299.00, 'cost' => 120.00, 'description' => 'Long-wear matte lipstick, transfer-proof'],
            ['name' => 'Eyeshadow Palette 12 Shades', 'brand' => 'GlowGirl', 'sku' => 'MK-EYE-002', 'price' => 499.00, 'cost' => 210.00, 'description' => 'Highly pigmented nude and bold shades'],
            ['name' => 'Liquid Foundation SPF30', 'brand' => 'PureBlend', 'sku' => 'MK-FND-003', 'price' => 449.00, 'cost' => 190.00, 'description' => 'Medium-coverage liquid foundation with SPF'],
            ['name' => 'Makeup Brush Set 12pcs', 'brand' => 'BeautyPro', 'sku' => 'MK-BRS-004', 'price' => 399.00, 'cost' => 160.00, 'description' => 'Synthetic bristle brush set with pouch'],
        ]);

        $add('Dresses', 'style@invoiz.test', [
            ['name' => 'Floral Summer Midi Dress', 'brand' => 'BelleAmie', 'sku' => 'DR-FLR-001', 'price' => 899.00, 'cost' => 380.00, 'description' => 'Flowy floral midi dress, breathable fabric'],
            ['name' => 'Office Pencil Dress', 'brand' => 'BelleAmie', 'sku' => 'DR-OFC-002', 'price' => 999.00, 'cost' => 430.00, 'description' => 'Structured pencil dress for work'],
            ['name' => 'Sequined Evening Gown', 'brand' => 'NightVelvet', 'sku' => 'DR-EVE-003', 'price' => 1899.00, 'cost' => 850.00, 'description' => 'Elegant sequined gown for special occasions'],
            ['name' => 'Casual Linen Sundress', 'brand' => 'BreezeWear', 'sku' => 'DR-SUN-004', 'price' => 749.00, 'cost' => 300.00, 'description' => 'Light linen sundress for everyday wear'],
        ]);

        $add('Furniture', 'home@invoiz.test', [
            ['name' => 'Wooden Study Desk', 'brand' => 'OakCraft', 'sku' => 'FR-DSK-001', 'price' => 4499.00, 'cost' => 2100.00, 'description' => 'Solid wood study desk with drawers'],
            ['name' => 'Ergonomic Office Chair', 'brand' => 'SitWell', 'sku' => 'FR-CHR-002', 'price' => 3899.00, 'cost' => 1800.00, 'description' => 'Adjustable ergonomic chair with lumbar support'],
            ['name' => '3-Seater Fabric Sofa', 'brand' => 'CozyHome', 'sku' => 'FR-SOF-003', 'price' => 12999.00, 'cost' => 6200.00, 'description' => 'Comfortable fabric sofa, sturdy frame'],
            ['name' => 'Bookshelf 5-Tier', 'brand' => 'OakCraft', 'sku' => 'FR-BOK-004', 'price' => 2799.00, 'cost' => 1250.00, 'description' => 'Space-saving 5-tier open bookshelf'],
        ]);

        $add('Toys', 'seller@invoiz.test', [
            ['name' => 'Building Blocks 100pcs', 'brand' => 'BrickFun', 'sku' => 'TY-BLK-001', 'price' => 549.00, 'cost' => 240.00, 'description' => 'Creative building blocks, compatible set'],
            ['name' => 'Remote Control Car', 'brand' => 'SpeedKid', 'sku' => 'TY-RCC-002', 'price' => 899.00, 'cost' => 400.00, 'description' => 'High-speed RC car with rechargeable battery'],
            ['name' => 'Plush Teddy Bear Large', 'brand' => 'CuddleCo', 'sku' => 'TY-TED-003', 'price' => 449.00, 'cost' => 180.00, 'description' => 'Super soft huggable teddy bear, 60cm'],
            ['name' => 'Family Board Game Edition', 'brand' => 'FunTable', 'sku' => 'TY-BRD-004', 'price' => 699.00, 'cost' => 300.00, 'description' => 'Classic strategy board game for the family'],
        ]);

        $add('Sports Equipment', 'gear@invoiz.test', [
            ['name' => 'Yoga Mat 6mm', 'brand' => 'FlexFit', 'sku' => 'SP-YOG-001', 'price' => 599.00, 'cost' => 250.00, 'description' => 'Non-slip TPE yoga mat with carry strap'],
            ['name' => 'Adjustable Dumbbell Set 20kg', 'brand' => 'IronPeak', 'sku' => 'SP-DMB-002', 'price' => 2499.00, 'cost' => 1150.00, 'description' => 'Space-saving adjustable dumbbell pair'],
            ['name' => 'Basketball Official Size', 'brand' => 'HoopStar', 'sku' => 'SP-BKB-003', 'price' => 899.00, 'cost' => 380.00, 'description' => 'Indoor/outdoor composite leather basketball'],
            ['name' => 'Resistance Bands Set', 'brand' => 'FlexFit', 'sku' => 'SP-RES-004', 'price' => 349.00, 'cost' => 140.00, 'description' => '5-level resistance bands with door anchor'],
        ]);

        $add('Jewelry', 'glam@invoiz.test', [
            ['name' => 'Gold-Plated Necklace Set', 'brand' => 'LuxeAura', 'sku' => 'JW-NCK-001', 'price' => 1299.00, 'cost' => 550.00, 'description' => 'Elegant gold-plated necklace and earrings set'],
            ['name' => 'Freshwater Pearl Earrings', 'brand' => 'LuxeAura', 'sku' => 'JW-EAR-002', 'price' => 899.00, 'cost' => 380.00, 'description' => 'Genuine freshwater pearl drop earrings'],
            ['name' => 'Stainless Steel Watch', 'brand' => 'TimeLux', 'sku' => 'JW-WAT-003', 'price' => 2199.00, 'cost' => 980.00, 'description' => 'Water-resistant classic steel watch'],
            ['name' => 'Silver Charm Bracelet', 'brand' => 'LuxeAura', 'sku' => 'JW-BRC-004', 'price' => 749.00, 'cost' => 300.00, 'description' => '925 silver bracelet with charm accents'],
        ]);

        $add('Gadgets', 'tech@invoiz.test', [
            ['name' => 'Wireless Earbuds Pro', 'brand' => 'SonicWave', 'sku' => 'GD-EAR-001', 'price' => 1499.00, 'cost' => 650.00, 'description' => 'True wireless earbuds with noise reduction'],
            ['name' => 'Power Bank 20000mAh', 'brand' => 'VoltMax', 'sku' => 'GD-PWB-002', 'price' => 999.00, 'cost' => 430.00, 'description' => 'Fast-charging dual-USB power bank'],
            ['name' => 'Mini Bluetooth Speaker', 'brand' => 'SonicWave', 'sku' => 'GD-SPK-003', 'price' => 799.00, 'cost' => 330.00, 'description' => 'Portable speaker with deep bass, IPX6'],
            ['name' => 'Smart Watch Fitness Tracker', 'brand' => 'PulseTech', 'sku' => 'GD-WCH-004', 'price' => 1899.00, 'cost' => 820.00, 'description' => 'Heart-rate, sleep and step tracking watch'],
        ]);

        $add('Appliances', 'fresh@invoiz.test', [
            ['name' => 'Rice Cooker 1.8L', 'brand' => 'KusinaPro', 'sku' => 'AP-RIC-001', 'price' => 1299.00, 'cost' => 580.00, 'description' => 'Non-stick rice cooker with keep-warm function'],
            ['name' => 'Stand Fan 16 inch', 'brand' => 'BreezeAir', 'sku' => 'AP-FAN-002', 'price' => 1099.00, 'cost' => 480.00, 'description' => '3-speed adjustable stand fan with timer'],
            ['name' => 'Microwave Oven 20L', 'brand' => 'HeatWave', 'sku' => 'AP-MIC-003', 'price' => 3499.00, 'cost' => 1650.00, 'description' => 'Compact microwave with 5 power levels'],
            ['name' => '3-in-1 Blender', 'brand' => 'KusinaPro', 'sku' => 'AP-BLN-004', 'price' => 1599.00, 'cost' => 700.00, 'description' => 'Blender, grinder and chopper in one'],
        ]);

        $add('Tools', 'home@invoiz.test', [
            ['name' => 'Cordless Drill 12V', 'brand' => 'ToolForce', 'sku' => 'TL-DRL-001', 'price' => 1999.00, 'cost' => 900.00, 'description' => 'Compact cordless drill with 2 batteries'],
            ['name' => 'Screwdriver Set 32pcs', 'brand' => 'ToolForce', 'sku' => 'TL-SCR-002', 'price' => 349.00, 'cost' => 140.00, 'description' => 'Precision screwdriver set with magnetic tips'],
            ['name' => 'Tool Box Set 108pcs', 'brand' => 'BuildRight', 'sku' => 'TL-BOX-003', 'price' => 2499.00, 'cost' => 1100.00, 'description' => 'Complete home repair tool kit with case'],
            ['name' => 'Adjustable Wrench Set', 'brand' => 'BuildRight', 'sku' => 'TL-WRN-004', 'price' => 399.00, 'cost' => 160.00, 'description' => '3-piece chrome vanadium wrench set'],
        ]);

        // ------------------------------------------------------------------
        // Additional 100 products — keeps categories sama sama (grouped)
        // ------------------------------------------------------------------
        $add('Baby Clothes & Accessories', null, [
            ['name' => 'Baby Romper Pack 3pcs', 'brand' => 'TinyTots', 'sku' => 'BT-101', 'price' => 299.00, 'cost' => 140.00, 'description' => 'Soft cotton baby romper pack'],
            ['name' => 'Baby Socks Set 6pcs', 'brand' => 'TinyTots', 'sku' => 'BT-102', 'price' => 149.00, 'cost' => 60.00, 'description' => 'Cute animal baby socks set'],
            ['name' => 'Baby Hat & Mittens Set', 'brand' => 'CozyBaby', 'sku' => 'BT-103', 'price' => 199.00, 'cost' => 85.00, 'description' => 'Warm hat and mittens for newborns'],
            ['name' => 'Baby Bib Set 5pcs', 'brand' => 'CleanBaby', 'sku' => 'BT-104', 'price' => 179.00, 'cost' => 70.00, 'description' => 'Waterproof baby bibs with pocket'],
            ['name' => 'Baby Muslin Blanket', 'brand' => 'CozyBaby', 'sku' => 'BT-105', 'price' => 249.00, 'cost' => 110.00, 'description' => 'Breathable muslin swaddle blanket'],
        ]);
        $add('Toys & Games', null, [
            ['name' => 'Wooden Stacking Rings', 'brand' => 'PlayLearn', 'sku' => 'TG-101', 'price' => 249.00, 'cost' => 100.00, 'description' => 'Colorful wooden stacking rings toy'],
            ['name' => 'Magnetic Building Tiles 42pcs', 'brand' => 'BrickFun', 'sku' => 'TG-102', 'price' => 599.00, 'cost' => 260.00, 'description' => 'Magnetic tiles for creative building'],
            ['name' => 'Plush Dinosaur Toy', 'brand' => 'CuddleCo', 'sku' => 'TG-103', 'price' => 349.00, 'cost' => 140.00, 'description' => 'Soft plush dinosaur for kids'],
            ['name' => 'Kids Doctor Play Set', 'brand' => 'PlayLearn', 'sku' => 'TG-104', 'price' => 399.00, 'cost' => 160.00, 'description' => 'Doctor kit with stethoscope and tools'],
            ['name' => 'Jumbo Coloring Book', 'brand' => 'CreativeKids', 'sku' => 'TG-105', 'price' => 179.00, 'cost' => 70.00, 'description' => 'Giant coloring book with 100 pages'],
        ]);
        $add('Strollers & Gear', null, [
            ['name' => 'Umbrella Stroller Lightweight', 'brand' => 'BabyRide', 'sku' => 'SG-101', 'price' => 2999.00, 'cost' => 1400.00, 'description' => 'Compact umbrella stroller for travel'],
            ['name' => 'Baby Carrier Wrap', 'brand' => 'CozyBaby', 'sku' => 'SG-102', 'price' => 799.00, 'cost' => 350.00, 'description' => 'Ergonomic baby carrier wrap'],
            ['name' => 'Infant Car Mirror', 'brand' => 'SafeRide', 'sku' => 'SG-103', 'price' => 349.00, 'cost' => 140.00, 'description' => 'Wide-angle car mirror for baby'],
            ['name' => 'Stroller Organizer Bag', 'brand' => 'MomEssentials', 'sku' => 'SG-104', 'price' => 449.00, 'cost' => 190.00, 'description' => 'Organizer caddy for stroller handle'],
            ['name' => 'Baby Swaddle Wrap Set', 'brand' => 'TinyTots', 'sku' => 'SG-105', 'price' => 299.00, 'cost' => 120.00, 'description' => 'Adjustable swaddle wrap set 3pcs'],
        ]);
        $add('Nursery Furniture', null, [
            ['name' => 'Changing Table with Drawers', 'brand' => 'SleepWell', 'sku' => 'NF-101', 'price' => 3999.00, 'cost' => 1800.00, 'description' => 'Changing table with storage drawers'],
            ['name' => 'Rocking Chair Classic', 'brand' => 'CozyHome', 'sku' => 'NF-102', 'price' => 5499.00, 'cost' => 2500.00, 'description' => 'Classic nursery rocking chair'],
            ['name' => 'Kids Storage Box Wooden', 'brand' => 'PlayLearn', 'sku' => 'NF-103', 'price' => 599.00, 'cost' => 260.00, 'description' => 'Wooden toy storage box with lid'],
            ['name' => 'Star Projector Night Light', 'brand' => 'SleepWell', 'sku' => 'NF-104', 'price' => 499.00, 'cost' => 210.00, 'description' => 'Starry night projector lamp'],
            ['name' => 'Foam Puzzle Play Mat', 'brand' => 'PlayLearn', 'sku' => 'NF-105', 'price' => 799.00, 'cost' => 340.00, 'description' => 'Interlocking foam play mat 12pcs'],
        ]);
        $add('Safety & Health', null, [
            ['name' => 'Digital Baby Thermometer', 'brand' => 'SafeKids', 'sku' => 'SH-101', 'price' => 299.00, 'cost' => 120.00, 'description' => 'Fast digital thermometer for babies'],
            ['name' => 'Corner Guards 8pcs', 'brand' => 'SafeKids', 'sku' => 'SH-102', 'price' => 179.00, 'cost' => 70.00, 'description' => 'Clear corner guards for table edges'],
            ['name' => 'Outlet Covers 12pcs', 'brand' => 'SafeKids', 'sku' => 'SH-103', 'price' => 149.00, 'cost' => 55.00, 'description' => 'Child-proof outlet covers'],
            ['name' => 'Baby Nail Clipper Set', 'brand' => 'CleanBaby', 'sku' => 'SH-104', 'price' => 199.00, 'cost' => 80.00, 'description' => 'Safe baby nail clipper kit'],
            ['name' => 'Mini Humidifier', 'brand' => 'CleanBaby', 'sku' => 'SH-105', 'price' => 599.00, 'cost' => 250.00, 'description' => 'USB mini humidifier for nursery'],
        ]);
        $add('Educational Materials', null, [
            ['name' => 'Flash Cards Alphabet', 'brand' => 'CreativeKids', 'sku' => 'EM-101', 'price' => 199.00, 'cost' => 80.00, 'description' => 'Alphabet flash cards with pictures'],
            ['name' => 'Number Learning Board', 'brand' => 'PlayLearn', 'sku' => 'EM-102', 'price' => 349.00, 'cost' => 140.00, 'description' => 'Wooden number and counting board'],
            ['name' => 'Science Kit for Kids', 'brand' => 'CreativeKids', 'sku' => 'EM-103', 'price' => 499.00, 'cost' => 210.00, 'description' => 'Beginner science experiment kit'],
            ['name' => 'Story Books Set 10pcs', 'brand' => 'PageOne', 'sku' => 'EM-104', 'price' => 549.00, 'cost' => 240.00, 'description' => 'Classic story books for kids'],
            ['name' => 'Magnetic Letters & Numbers', 'brand' => 'PlayLearn', 'sku' => 'EM-105', 'price' => 299.00, 'cost' => 120.00, 'description' => 'Magnetic alphabet set for fridge'],
        ]);
        $add('School Supplies', 'school@invoiz.test', [
            ['name' => 'Gel Pen Set 12 Colors', 'brand' => 'WriteWell', 'sku' => 'SS-PEN-101', 'price' => 179.00, 'cost' => 75.00, 'description' => 'Smooth gel pens, 12 vibrant colors'],
            ['name' => 'Hardcover Journal A5', 'brand' => 'PageOne', 'sku' => 'SS-NTB-101', 'price' => 149.00, 'cost' => 65.00, 'description' => 'Hardcover lined journal 200 pages'],
            ['name' => 'Pencil Case Canvas', 'brand' => 'CarryAll', 'sku' => 'SS-CAS-101', 'price' => 129.00, 'cost' => 55.00, 'description' => 'Canvas pencil pouch with zipper'],
            ['name' => 'Scientific Calculator', 'brand' => 'MathPro', 'sku' => 'SS-CAL-101', 'price' => 599.00, 'cost' => 260.00, 'description' => 'Scientific calculator 240 functions'],
            ['name' => 'Highlighter Pack 6pcs', 'brand' => 'WriteWell', 'sku' => 'SS-HIG-101', 'price' => 99.00, 'cost' => 40.00, 'description' => 'Pastel highlighters set'],
            ['name' => 'Sticky Notes 6 Pads', 'brand' => 'PageOne', 'sku' => 'SS-STK-101', 'price' => 89.00, 'cost' => 35.00, 'description' => 'Sticky memo pads assorted colors'],
            ['name' => 'Lunch Box Bento', 'brand' => 'CarryAll', 'sku' => 'SS-BOX-101', 'price' => 249.00, 'cost' => 110.00, 'description' => 'Bento lunch box with compartments'],
        ]);
        $add('Makeup', 'glam@invoiz.test', [
            ['name' => 'Lip Gloss Set 6pcs', 'brand' => 'GlowGirl', 'sku' => 'MK-LIP-101', 'price' => 349.00, 'cost' => 140.00, 'description' => 'Glossy lip gloss collection'],
            ['name' => 'Waterproof Mascara', 'brand' => 'BeautyPro', 'sku' => 'MK-MAS-101', 'price' => 279.00, 'cost' => 110.00, 'description' => 'Volume waterproof mascara'],
            ['name' => 'Concealer Palette', 'brand' => 'PureBlend', 'sku' => 'MK-CON-101', 'price' => 399.00, 'cost' => 160.00, 'description' => 'Cream concealer palette 6 shades'],
            ['name' => 'Beauty Sponge Set', 'brand' => 'BeautyPro', 'sku' => 'MK-SPO-101', 'price' => 149.00, 'cost' => 60.00, 'description' => 'Makeup sponge blender set'],
            ['name' => 'Lip Liner Pencil', 'brand' => 'GlowGirl', 'sku' => 'MK-LIN-101', 'price' => 129.00, 'cost' => 50.00, 'description' => 'Retractable lip liner'],
            ['name' => 'False Eyelashes 5 Pairs', 'brand' => 'BeautyPro', 'sku' => 'MK-LAS-101', 'price' => 199.00, 'cost' => 80.00, 'description' => 'Natural false lashes set'],
            ['name' => 'Setting Spray', 'brand' => 'PureBlend', 'sku' => 'MK-SET-101', 'price' => 249.00, 'cost' => 100.00, 'description' => 'Long-lasting makeup setting spray'],
        ]);
        $add('Dresses', 'style@invoiz.test', [
            ['name' => 'Wrap Dress Polka Dot', 'brand' => 'BelleAmie', 'sku' => 'DR-WRP-101', 'price' => 799.00, 'cost' => 340.00, 'description' => 'Chic wrap dress with polka dots'],
            ['name' => 'Blazer Dress Formal', 'brand' => 'BelleAmie', 'sku' => 'DR-BLZ-101', 'price' => 1199.00, 'cost' => 520.00, 'description' => 'Tailored blazer dress for office'],
            ['name' => 'Maxi Dress Boho', 'brand' => 'BreezeWear', 'sku' => 'DR-MAX-101', 'price' => 999.00, 'cost' => 430.00, 'description' => 'Boho floral maxi dress'],
            ['name' => 'Denim Shirt Dress', 'brand' => 'BreezeWear', 'sku' => 'DR-DEN-101', 'price' => 849.00, 'cost' => 360.00, 'description' => 'Casual denim shirt dress'],
            ['name' => 'Tiered Ruffle Dress', 'brand' => 'BelleAmie', 'sku' => 'DR-RUF-101', 'price' => 899.00, 'cost' => 380.00, 'description' => 'Tiered ruffle midi dress'],
            ['name' => 'Sheath Dress Classic', 'brand' => 'NightVelvet', 'sku' => 'DR-SHE-101', 'price' => 1099.00, 'cost' => 480.00, 'description' => 'Classic sheath dress knee-length'],
            ['name' => 'Lace Cocktail Dress', 'brand' => 'NightVelvet', 'sku' => 'DR-LAC-101', 'price' => 1299.00, 'cost' => 560.00, 'description' => 'Elegant lace cocktail dress'],
        ]);
        $add('Furniture', 'home@invoiz.test', [
            ['name' => 'Corner Computer Desk', 'brand' => 'OakCraft', 'sku' => 'FR-COR-101', 'price' => 3499.00, 'cost' => 1600.00, 'description' => 'L-shaped corner computer desk'],
            ['name' => 'Folding Dining Chair', 'brand' => 'SitWell', 'sku' => 'FR-FOL-101', 'price' => 899.00, 'cost' => 380.00, 'description' => 'Foldable wooden dining chair'],
            ['name' => 'Recliner Single Seat', 'brand' => 'CozyHome', 'sku' => 'FR-REC-101', 'price' => 8999.00, 'cost' => 4200.00, 'description' => 'Leather recliner with cup holder'],
            ['name' => 'TV Stand Modern', 'brand' => 'OakCraft', 'sku' => 'FR-TV-101', 'price' => 2999.00, 'cost' => 1350.00, 'description' => 'Modern TV stand with shelves'],
            ['name' => 'Standing Desk Converter', 'brand' => 'SitWell', 'sku' => 'FR-STD-101', 'price' => 2499.00, 'cost' => 1100.00, 'description' => 'Adjustable standing desk riser'],
            ['name' => 'Bar Stool Set 2pcs', 'brand' => 'OakCraft', 'sku' => 'FR-BAR-101', 'price' => 1999.00, 'cost' => 880.00, 'description' => 'Modern bar stool set of 2'],
            ['name' => 'Ottoman Storage', 'brand' => 'CozyHome', 'sku' => 'FR-OTT-101', 'price' => 1299.00, 'cost' => 560.00, 'description' => 'Fabric ottoman with storage'],
        ]);
        $add('Toys', 'seller@invoiz.test', [
            ['name' => 'LEGO City Set 300pcs', 'brand' => 'BrickFun', 'sku' => 'TY-LEG-101', 'price' => 1299.00, 'cost' => 580.00, 'description' => 'City building blocks 300 pieces'],
            ['name' => 'Drone Mini with Camera', 'brand' => 'SpeedKid', 'sku' => 'TY-DRN-101', 'price' => 1499.00, 'cost' => 650.00, 'description' => 'Mini drone with HD camera'],
            ['name' => 'Plush Unicorn 40cm', 'brand' => 'CuddleCo', 'sku' => 'TY-UNI-101', 'price' => 399.00, 'cost' => 160.00, 'description' => 'Rainbow plush unicorn'],
            ['name' => 'Chess Board Wooden', 'brand' => 'FunTable', 'sku' => 'TY-CHE-101', 'price' => 549.00, 'cost' => 230.00, 'description' => 'Classic wooden chess set'],
            ['name' => 'Marble Run 80pcs', 'brand' => 'BrickFun', 'sku' => 'TY-MAR-101', 'price' => 699.00, 'cost' => 300.00, 'description' => 'Marble run building set'],
            ['name' => 'Train Set Electric', 'brand' => 'SpeedKid', 'sku' => 'TY-TRA-101', 'price' => 899.00, 'cost' => 380.00, 'description' => 'Electric train track set'],
            ['name' => 'Action Figure 6pcs', 'brand' => 'CuddleCo', 'sku' => 'TY-ACT-101', 'price' => 499.00, 'cost' => 210.00, 'description' => 'Superhero action figures set'],
        ]);
        $add('Sports Equipment', 'gear@invoiz.test', [
            ['name' => 'Foam Roller', 'brand' => 'FlexFit', 'sku' => 'SP-ROL-101', 'price' => 349.00, 'cost' => 140.00, 'description' => 'High-density foam roller'],
            ['name' => 'Kettlebell 8kg', 'brand' => 'IronPeak', 'sku' => 'SP-KET-101', 'price' => 999.00, 'cost' => 430.00, 'description' => 'Cast iron kettlebell 8kg'],
            ['name' => 'Soccer Ball Size 5', 'brand' => 'HoopStar', 'sku' => 'SP-SOC-101', 'price' => 599.00, 'cost' => 250.00, 'description' => 'Official match soccer ball'],
            ['name' => 'Jump Rope Speed', 'brand' => 'FlexFit', 'sku' => 'SP-JMP-101', 'price' => 199.00, 'cost' => 80.00, 'description' => 'Speed jump rope adjustable'],
            ['name' => 'Pilates Ball 65cm', 'brand' => 'FlexFit', 'sku' => 'SP-PIL-101', 'price' => 499.00, 'cost' => 210.00, 'description' => 'Anti-burst pilates ball'],
            ['name' => 'Doorway Pull Up Bar', 'brand' => 'IronPeak', 'sku' => 'SP-PUL-101', 'price' => 799.00, 'cost' => 340.00, 'description' => 'Doorway pull-up bar no drilling'],
            ['name' => 'Boxing Gloves 12oz', 'brand' => 'IronPeak', 'sku' => 'SP-BOX-101', 'price' => 699.00, 'cost' => 300.00, 'description' => 'Training boxing gloves'],
        ]);
        $add('Jewelry', 'glam@invoiz.test', [
            ['name' => 'Layered Choker Set', 'brand' => 'LuxeAura', 'sku' => 'JW-CHO-101', 'price' => 599.00, 'cost' => 250.00, 'description' => 'Boho layered choker necklace set'],
            ['name' => 'Gold Hoop Earrings', 'brand' => 'LuxeAura', 'sku' => 'JW-HOO-101', 'price' => 399.00, 'cost' => 160.00, 'description' => 'Classic gold hoop earrings'],
            ['name' => 'Leather Strap Watch', 'brand' => 'TimeLux', 'sku' => 'JW-LEA-101', 'price' => 1499.00, 'cost' => 650.00, 'description' => 'Vintage leather strap watch'],
            ['name' => 'Beaded Bracelet Set', 'brand' => 'LuxeAura', 'sku' => 'JW-BEA-101', 'price' => 299.00, 'cost' => 120.00, 'description' => 'Bohemian beaded bracelet stack'],
            ['name' => 'Heart Pendant Necklace', 'brand' => 'LuxeAura', 'sku' => 'JW-HEA-101', 'price' => 499.00, 'cost' => 210.00, 'description' => 'Gold heart pendant necklace'],
            ['name' => 'Pearl Stud Earrings', 'brand' => 'LuxeAura', 'sku' => 'JW-STU-101', 'price' => 349.00, 'cost' => 140.00, 'description' => 'Classic pearl stud earrings'],
            ['name' => 'Metal Watch Band', 'brand' => 'TimeLux', 'sku' => 'JW-BAN-101', 'price' => 799.00, 'cost' => 340.00, 'description' => 'Stainless steel mesh watch band'],
        ]);
        $add('Gadgets', 'tech@invoiz.test', [
            ['name' => 'Wireless Headphones', 'brand' => 'SonicWave', 'sku' => 'GD-HEA-101', 'price' => 1299.00, 'cost' => 560.00, 'description' => 'Over-ear wireless headphones'],
            ['name' => 'Fast Charger 65W', 'brand' => 'VoltMax', 'sku' => 'GD-CHA-101', 'price' => 699.00, 'cost' => 300.00, 'description' => '65W GaN fast charger'],
            ['name' => 'Soundbar Mini', 'brand' => 'SonicWave', 'sku' => 'GD-SOU-101', 'price' => 1499.00, 'cost' => 650.00, 'description' => 'Compact soundbar for TV'],
            ['name' => 'Fitness Band', 'brand' => 'PulseTech', 'sku' => 'GD-FIT-101', 'price' => 999.00, 'cost' => 430.00, 'description' => 'Smart fitness band with display'],
            ['name' => 'Earbuds Case Silicone', 'brand' => 'SonicWave', 'sku' => 'GD-CAS-101', 'price' => 149.00, 'cost' => 60.00, 'description' => 'Silicone protective case'],
            ['name' => 'Car Phone Holder', 'brand' => 'VoltMax', 'sku' => 'GD-HOL-101', 'price' => 249.00, 'cost' => 100.00, 'description' => 'Dashboard car phone mount'],
            ['name' => 'LED Strip Lights 5m', 'brand' => 'VoltMax', 'sku' => 'GD-LED-101', 'price' => 399.00, 'cost' => 160.00, 'description' => 'RGB LED strip with remote'],
        ]);
        $add('Appliances', 'fresh@invoiz.test', [
            ['name' => 'Induction Cooker', 'brand' => 'KusinaPro', 'sku' => 'AP-IND-101', 'price' => 1999.00, 'cost' => 880.00, 'description' => 'Single induction cooker'],
            ['name' => 'Air Purifier', 'brand' => 'BreezeAir', 'sku' => 'AP-AIR-101', 'price' => 3499.00, 'cost' => 1600.00, 'description' => 'HEPA air purifier for home'],
            ['name' => 'Electric Kettle 1.7L', 'brand' => 'KusinaPro', 'sku' => 'AP-KET-101', 'price' => 599.00, 'cost' => 250.00, 'description' => 'Stainless steel electric kettle'],
            ['name' => 'Hand Mixer 5 Speed', 'brand' => 'KusinaPro', 'sku' => 'AP-MIX-101', 'price' => 799.00, 'cost' => 340.00, 'description' => 'Electric hand mixer 5 speeds'],
            ['name' => 'Coffee Maker Drip', 'brand' => 'HeatWave', 'sku' => 'AP-COF-101', 'price' => 1299.00, 'cost' => 560.00, 'description' => 'Drip coffee maker 10 cups'],
            ['name' => 'Handheld Vacuum Cleaner', 'brand' => 'BreezeAir', 'sku' => 'AP-VAC-101', 'price' => 1499.00, 'cost' => 650.00, 'description' => 'Cordless handheld vacuum'],
            ['name' => 'Toaster 2 Slice', 'brand' => 'HeatWave', 'sku' => 'AP-TOA-101', 'price' => 699.00, 'cost' => 300.00, 'description' => '2-slice pop-up toaster'],
        ]);
        $add('Tools', 'home@invoiz.test', [
            ['name' => 'Angle Grinder 4 inch', 'brand' => 'ToolForce', 'sku' => 'TL-ANG-101', 'price' => 1499.00, 'cost' => 650.00, 'description' => 'Powerful angle grinder 750W'],
            ['name' => 'Hammer Claw 16oz', 'brand' => 'ToolForce', 'sku' => 'TL-HAM-101', 'price' => 349.00, 'cost' => 140.00, 'description' => 'Fiberglass claw hammer'],
            ['name' => 'Measuring Tape 5m', 'brand' => 'BuildRight', 'sku' => 'TL-TAP-101', 'price' => 149.00, 'cost' => 60.00, 'description' => 'Retractable measuring tape'],
            ['name' => 'Socket Set 46pcs', 'brand' => 'BuildRight', 'sku' => 'TL-SOC-101', 'price' => 1299.00, 'cost' => 560.00, 'description' => 'Drive socket set 46 pieces'],
            ['name' => 'Handsaw 12 inch', 'brand' => 'ToolForce', 'sku' => 'TL-SAW-101', 'price' => 299.00, 'cost' => 120.00, 'description' => 'Sharp handsaw for wood'],
            ['name' => 'Pliers Set 3pcs', 'brand' => 'ToolForce', 'sku' => 'TL-PLI-101', 'price' => 279.00, 'cost' => 110.00, 'description' => 'Pliers set 3-piece'],
            ['name' => 'Work Gloves Pair', 'brand' => 'BuildRight', 'sku' => 'TL-GLO-101', 'price' => 149.00, 'cost' => 60.00, 'description' => 'Heavy-duty work gloves'],
        ]);
    }
}
