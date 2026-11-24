<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Statuses;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_bulk_update_client_statuses()
    {
        // Setup Statuses
        $status1 = Statuses::create(['name' => 'Status A', 'color' => '#ff0000']);
        $status2 = Statuses::create(['name' => 'Status B', 'color' => '#00ff00']);

        // Setup Branch
        $branch = Branch::create([
            'name' => 'Main Branch',
            'code' => 'B001',
        ]);

        // Setup Clients
        $client1 = Client::create([
            'trade_name' => 'Client One',
            'code' => 'C001',
            'status_id' => $status1->id,
            'branch_id' => $branch->id,
        ]);

        $client2 = Client::create([
            'trade_name' => 'Client Two',
            'code' => 'C002',
            'status_id' => $status1->id,
            'branch_id' => $branch->id,
        ]);

        // Make POST request with bulk update
        $response = $this->actingAs($this->user)
            ->withoutMiddleware()
            ->postJson(route('clients.bulkUpdateStatus'), [
                'client_ids' => [$client1->id, $client2->id],
                'status_id' => $status2->id,
            ]);

        // Assert response is successful
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'تم تغيير حالة العملاء بنجاح.',
        ]);

        // Assert database is updated
        $this->assertEquals($status2->id, $client1->fresh()->status_id);
        $this->assertEquals($status2->id, $client2->fresh()->status_id);
    }

    /** @test */
    public function it_validates_required_fields_for_bulk_update()
    {
        $response = $this->actingAs($this->user)
            ->withoutMiddleware()
            ->postJson(route('clients.bulkUpdateStatus'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['client_ids', 'status_id']);
    }
}
