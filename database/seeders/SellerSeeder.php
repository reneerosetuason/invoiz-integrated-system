<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SellerSeeder extends Seeder
{
    public function run(): void
    {
        $stores = [
            [
                'email' => 'seller@invoiz.test',
                'name' => 'Marco Santos',
                'password' => 'password',
                'store_name' => 'Invoiz Demo Store',
                'primary_color' => '#16697A',
                'accent_color' => '#F0A202',
                'logo' => 'logos/invoiz-demo.png',
            ],
            [
                'email' => 'tech@invoiz.test',
                'name' => 'Jenna Reyes',
                'password' => 'password',
                'store_name' => 'TechNest Gadgets',
                'primary_color' => '#2563EB',
                'accent_color' => '#93C5FD',
                'logo' => 'logos/technest.png',
            ],
            [
                'email' => 'fresh@invoiz.test',
                'name' => 'Carlo Dela Cruz',
                'password' => 'password',
                'store_name' => 'FreshMart PH',
                'primary_color' => '#16A34A',
                'accent_color' => '#BBF7D0',
                'logo' => 'logos/freshmart.png',
            ],
            [
                'email' => 'school@invoiz.test',
                'name' => 'Mira Valdez',
                'password' => 'password',
                'store_name' => 'SchoolSmart PH',
                'primary_color' => '#2563EB',
                'accent_color' => '#BFDBFE',
                'logo' => null,
                'status' => 'pending',
            ],
            [
                'email' => 'glam@invoiz.test',
                'name' => 'Sofia Lim',
                'password' => 'password',
                'store_name' => 'GlamEssence Beauty',
                'primary_color' => '#DB2777',
                'accent_color' => '#FBCFE8',
                'logo' => null,
                'status' => 'rejected',
            ],
            [
                'email' => 'style@invoiz.test',
                'name' => 'Isabella Cruz',
                'password' => 'password',
                'store_name' => 'StyleHaven Dresses',
                'primary_color' => '#7C3AED',
                'accent_color' => '#DDD6FE',
                'logo' => null,
            ],
            [
                'email' => 'gear@invoiz.test',
                'name' => 'Diego Ramos',
                'password' => 'password',
                'store_name' => 'ActiveGear Sports',
                'primary_color' => '#EA580C',
                'accent_color' => '#FED7AA',
                'logo' => null,
            ],
            [
                'email' => 'home@invoiz.test',
                'name' => 'Elena Torres',
                'password' => 'password',
                'store_name' => 'HomeCraft Living',
                'primary_color' => '#92400E',
                'accent_color' => '#FDE68A',
                'logo' => null,
            ],
        ];

        foreach ($stores as $store) {
            $user = User::updateOrCreate(
                ['email' => $store['email']],
                [
                    'name' => $store['name'],
                    'password' => Hash::make($store['password']),
                    'role' => 'seller',
                    'account_status' => 'active',
                    'is_admin' => false,
                ]
            );

            Seller::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'store_name' => $store['store_name'],
                    'status' => $store['status'] ?? 'approved',
                    'primary_color' => $store['primary_color'],
                    'accent_color' => $store['accent_color'],
                    'logo' => $store['logo'],
                ]
            );
        }
    }
}