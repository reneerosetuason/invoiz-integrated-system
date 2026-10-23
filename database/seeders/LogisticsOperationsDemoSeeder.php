<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Core logistics reference data: vehicle types, centers, service areas and
 * the per-user logistics settings row.
 */
class LogisticsOperationsDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('LogisticsOperationsDemoSeeder skipped outside local/testing.');

            return;
        }

        $vehicleTypes = [
            ['name' => 'motorcycle', 'label' => 'Motorcycle', 'capacity_kg' => 20, 'sort_order' => 1],
            ['name' => 'tricycle', 'label' => 'Tricycle', 'capacity_kg' => 100, 'sort_order' => 2],
            ['name' => 'van', 'label' => 'Van', 'capacity_kg' => 500, 'sort_order' => 3],
            ['name' => 'truck', 'label' => 'Truck', 'capacity_kg' => 2000, 'sort_order' => 4],
            ['name' => 'bicycle', 'label' => 'Bicycle', 'capacity_kg' => 10, 'sort_order' => 5],
        ];

        foreach ($vehicleTypes as $vehicleType) {
            DB::table('vehicle_types')->updateOrInsert(
                ['name' => $vehicleType['name']],
                [
                    'label' => $vehicleType['label'],
                    'capacity_kg' => $vehicleType['capacity_kg'],
                    'is_active' => true,
                    'sort_order' => $vehicleType['sort_order'],
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        $centers = [
            [
                'name' => 'Invoiz Manila Logistics Center',
                'address' => '123 Port Area, Manila',
                'city' => 'Manila',
                'province' => 'Metro Manila',
                'phone' => '0281234567',
            ],
            [
                'name' => 'Invoiz Cebu Logistics Center',
                'address' => '45 Reclamation Area, Cebu City',
                'city' => 'Cebu City',
                'province' => 'Cebu',
                'phone' => '0327654321',
            ],
            [
                'name' => 'Invoiz Central Luzon Sorting Center',
                'address' => '8 MacArthur Highway, San Fernando',
                'city' => 'San Fernando',
                'province' => 'Pampanga',
                'phone' => '0459876543',
            ],
        ];

        foreach ($centers as $center) {
            DB::table('logistics_centers')->updateOrInsert(
                ['name' => $center['name']],
                [
                    'address' => $center['address'],
                    'city' => $center['city'],
                    'province' => $center['province'],
                    'phone' => $center['phone'],
                    'is_active' => true,
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        $serviceAreas = [
            ['name' => 'Manila North', 'center' => 'Invoiz Manila Logistics Center', 'description' => 'Tondo, Caloocan, Valenzuela', 'is_active' => true],
            ['name' => 'Quezon City', 'center' => 'Invoiz Manila Logistics Center', 'description' => 'QC districts 1-6', 'is_active' => true],
            ['name' => 'Makati & Pasig', 'center' => 'Invoiz Manila Logistics Center', 'description' => 'CBD and Ortigas', 'is_active' => true],
            ['name' => 'Manila South', 'center' => 'Invoiz Manila Logistics Center', 'description' => 'Legacy area (inactive)', 'is_active' => false],
            ['name' => 'Cebu Metro', 'center' => 'Invoiz Cebu Logistics Center', 'description' => 'Cebu City and Talisay', 'is_active' => true],
            ['name' => 'Mandaue & Lapu-Lapu', 'center' => 'Invoiz Cebu Logistics Center', 'description' => 'Mactan corridor', 'is_active' => true],
            ['name' => 'Pampanga Central', 'center' => 'Invoiz Central Luzon Sorting Center', 'description' => 'San Fernando and Angeles', 'is_active' => true],
        ];

        foreach ($serviceAreas as $area) {
            $centerId = DB::table('logistics_centers')->where('name', $area['center'])->value('id');

            if (! $centerId) {
                continue;
            }

            DB::table('service_areas')->updateOrInsert(
                ['logistics_center_id' => $centerId, 'name' => $area['name']],
                [
                    'description' => $area['description'],
                    'is_active' => $area['is_active'],
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        $settings = [
            [
                'new_messages' => true,
                'failed_pickups' => true,
                'delivery_requests' => true,
                'failed_deliveries' => true,
                'delivery_completed' => true,
                'rider_applications' => true,
                'application_updates' => true,
                'rider_status_updates' => true,
            ],
        ];

        $logisticsUsers = DB::table('users')
            ->whereIn('role', ['logistics_admin', 'logistics_staff'])
            ->pluck('id');

        $admin = DB::table('users')->where('role', 'logistics_admin')->first();

        if ($admin) {
            $plain = 'demo-logistics-token';
            DB::table('personal_access_tokens')->updateOrInsert(
                ['token' => hash('sha256', $plain)],
                [
                    'tokenable_type' => \App\Models\User::class,
                    'tokenable_id' => $admin->id,
                    'name' => 'demo-logistics-admin',
                    'abilities' => json_encode(['*']),
                    'last_used_at' => null,
                    'expires_at' => now()->addDays(30),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        foreach ($logisticsUsers as $userId) {
            DB::table('logistics_settings')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'notifications' => json_encode($settings[0]),
                    'email_notifications' => true,
                    'delivery' => json_encode([
                        'max_attempts' => 2,
                        'require_proof' => true,
                        'allow_reassignment' => true,
                    ]),
                    'preferences' => json_encode(['theme' => 'light', 'language' => 'en']),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
