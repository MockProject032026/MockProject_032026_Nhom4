<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'full_name' => 'Admin User',
            'email' => 'admin@test.com',
            'id_role' => 1,
            'status' => 'active',
        ]);
    }

    /**
     * Test lấy danh sách Journals
     */
    public function test_can_list_journals()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
                         ->getJson('/api/v1/journals');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data',
                     'meta'
                 ]);
    }

    /**
     * Test xem chi tiết Journal
     */
    public function test_can_show_journal_detail()
    {
        $journal = JournalEntry::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'notary_id' => $this->admin->id,
            'status' => 'pending',
            'venue_state' => 'California',
            'is_holiday' => 0,
            'thumbprint_waived' => 0
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->getJson("/api/v1/journals/{$journal->id}");

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    /**
     * Test miễn lấy vân tay
     */
    public function test_can_waive_thumbprint()
    {
        $journal = JournalEntry::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'notary_id' => $this->admin->id,
            'status' => 'pending',
            'thumbprint_waived' => 0,
            'is_holiday' => 0
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->patchJson("/api/v1/journals/{$journal->id}/waive-thumbprint", [
                             'action' => 'WAIVE',
                             'notes' => 'Test reason'
                         ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('journal_entries', [
            'id' => $journal->id,
            'thumbprint_waived' => 1
        ]);
    }

    /**
     * Test yêu cầu Export hàng loạt
     */
    public function test_can_create_export_job()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
                         ->postJson('/api/v1/journals/export', [
                             'format' => 'csv',
                             'from_date' => '2026-01-01'
                         ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'data' => ['job_id', 'status']
                 ]);
    }
}
