<?php

namespace Tests\Unit;

use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\Seller;
use App\Services\PickupHubRecommendations;
use Tests\TestCase;

class PickupHubRecommendationsTest extends TestCase
{
    private function hub(int $id, string $city, string $province = 'Laguna', array $extra = []): LogisticsCenter
    {
        return (new LogisticsCenter)->forceFill(array_merge([
            'id' => $id, 'name' => $city.' Hub '.$id, 'city' => $city,
            'province' => $province, 'is_active' => true,
        ], $extra));
    }

    private function store(string $city, string $province = 'Laguna'): Seller
    {
        return (new Seller)->forceFill(['city' => $city, 'province' => $province, 'address_line' => 'Pickup street']);
    }

    public function test_kalayaan_pickups_recommend_multiple_nearby_hubs_and_keep_far_hubs_available(): void
    {
        $result = (new PickupHubRecommendations)->forPickup(collect([
            $this->hub(1, 'Calamba'), $this->hub(2, 'Lumban'), $this->hub(3, 'Kalayaan'),
        ]), null, $this->store('Kalayaan'));
        $this->assertTrue($result['recommendations'][2]['recommended']);
        $this->assertTrue($result['recommendations'][3]['recommended']);
        $this->assertFalse($result['recommendations'][1]['recommended']);
        $this->assertTrue($result['recommendations'][2]['approximate']);
        $this->assertSame([3, 2, 1], $result['centers']->pluck('id')->all());
    }

    public function test_province_disambiguates_municipalities_with_the_same_name(): void
    {
        $result = (new PickupHubRecommendations)->forPickup(collect([
            $this->hub(1, 'Kalayaan', 'Palawan'), $this->hub(2, 'Lumban'),
        ]), null, $this->store('Kalayaan'));
        $this->assertFalse($result['recommendations'][1]['recommended']);
        $this->assertGreaterThan(100, $result['recommendations'][1]['distance_km']);
        $this->assertTrue($result['recommendations'][2]['recommended']);
    }

    public function test_saved_pickup_address_takes_precedence_over_a_store_that_moved(): void
    {
        $delivery = (new Delivery)->forceFill(['sender_address' => '10 Main Road, Brgy. San Juan, Kalayaan, Laguna, Calabarzon, 4015']);
        $result = (new PickupHubRecommendations)->forPickup(collect([$this->hub(1, 'Lumban')]), $delivery, $this->store('Calamba'));
        $this->assertTrue($result['recommendations'][1]['recommended']);
    }

    public function test_unknown_saved_address_does_not_fall_back_to_an_unrelated_current_store_address(): void
    {
        $delivery = (new Delivery)->forceFill(['sender_address' => 'Unresolved old pickup location']);
        $result = (new PickupHubRecommendations)->forPickup(collect([$this->hub(1, 'Lumban')]), $delivery, $this->store('Kalayaan'));
        $this->assertFalse($result['recommendations'][1]['recommended']);
        $this->assertNull($result['recommendations'][1]['distance_km']);
    }

    public function test_exact_coordinates_override_locality_estimates_and_enforce_the_radius(): void
    {
        $delivery = (new Delivery)->forceFill(['sender_lat' => 14, 'sender_lng' => 121]);
        $result = (new PickupHubRecommendations)->forPickup(collect([
            $this->hub(1, 'Unknown', 'Unknown', ['latitude' => 14.224, 'longitude' => 121]),
            $this->hub(2, 'Unknown', 'Unknown', ['latitude' => 14.226, 'longitude' => 121]),
        ]), $delivery, null);
        $this->assertTrue($result['recommendations'][1]['recommended']);
        $this->assertFalse($result['recommendations'][2]['recommended']);
        $this->assertFalse($result['recommendations'][1]['approximate']);
    }

    public function test_inactive_centers_are_never_offered(): void
    {
        $result = (new PickupHubRecommendations)->forPickup(collect([
            $this->hub(1, 'Kalayaan', 'Laguna', ['is_active' => false]), $this->hub(2, 'Lumban'),
        ]), null, $this->store('Kalayaan'));
        $this->assertSame([2], $result['centers']->pluck('id')->all());
        $this->assertArrayNotHasKey(1, $result['recommendations']);
    }

    public function test_unknown_locations_do_not_get_false_recommendations(): void
    {
        $result = (new PickupHubRecommendations)->forPickup(collect([$this->hub(1, 'Lumban')]), null, null);
        $this->assertFalse($result['recommendations'][1]['recommended']);
        $this->assertNull($result['recommendations'][1]['distance_km']);
        $this->assertCount(1, $result['centers']);
    }

    public function test_city_and_province_labels_are_normalized(): void
    {
        $result = (new PickupHubRecommendations)->forPickup(collect([
            $this->hub(1, 'Municipality of Lumban', 'Province of Laguna'),
        ]), null, $this->store('  KALAYAAN  ', 'LAGUNA'));
        $this->assertTrue($result['recommendations'][1]['recommended']);
    }

    public function test_dropdown_labels_nearby_hubs_without_overriding_the_selected_distant_hub(): void
    {
        $result = (new PickupHubRecommendations)->forPickup(collect([
            $this->hub(1, 'Calamba'), $this->hub(2, 'Lumban'),
        ]), null, $this->store('Kalayaan'));
        $html = view('seller.orders.partials.pickup-center-options', [
            'logisticsCenters' => $result['centers'],
            'pickupRecommendations' => $result['recommendations'],
            'selectedCenterId' => 1,
        ])->render();
        $this->assertStringContainsString('Recommended — Lumban Hub 2', $html);
        $this->assertStringContainsString('approx.', $html);
        $this->assertMatchesRegularExpression('/value="1"\s+selected/', $html);
        $this->assertStringNotContainsString('Recommended — Calamba', $html);
    }
}
