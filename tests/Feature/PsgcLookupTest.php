<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PsgcLookupTest extends TestCase
{
    public function test_public_shared_lookup_preserves_codes_and_caches_success(): void
    {
        Http::fake(['psgc.gitlab.io/api/regions' => Http::response([
            ['code' => '010000000', 'name' => 'Ilocos Region'],
        ])]);

        $this->getJson('/api/psgc/regions')->assertOk()->assertJsonPath('0.code', '010000000');
        $this->getJson('/api/psgc/regions')->assertOk()->assertJsonPath('0.name', 'Ilocos Region');
        Http::assertSentCount(1);
    }

    public function test_regions_without_provinces_return_a_successful_empty_list(): void
    {
        Http::fake(['psgc.gitlab.io/api/regions/130000000/provinces' => Http::response([])]);

        $this->getJson('/api/psgc/regions/130000000/provinces')->assertOk()->assertExactJson([]);
    }

    public function test_upstream_failure_is_retryable_and_not_cached_as_an_empty_list(): void
    {
        Http::fakeSequence()->pushStatus(503)->push([
            ['code' => '012800000', 'name' => 'Ilocos Norte'],
        ]);

        $this->getJson('/api/psgc/regions/010000000/provinces')->assertStatus(503);
        $this->getJson('/api/psgc/regions/010000000/provinces')
            ->assertOk()->assertJsonPath('0.code', '012800000');
        Http::assertSentCount(2);
    }

    public function test_connection_failure_returns_a_clear_retryable_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('Test connection failure'));

        $this->getJson('/api/psgc/regions')->assertStatus(503)
            ->assertJsonPath('message', 'Address lookup is unavailable. Please retry.');
    }

    public function test_malformed_upstream_response_is_not_a_successful_lookup(): void
    {
        Http::fake(['*' => Http::response(['error' => 'Unavailable'])]);

        $this->getJson('/api/psgc/regions')->assertStatus(503);
    }

    public function test_unknown_paths_are_rejected_without_contacting_upstream(): void
    {
        Http::fake();

        $this->getJson('/api/psgc/not-a-directory')->assertNotFound();
        Http::assertNothingSent();
    }
}
