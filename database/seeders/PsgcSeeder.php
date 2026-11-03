<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PsgcSeeder extends Seeder
{
    public function run(): void
    {
        ini_set('memory_limit', '512M');

        $dir = database_path('data/psgc');
        if (! is_dir($dir)) {
            $this->command?->error("PSGC data directory not found at $dir");
            return;
        }

        $now = now();

        // 1. Regions
        $regionsFile = "$dir/regions.json";
        if (file_exists($regionsFile)) {
            $regions = json_decode(file_get_contents($regionsFile), true);
            $regionRows = [];
            foreach ($regions as $r) {
                $regionRows[] = [
                    'code' => $r['code'],
                    'name' => $r['name'],
                    'region_name' => $r['regionName'] ?? null,
                    'island_group_code' => $r['islandGroupCode'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('regions')->upsert(
                $regionRows,
                ['code'],
                ['name', 'region_name', 'island_group_code', 'updated_at']
            );
            $this->command?->info("Seeded " . count($regionRows) . " regions.");
        }

        // 2. Provinces
        $provincesFile = "$dir/provinces.json";
        if (file_exists($provincesFile)) {
            $provinces = json_decode(file_get_contents($provincesFile), true);
            $provinceRows = [];
            foreach ($provinces as $p) {
                $provinceRows[] = [
                    'code' => $p['code'],
                    'name' => $p['name'],
                    'region_code' => $p['regionCode'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('provinces')->upsert(
                $provinceRows,
                ['code'],
                ['name', 'region_code', 'updated_at']
            );
            $this->command?->info("Seeded " . count($provinceRows) . " provinces.");
        }

        // Cache provinces map [code => id]
        $provinceMap = DB::table('provinces')->pluck('id', 'code')->all();

        // 3. Municipalities & Cities
        $cmsFile = "$dir/cities-municipalities.json";
        if (file_exists($cmsFile)) {
            $cms = json_decode(file_get_contents($cmsFile), true);
            $municipalityRows = [];
            foreach ($cms as $cm) {
                $pCode = ! empty($cm['provinceCode']) ? $cm['provinceCode'] : null;
                $provinceId = $pCode && isset($provinceMap[$pCode]) ? $provinceMap[$pCode] : null;

                $municipalityRows[] = [
                    'code' => $cm['code'],
                    'name' => $cm['name'],
                    'province_id' => $provinceId,
                    'province_code' => $pCode,
                    'region_code' => $cm['regionCode'],
                    'is_city' => ! empty($cm['isCity']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($municipalityRows, 500) as $chunk) {
                DB::table('municipalities')->upsert(
                    $chunk,
                    ['code'],
                    ['name', 'province_id', 'province_code', 'region_code', 'is_city', 'updated_at']
                );
            }
            $this->command?->info("Seeded " . count($municipalityRows) . " cities and municipalities.");
        }

        // Cache municipality map [code => id]
        $municipalityMap = DB::table('municipalities')->pluck('id', 'code')->all();

        // 4. Barangays
        $brgysFile = "$dir/barangays.json";
        if (file_exists($brgysFile)) {
            $brgys = json_decode(file_get_contents($brgysFile), true);
            $totalBarangays = 0;
            $batch = [];

            foreach ($brgys as $b) {
                $mCode = ! empty($b['municipalityCode']) ? $b['municipalityCode'] : ($b['cityCode'] ?? null);
                $mId = $mCode && isset($municipalityMap[$mCode]) ? $municipalityMap[$mCode] : null;

                if (! $mId) {
                    continue;
                }

                $batch[] = [
                    'code' => $b['code'],
                    'name' => $b['name'],
                    'municipality_id' => $mId,
                    'municipality_code' => $mCode,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) >= 1000) {
                    DB::table('barangays')->upsert(
                        $batch,
                        ['code'],
                        ['name', 'municipality_id', 'municipality_code', 'updated_at']
                    );
                    $totalBarangays += count($batch);
                    $batch = [];
                }
            }

            if (! empty($batch)) {
                DB::table('barangays')->upsert(
                    $batch,
                    ['code'],
                    ['name', 'municipality_id', 'municipality_code', 'updated_at']
                );
                $totalBarangays += count($batch);
                $batch = [];
            }

            unset($brgys);
            $this->command?->info("Seeded {$totalBarangays} barangays.");
        }
    }
}
