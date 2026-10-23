<?php

namespace Database\Seeders;

use App\Models\LogisticsCenterApplicationDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * "Open a Logistics Center" applications in pending / approved / rejected
 * states, with supporting documents and wizard error metadata.
 */
class CenterApplicationDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('CenterApplicationDemoSeeder skipped outside local/testing.');

            return;
        }

        $adminId = DB::table('users')->where('role', 'logistics_admin')->value('id');

        $applications = [
            [
                'business_name' => 'Sunrise Mini Mart',
                'email' => 'sunrise@test.com',
                'owner' => ['Juan', 'Santos', 'Dela Cruz'],
                'sec_number' => 'AB123456789',
                'tin' => '123-456-789-000',
                'region' => 'National Capital Region (NCR)',
                'postal_code' => '1000',
                'status' => 'pending',
                'submitted_via' => 'web',
                'documents' => ['business_registration', 'barangay_clearance'],
                'wizard_error_steps' => null,
            ],
            [
                'business_name' => 'Metro Hub Logistics',
                'email' => 'metrohub@test.com',
                'owner' => ['Maria', 'Lopez', 'Reyes'],
                'sec_number' => 'CD987654321',
                'tin' => '987-654-321-000',
                'region' => 'National Capital Region (NCR)',
                'postal_code' => '1200',
                'status' => 'approved',
                'submitted_via' => 'web',
                'documents' => ['business_registration', 'barangay_clearance', 'mayors_permit', 'lease_contract'],
                'wizard_error_steps' => null,
            ],
            [
                'business_name' => 'QuickDrop Cebu',
                'email' => 'quickdrop@test.com',
                'owner' => ['Ana', 'Cruz', 'Lim'],
                'sec_number' => 'EF456789123',
                'tin' => '456-789-123-000',
                'region' => 'Central Visayas (Region VII)',
                'postal_code' => '6000',
                'status' => 'rejected',
                'submitted_via' => 'web',
                'documents' => ['business_registration'],
                'wizard_error_steps' => null,
            ],
            [
                'business_name' => 'Northern Sorting Co.',
                'email' => 'northern@test.com',
                'owner' => ['Pedro', 'Garcia', 'Bautista'],
                'sec_number' => 'GH321654987',
                'tin' => '321-654-987-000',
                'region' => 'Central Luzon (Region III)',
                'postal_code' => '2000',
                'status' => 'pending',
                'submitted_via' => 'mobile',
                'documents' => ['business_registration', 'other'],
                'wizard_error_steps' => json_encode([1 => ['owner_first_name' => 'Owner name is required.'], 3 => ['documents' => 'Upload the business registration.']]),
            ],
        ];

        foreach ($applications as $application) {
            [$firstName, $middleName, $lastName] = $application['owner'];
            $ownerName = trim($firstName.' '.$middleName.' '.$lastName);
            $isApproved = $application['status'] === 'approved';
            $isRejected = $application['status'] === 'rejected';

            $municipality = $application['region'] === 'Central Visayas (Region VII)'
                ? 'Cebu City'
                : ($application['region'] === 'Central Luzon (Region III)' ? 'San Fernando' : 'Quezon City');
            $province = $application['region'] === 'Central Visayas (Region VII)'
                ? 'Cebu'
                : ($application['region'] === 'Central Luzon (Region III)' ? 'Pampanga' : 'Metro Manila');
            $barangay = $application['region'] === 'Central Visayas (Region VII)'
                ? 'Lahug'
                : ($application['region'] === 'Central Luzon (Region III)' ? 'Del Pilar' : 'Batasan Hills');

            DB::table('logistics_center_applications')->updateOrInsert(
                ['email' => $application['email']],
                [
                    'business_name' => $application['business_name'],
                    'owner_name' => $ownerName,
                    'owner_first_name' => $firstName,
                    'owner_middle_name' => $middleName,
                    'owner_last_name' => $lastName,
                    'sec_number' => $application['sec_number'],
                    'tin' => $application['tin'],
                    'postal_code' => $application['postal_code'],
                    'region' => $application['region'],
                    'phone' => '0917333000'.array_search($application, $applications, true),
                    'house_number' => '12',
                    'street' => 'Sample Avenue',
                    'barangay' => $barangay,
                    'municipality' => $municipality,
                    'province' => $province,
                    'address' => "12 Sample Avenue, {$barangay}, {$municipality}, {$province}",
                    'status' => $application['status'],
                    'submitted_via' => $application['submitted_via'],
                    'wizard_error_steps' => $application['wizard_error_steps'],
                    'notes' => $isRejected
                        ? 'Business permit could not be verified.'
                        : ($isApproved ? 'Verified and ready for provisioning.' : null),
                    'reviewed_at' => $isApproved || $isRejected ? now()->subDays(3) : null,
                    'approved_by' => $isApproved ? $adminId : null,
                    'provisioned_at' => $isApproved ? now()->subDays(2) : null,
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );

            $applicationId = DB::table('logistics_center_applications')
                ->where('email', $application['email'])
                ->value('id');

            foreach ($application['documents'] as $index => $documentType) {
                $label = LogisticsCenterApplicationDocument::TYPES[$documentType] ?? $documentType;
                $path = "center-documents/{$applicationId}/{$documentType}.pdf";
                Storage::disk('local')->put($path, "%PDF-1.4\n% Demo center document: {$label}\n");

                DB::table('logistics_center_application_documents')->updateOrInsert(
                    ['logistics_center_application_id' => $applicationId, 'document_type' => $documentType],
                    [
                        'original_filename' => str_replace(' ', '-', strtolower($label)).'.pdf',
                        'stored_path' => $path,
                        'mime_type' => 'application/pdf',
                        'file_size' => 80 + $index,
                        'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
