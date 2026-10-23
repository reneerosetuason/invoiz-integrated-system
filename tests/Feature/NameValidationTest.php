<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rules\BusinessName;
use App\Rules\PersonName;
use App\Support\NameNormalizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class NameValidationTest extends TestCase
{
    use DatabaseTransactions;

    private function personPasses(string $value): bool
    {
        return Validator::make(
            ['name' => $value],
            ['name' => ['required', 'string', new PersonName()]]
        )->passes();
    }

    private function businessPasses(string $value): bool
    {
        return Validator::make(
            ['business_name' => $value],
            ['business_name' => ['required', 'string', new BusinessName()]]
        )->passes();
    }

    private function verifyEmail(string $email): void
    {
        DB::table('rider_email_verifications')->insert([
            'email' => $email,
            'otp_hash' => bcrypt('123456'),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'consumed_at' => now(),
            'last_sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function centerPayload(string $email, array $overrides = []): array
    {
        $sec = 'AB'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
        $tin = implode('-', str_split(str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT), 3));

        return array_merge([
            'terms' => '1',
            'business_name' => 'Name Validation Center '.Str::random(6),
            'sec_number' => $sec,
            'tin' => $tin,
            'owner_first_name' => 'Maria',
            'owner_middle_name' => 'Clara',
            'owner_last_name' => 'Santos',
            'email' => $email,
            'phone' => '09'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'region' => '130000000',
            'municipality' => 'Quezon City',
            'barangay' => 'Barangay One',
            'house_number' => '42',
            'street' => 'Rizal Avenue',
            'postal_code' => '1100',
            'documents' => [
                'valid_id' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
                'business_registration' => UploadedFile::fake()->create('registration.pdf', 100, 'application/pdf'),
            ],
        ], $overrides);
    }

    public function test_normalizer_trims_and_collapses_repeated_spaces(): void
    {
        $this->assertSame('María José Rizal', NameNormalizer::clean('  María   José  Rizal  '));
        $this->assertSame('J&T Express', NameNormalizer::clean(' J&T   Express '));
        $this->assertNull(NameNormalizer::clean(null));
    }

    public function test_person_rule_accepts_unicode_names_and_common_punctuation(): void
    {
        foreach ([
            'María José',
            'Dela Cruz-Santos',
            "O'Connor",
            'O’Connor',
            'Juan Jr.',
            'Ñoño',
            "D'Angelo",
        ] as $name) {
            $this->assertTrue($this->personPasses($name), "Expected person name to pass: {$name}");
        }
    }

    public function test_person_rule_rejects_digits_markup_and_control_characters(): void
    {
        foreach ([
            'Juan123',
            '<script>',
            'José@example.com',
            "Juan\nDela Cruz",
            'Juan_Dela Cruz',
        ] as $name) {
            $this->assertFalse($this->personPasses($name), "Expected person name to fail: {$name}");
        }
    }

    public function test_business_rule_accepts_unicode_and_common_business_punctuation(): void
    {
        foreach ([
            'J&T Express',
            '7-Eleven',
            "McDonald's",
            'Acme, Inc.',
            'A.B.C. Trading (Branch #2)',
            'Café 24/7',
        ] as $name) {
            $this->assertTrue($this->businessPasses($name), "Expected business name to pass: {$name}");
        }
    }

    public function test_business_rule_rejects_markup_leading_punctuation_and_control_characters(): void
    {
        foreach ([
            '<script>',
            '-Leading Hyphen',
            'Acme; DROP TABLE users',
            "Acme\nInc.",
        ] as $name) {
            $this->assertFalse($this->businessPasses($name), "Expected business name to fail: {$name}");
        }
    }

    public function test_registration_normalizes_and_stores_unicode_names(): void
    {
        Mail::fake();
        $email = 'name.'.Str::random(8).'@test.local';

        $this->post(route('register.post'), [
            'first_name' => '  María  José ',
            'last_name' => 'Dela  Cruz-Santos',
            'sex' => 'female',
            'birthday' => '1995-05-31',
            'email' => $email,
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
            'terms' => '1',
        ])->assertSessionHasNoErrors();

        $user = User::where('email', $email)->firstOrFail();
        $this->assertSame('María José', $user->first_name);
        $this->assertSame('Dela Cruz-Santos', $user->last_name);
        $this->assertSame('María José Dela Cruz-Santos', $user->name);
    }

    public function test_registration_rejects_invalid_person_names(): void
    {
        Mail::fake();
        $email = 'invalid.name.'.Str::random(8).'@test.local';

        $this->post(route('register.post'), [
            'first_name' => 'Juan123',
            'last_name' => 'Dela Cruz',
            'sex' => 'male',
            'birthday' => '1995-05-31',
            'email' => $email,
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
            'terms' => '1',
        ])->assertSessionHasErrors('first_name');

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_buyer_profile_update_normalizes_and_stores_unicode_names(): void
    {
        $user = User::create([
            'name' => 'Old Name',
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'profile.'.Str::random(8).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'buyer',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('buyer.profile.update'), [
                'first_name' => '  María  José ',
                'last_name' => 'Dela  Cruz-Santos',
                'phone' => '',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('María José', $user->first_name);
        $this->assertSame('Dela Cruz-Santos', $user->last_name);
        $this->assertSame('María José Dela Cruz-Santos', $user->name);
    }

    public function test_seller_application_rejects_invalid_business_names(): void
    {
        $this->post(route('sell.post'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'seller.'.Str::random(8).'@test.local',
            'phone' => '09171234567',
            'business_name' => '<script>',
            'line_of_business' => 'Retail',
            'seller_type' => 'Sole Proprietorship',
            'tin' => '123456789012',
            'region' => 'Region',
            'province' => 'Province',
            'city' => 'City',
            'barangay' => 'Barangay',
            'address_line' => '123 Street',
            'postal_code' => '1100',
            'terms' => '1',
        ])->assertSessionHasErrors('business_name');
    }

    public function test_center_application_normalizes_business_and_owner_names(): void
    {
        $email = 'center.names.'.Str::random(8).'@test.local';
        $this->verifyEmail($email);

        $this->post(route('center-application.store'), $this->centerPayload($email, [
            'business_name' => '  J&T   Express  ',
            'owner_first_name' => '  María ',
            'owner_middle_name' => ' José ',
            'owner_last_name' => " O'Connor ",
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('logistics_center_applications', [
            'email' => $email,
            'business_name' => 'J&T Express',
            'owner_first_name' => 'María',
            'owner_middle_name' => 'José',
            'owner_last_name' => "O'Connor",
            'owner_name' => "María José O'Connor",
        ]);
    }

    public function test_center_application_rejects_invalid_business_names(): void
    {
        $email = 'center.invalid.'.Str::random(8).'@test.local';
        $this->verifyEmail($email);

        $payload = $this->centerPayload($email, ['business_name' => '<script> Center']);

        $this->post(route('center-application.store'), $payload)
            ->assertSessionHasErrors('business_name');

        $this->assertDatabaseMissing('logistics_center_applications', [
            'email' => $email,
        ]);
    }

    public function test_rider_application_rejects_invalid_person_names(): void
    {
        $this->postJson('/api/logistics/rider/apply', [
            'name' => 'Juan123',
            'middle_initial' => 'S.',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}
