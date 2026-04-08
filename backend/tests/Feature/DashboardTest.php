<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Tạo admin user để bypass middleware auth và checkRole
        $this->admin = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'full_name' => 'Admin User',
            'email' => 'admin@test.com',
            'password_hash' => \Illuminate\Support\Facades\Hash::make('password'),
            'id_role' => 1,
            'status' => 'active',
        ]);
    }

    /**
     * Test API KPI Summary
     */
    public function test_can_get_kpi_summary()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
                         ->getJson('/api/v1/dashboard/kpi-summary');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'kpi', 'charts', 'filters_data', 'alerts'
                     ]
                 ]);
    }

    /**
     * Test API Compliance Logs phân trang
     */
    public function test_can_get_compliance_logs()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
                         ->getJson('/api/v1/dashboard/compliance-logs?limit=5');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => ['logs'],
                     'meta' => ['total', 'page', 'limit']
                 ]);
    }

    /**
     * Test gửi thông báo nhắc nhở
     */
    public function test_can_send_missing_signature_reminders()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
                         ->postJson('/api/v1/notaries/reminders/missing-signatures', [
                             'target' => 'ALL'
                         ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }
}
