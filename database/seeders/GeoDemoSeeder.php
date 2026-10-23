<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * PSGC address reference data used by the logistics address pickers
 * (Logistics\Api\AddressController and the center-application wizard).
 * Region codes match config('logistics.psgc_regions').
 */
class GeoDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('GeoDemoSeeder skipped outside local/testing.');

            return;
        }

        $provinces = [
            ['code' => '130010000', 'name' => 'Metro Manila', 'region_code' => '130000000'],
            ['code' => '030140000', 'name' => 'Bulacan', 'region_code' => '030000000'],
            ['code' => '030540000', 'name' => 'Pampanga', 'region_code' => '030000000'],
            ['code' => '030490000', 'name' => 'Nueva Ecija', 'region_code' => '030000000'],
            ['code' => '070220000', 'name' => 'Cebu', 'region_code' => '070000000'],
            ['code' => '070120000', 'name' => 'Bohol', 'region_code' => '070000000'],
        ];

        foreach ($provinces as $province) {
            DB::table('provinces')->updateOrInsert(
                ['code' => $province['code']],
                [
                    'name' => $province['name'],
                    'region_code' => $province['region_code'],
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        $municipalities = [
            ['code' => '130010010', 'name' => 'Manila', 'province_code' => '130010000'],
            ['code' => '130010020', 'name' => 'Quezon City', 'province_code' => '130010000'],
            ['code' => '130010030', 'name' => 'Makati', 'province_code' => '130010000'],
            ['code' => '130010040', 'name' => 'Pasig', 'province_code' => '130010000'],
            ['code' => '030140010', 'name' => 'Malolos', 'province_code' => '030140000'],
            ['code' => '030140020', 'name' => 'Meycauayan', 'province_code' => '030140000'],
            ['code' => '030140030', 'name' => 'San Jose del Monte', 'province_code' => '030140000'],
            ['code' => '030540010', 'name' => 'San Fernando', 'province_code' => '030540000'],
            ['code' => '030540020', 'name' => 'Angeles', 'province_code' => '030540000'],
            ['code' => '030490010', 'name' => 'Cabanatuan', 'province_code' => '030490000'],
            ['code' => '030490020', 'name' => 'Palayan', 'province_code' => '030490000'],
            ['code' => '070220010', 'name' => 'Cebu City', 'province_code' => '070220000'],
            ['code' => '070220020', 'name' => 'Mandaue', 'province_code' => '070220000'],
            ['code' => '070220030', 'name' => 'Lapu-Lapu', 'province_code' => '070220000'],
            ['code' => '070120010', 'name' => 'Tagbilaran', 'province_code' => '070120000'],
            ['code' => '070120020', 'name' => 'Panglao', 'province_code' => '070120000'],
        ];

        foreach ($municipalities as $municipality) {
            $provinceId = DB::table('provinces')->where('code', $municipality['province_code'])->value('id');
            $regionCode = DB::table('provinces')->where('id', $provinceId)->value('region_code');

            if (! $provinceId) {
                continue;
            }

            DB::table('municipalities')->updateOrInsert(
                ['code' => $municipality['code']],
                [
                    'name' => $municipality['name'],
                    'province_id' => $provinceId,
                    'region_code' => $regionCode,
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        $barangays = [
            ['code' => '130010011', 'name' => 'Barangay 1', 'municipality_code' => '130010010'],
            ['code' => '130010012', 'name' => 'Barangay 2', 'municipality_code' => '130010010'],
            ['code' => '130010013', 'name' => 'San Nicolas', 'municipality_code' => '130010010'],
            ['code' => '130010021', 'name' => 'Batasan Hills', 'municipality_code' => '130010020'],
            ['code' => '130010022', 'name' => 'Commonwealth', 'municipality_code' => '130010020'],
            ['code' => '130010023', 'name' => 'Cubao', 'municipality_code' => '130010020'],
            ['code' => '130010031', 'name' => 'Poblacion', 'municipality_code' => '130010030'],
            ['code' => '130010032', 'name' => 'Bel-Air', 'municipality_code' => '130010030'],
            ['code' => '130010033', 'name' => 'San Lorenzo', 'municipality_code' => '130010030'],
            ['code' => '130010041', 'name' => 'San Antonio', 'municipality_code' => '130010040'],
            ['code' => '130010042', 'name' => 'Kapitolyo', 'municipality_code' => '130010040'],
            ['code' => '030140011', 'name' => 'Barangay Santo Nino', 'municipality_code' => '030140010'],
            ['code' => '030140012', 'name' => 'Barangay San Vicente', 'municipality_code' => '030140010'],
            ['code' => '030140021', 'name' => 'Barangay Poblacion', 'municipality_code' => '030140020'],
            ['code' => '030140031', 'name' => 'Barangay Muzon', 'municipality_code' => '030140030'],
            ['code' => '030540011', 'name' => 'Barangay Del Pilar', 'municipality_code' => '030540010'],
            ['code' => '030540021', 'name' => 'Barangay Balibago', 'municipality_code' => '030540020'],
            ['code' => '030490011', 'name' => 'Barangay San Roque', 'municipality_code' => '030490010'],
            ['code' => '030490021', 'name' => 'Barangay Singalat', 'municipality_code' => '030490020'],
            ['code' => '070220011', 'name' => 'Barangay Lahug', 'municipality_code' => '070220010'],
            ['code' => '070220012', 'name' => 'Barangay Guadalupe', 'municipality_code' => '070220010'],
            ['code' => '070220021', 'name' => 'Barangay Centro', 'municipality_code' => '070220020'],
            ['code' => '070220031', 'name' => 'Barangay Pajo', 'municipality_code' => '070220030'],
            ['code' => '070120011', 'name' => 'Barangay Poblacion', 'municipality_code' => '070120010'],
            ['code' => '070120021', 'name' => 'Barangay Tawala', 'municipality_code' => '070120020'],
        ];

        foreach ($barangays as $barangay) {
            $municipalityId = DB::table('municipalities')->where('code', $barangay['municipality_code'])->value('id');

            if (! $municipalityId) {
                continue;
            }

            DB::table('barangays')->updateOrInsert(
                ['code' => $barangay['code']],
                [
                    'name' => $barangay['name'],
                    'municipality_id' => $municipalityId,
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
