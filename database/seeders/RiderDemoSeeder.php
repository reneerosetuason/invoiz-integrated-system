<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Rider accounts plus the application/verification workflow records.
 * Deliveries, earnings and locations are seeded by DeliveryDemoSeeder.
 */
class RiderDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('RiderDemoSeeder skipped outside local/testing.');

            return;
        }

        $password = Hash::make('password123');

        $riderUsers = [
            ['email' => 'rider1@test.com', 'first_name' => 'Rider', 'last_name' => 'One', 'center' => 'Invoiz Manila Logistics Center', 'phone' => '09171110001'],
            ['email' => 'rider2@test.com', 'first_name' => 'Rider', 'last_name' => 'Two', 'center' => 'Invoiz Manila Logistics Center', 'phone' => '09171110002'],
            ['email' => 'rider3@test.com', 'first_name' => 'Rider', 'last_name' => 'Three', 'center' => 'Invoiz Cebu Logistics Center', 'phone' => '09171110003'],
        ];

        foreach ($riderUsers as $user) {
            $centerId = DB::table('logistics_centers')->where('name', $user['center'])->value('id');

            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                [
                    'name' => $user['first_name'].' '.$user['last_name'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role' => 'rider',
                    'center_id' => $centerId,
                    'phone' => $user['phone'],
                    'sex' => 'other',
                    'birthday' => '1995-01-15',
                    'age' => 31,
                    'password' => $password,
                    'approval_status' => 'approved',
                    'status' => 'active',
                    'account_status' => 'active',
                    'is_admin' => false,
                    'email_verified_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        $riders = [
            [
                'email' => 'rider1@test.com',
                'name' => 'Rider One',
                'phone' => '09171110001',
                'center' => 'Invoiz Manila Logistics Center',
                'area' => 'Manila North',
                'vehicle_type' => 'Motorcycle',
                'license_plate' => 'ABC-1234',
                'capacity' => 20,
                'verification' => 'verified',
                'status' => 'available',
                'is_verified' => true,
                'is_online' => true,
            ],
            [
                'email' => 'rider2@test.com',
                'name' => 'Rider Two',
                'phone' => '09171110002',
                'center' => 'Invoiz Manila Logistics Center',
                'area' => 'Quezon City',
                'vehicle_type' => 'Van',
                'license_plate' => 'XYZ-5678',
                'capacity' => 500,
                'verification' => 'pending',
                'status' => 'delivering',
                'is_verified' => false,
                'is_online' => true,
            ],
            [
                'email' => 'rider3@test.com',
                'name' => 'Rider Three',
                'phone' => '09171110003',
                'center' => 'Invoiz Cebu Logistics Center',
                'area' => 'Cebu Metro',
                'vehicle_type' => 'Tricycle',
                'license_plate' => 'CEB-9012',
                'capacity' => 100,
                'verification' => 'rejected',
                'status' => 'inactive',
                'is_verified' => false,
                'is_online' => false,
            ],
        ];

        foreach ($riders as $rider) {
            $userId = DB::table('users')->where('email', $rider['email'])->value('id');
            $centerId = DB::table('logistics_centers')->where('name', $rider['center'])->value('id');
            $areaId = DB::table('service_areas')->where('name', $rider['area'])->value('id');

            DB::table('riders')->updateOrInsert(
                ['email' => $rider['email']],
                [
                    'user_id' => $userId,
                    'center_id' => $centerId,
                    'service_area_id' => $areaId,
                    'name' => $rider['name'],
                    'phone' => $rider['phone'],
                    'vehicle_type' => $rider['vehicle_type'],
                    'license_plate' => $rider['license_plate'],
                    'vehicle_capacity_kg' => $rider['capacity'],
                    'vehicle_verification' => $rider['verification'],
                    'vehicle_verification_note' => $rider['verification'] === 'rejected'
                        ? 'Vehicle registration document is expired.'
                        : null,
                    'vehicle_verified_at' => $rider['verification'] === 'verified' ? now()->subDays(10) : null,
                    'vehicle_verified_by' => $rider['verification'] === 'verified'
                        ? DB::table('users')->where('role', 'logistics_admin')->value('id')
                        : null,
                    'status' => $rider['status'],
                    'is_verified' => $rider['is_verified'],
                    'approved_at' => $rider['is_verified'] ? now()->subDays(10) : null,
                    'is_online' => $rider['is_online'],
                    'avatar' => null,
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        $adminId = DB::table('users')->where('role', 'logistics_admin')->value('id');
        $manilaCenter = DB::table('logistics_centers')->where('name', 'Invoiz Manila Logistics Center')->value('id');
        $manilaArea = DB::table('service_areas')->where('name', 'Manila North')->value('id');

        $applications = [
            [
                'name' => 'Pending Applicant',
                'email' => 'applicant1@test.com',
                'phone' => '09172220001',
                'vehicle_type' => 'Motorcycle',
                'status' => 'pending',
                'documents' => ['valid_id', 'proof_of_address'],
            ],
            [
                'name' => 'Approved Applicant',
                'email' => 'applicant2@test.com',
                'phone' => '09172220002',
                'vehicle_type' => 'Van',
                'status' => 'approved',
                'documents' => ['valid_id', 'proof_of_address', 'vehicle_registration'],
            ],
            [
                'name' => 'Rejected Applicant',
                'email' => 'applicant3@test.com',
                'phone' => '09172220003',
                'vehicle_type' => 'Tricycle',
                'status' => 'rejected',
                'documents' => ['valid_id'],
            ],
            [
                'name' => 'Borrowed Vehicle Applicant',
                'email' => 'applicant4@test.com',
                'phone' => '09172220004',
                'vehicle_type' => 'Motorcycle',
                'status' => 'pending',
                'documents' => ['valid_id', 'owner_valid_id', 'authorization_letter'],
            ],
        ];

        foreach ($applications as $application) {
            DB::table('rider_applications')->updateOrInsert(
                ['email' => $application['email']],
                [
                    'name' => $application['name'],
                    'middle_initial' => 'D',
                    'sex' => 'male',
                    'birthday' => '1997-05-20',
                    'age' => 29,
                    'phone' => $application['phone'],
                    'address' => '24 Sample Street, Manila',
                    'house_number' => '24',
                    'street' => 'Sample Street',
                    'barangay' => 'Barangay 1',
                    'municipality' => 'Manila',
                    'province' => 'Metro Manila',
                    'license_number' => 'N01-23-456789',
                    'vehicle_type' => $application['vehicle_type'],
                    'license_plate' => 'ABC-1234',
                    'vehicle_registration' => 'REG-2026-001',
                    'submitted_via' => 'web',
                    'rider_type' => 'full_time',
                    'vehicle_ownership' => in_array('owner_valid_id', $application['documents'], true) ? 'borrowed' : 'own',
                    'center_id' => $application['status'] === 'approved' ? $manilaCenter : null,
                    'service_area_id' => $application['status'] === 'approved' ? $manilaArea : null,
                    'approved_by' => $application['status'] === 'approved' ? $adminId : null,
                    'provisioned_at' => $application['status'] === 'approved' ? now()->subDays(5) : null,
                    'status' => $application['status'],
                    'notes' => $application['status'] === 'rejected'
                        ? 'Incomplete vehicle registration documents.'
                        : null,
                    'documents' => null,
                    'reviewed_at' => $application['status'] === 'pending' ? null : now()->subDays(5),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );

            $applicationId = DB::table('rider_applications')->where('email', $application['email'])->value('id');

            foreach ($application['documents'] as $documentType) {
                $path = "rider-documents/{$applicationId}/{$documentType}.pdf";
                $pdf = $this->demoPdf(str_replace('_', ' ', $documentType));
                Storage::disk('local')->put($path, $pdf);

                DB::table('rider_application_documents')->updateOrInsert(
                    ['rider_application_id' => $applicationId, 'document_type' => $documentType],
                    [
                        'original_filename' => str_replace('_', '-', $documentType).'.pdf',
                        'stored_path' => $path,
                        'mime_type' => 'application/pdf',
                        'file_size' => strlen($pdf),
                        'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                        'updated_at' => now(),
                    ]
                );
            }

            $logs = [['previous_status' => null, 'new_status' => 'pending', 'reason' => 'Application submitted.']];

            if ($application['status'] === 'approved') {
                $logs[] = ['previous_status' => 'pending', 'new_status' => 'approved', 'reason' => 'Requirements complete.'];
            } elseif ($application['status'] === 'rejected') {
                $logs[] = ['previous_status' => 'pending', 'new_status' => 'rejected', 'reason' => 'Expired vehicle registration.'];
            }

            foreach ($logs as $log) {
                DB::table('rider_application_logs')->updateOrInsert(
                    ['rider_application_id' => $applicationId, 'new_status' => $log['new_status']],
                    [
                        'previous_status' => $log['previous_status'],
                        'changed_by' => $log['new_status'] === 'pending' ? null : $adminId,
                        'reason' => $log['reason'],
                        'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $riderIds = DB::table('riders')->pluck('id');
        $riderNotifications = [
            ['rider_id' => $riderIds[0] ?? null, 'type' => 'delivery', 'title' => 'New delivery assigned', 'body' => 'TRK-20260819-0003 is waiting for pickup.', 'is_read' => false],
            ['rider_id' => $riderIds[0] ?? null, 'type' => 'system', 'title' => 'Vehicle verified', 'body' => 'Your motorcycle documents were approved.', 'is_read' => true],
            ['rider_id' => $riderIds[1] ?? null, 'type' => 'delivery', 'title' => 'Delivery reminder', 'body' => 'TRK-20260819-0002 is out for delivery.', 'is_read' => false],
            ['rider_id' => $riderIds[2] ?? null, 'type' => 'system', 'title' => 'Vehicle verification failed', 'body' => 'Please upload a valid vehicle registration.', 'is_read' => false],
        ];

        foreach ($riderNotifications as $notification) {
            if (! $notification['rider_id']) {
                continue;
            }

            DB::table('rider_notifications')->updateOrInsert(
                ['rider_id' => $notification['rider_id'], 'title' => $notification['title']],
                [
                    'type' => $notification['type'],
                    'body' => $notification['body'],
                    'data' => json_encode(['seeded' => true]),
                    'is_read' => $notification['is_read'],
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        DB::table('rider_email_verifications')->updateOrInsert(
            ['email' => 'applicant1@test.com'],
            [
                'otp_hash' => Hash::make('123456'),
                'expires_at' => now()->addMinutes(10),
                'attempts' => 0,
                'consumed_at' => null,
                'last_sent_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                'updated_at' => now(),
            ]
        );

        DB::table('rider_email_verifications')->updateOrInsert(
            ['email' => 'applicant2@test.com'],
            [
                'otp_hash' => Hash::make('654321'),
                'expires_at' => now()->subMinutes(5),
                'attempts' => 2,
                'consumed_at' => now()->subDays(5),
                'last_sent_at' => now()->subDays(5),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                'updated_at' => now(),
            ]
        );

        $riderUser = DB::table('users')->where('email', 'rider1@test.com')->first();

        if ($riderUser) {
            $plain = 'demo-rider-'.substr(hash('sha256', (string) config('app.key')), 0, 32);
            DB::table('personal_access_tokens')->updateOrInsert(
                ['token' => hash('sha256', $plain)],
                [
                    'tokenable_type' => \App\Models\User::class,
                    'tokenable_id' => $riderUser->id,
                    'name' => 'demo-rider',
                    'abilities' => json_encode(['*']),
                    'last_used_at' => null,
                    'expires_at' => now()->addDays(30),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );

            $tokenId = DB::table('personal_access_tokens')
                ->where('token', hash('sha256', $plain))
                ->value('id');

            $this->command?->info('Rider demo token seeded successfully.');
        }
    }

    /**
     * Build a valid single-page PDF so demo documents can actually be
     * previewed. The previous placeholder was only a header/comment, which
     * browsers reported as a broken PDF with zero pages.
     */
    private function demoPdf(string $label): string
    {
        $label = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $label);
        $content = "BT /F1 14 Tf 72 720 Td (INVOIZ Demo Document) Tj 0 -22 Td ({$label}) Tj ET";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            "<< /Length ".strlen($content)." >>\nstream\n".$content."\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $number = $index + 1;
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $size = count($objects) + 1;

        $pdf .= "xref\n0 ".$size."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($number = 1; $number < $size; $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        $pdf .= "trailer\n<< /Size ".$size." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }
}
