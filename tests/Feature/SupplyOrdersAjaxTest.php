<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\SupplyOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SupplyOrdersAjaxTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_load_supply_orders_with_ajax()
    {
        // Create a user and authenticate
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create some supply orders
        SupplyOrder::factory()->count(5)->create();

        // Make an AJAX request to the index endpoint
        $response = $this->get(route('SupplyOrders.index', ['is_ajax' => 1]), [
            'X-Requested-With' => 'XMLHttpRequest'
        ]);

        // Assert that the response is successful
        $response->assertStatus(200);

        // Assert that the response contains the expected JSON structure
        $response->assertJsonStructure([
            'success',
            'data'
        ]);

        // Assert that the response indicates success
        $response->assertJson([
            'success' => true
        ]);
    }

    /** @test */
    public function it_can_filter_supply_orders_with_ajax()
    {
        // Create a user and authenticate
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create some supply orders
        $order1 = SupplyOrder::factory()->create(['name' => 'Test Order 1']);
        $order2 = SupplyOrder::factory()->create(['name' => 'Test Order 2']);

        // Make an AJAX request with a filter
        $response = $this->get(route('SupplyOrders.index', [
            'is_ajax' => 1,
            'name' => 'Test Order 1'
        ]), [
            'X-Requested-With' => 'XMLHttpRequest'
        ]);

        // Assert that the response is successful
        $response->assertStatus(200);

        // Assert that the response contains the expected JSON structure
        $response->assertJsonStructure([
            'success',
            'data'
        ]);

        // Assert that the response indicates success
        $response->assertJson([
            'success' => true
        ]);

        // Assert that the response data contains the filtered order
        $response->assertSee($order1->name);
        $response->assertDontSee($order2->name);
    }
}