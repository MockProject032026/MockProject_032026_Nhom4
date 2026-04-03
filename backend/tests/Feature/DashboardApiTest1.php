<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use Carbon\Carbon;

class DashboardApiTest1 extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $complianceUser;
    protected User $notaryUser;
    protected string $journalId;
    protected string $auditLogId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'id' => Str::uuid()->toString(),
            'email' => 'admin@test.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Admin User',
            'id_role' => 1,
            'status' => 'active',
        ]);

        $this->complianceUser = User::create([
            'id' => Str::uuid()->toString(),
            'email' => 'compliance@test.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Compliance User',
            'id_role' => 2,
            'status' => 'active',
        ]);

        $this->notaryUser = User::create([
            'id' => Str::uuid()->toString(),
            'email' => 'notary@test.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Notary User',
            'id_role' => 3,
            'status' => 'active',
        ]);

        // Create a journal entry
        $this->journalId = Str::uuid()->toString();
        DB::table('journal_entries')->insert([
            'id' => $this->journalId,
            'notary_id' => $this->notaryUser->id,
            'venue_state' => 'California',
            'status' => 'completed',
            'notarial_fee' => 25.00,
            'created_at' => now(),
        ]);

        // Create a signer for the journal entry (needed for missing-signatures reminder)
        $signerId = Str::uuid()->toString();
        DB::table('signers')->insert([
            'id' => $signerId,
            'journal_entry_id' => $this->journalId,
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '555-1234',
            'address' => '123 Main St',
            'id_type' => 'passport',
            'id_number' => 'P12345',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create audit logs
        $this->auditLogId = Str::uuid()->toString();
        DB::table('audit_logs')->insert([
            [
                'id' => $this->auditLogId,
                'timestamp' => now(),
                'initiator_name' => 'Notary',
                'action' => 'JOURNAL_CREATED',
                'resource_id' => $this->journalId,
                'change_details_before' => null,
                'change_details_after' => json_encode(['status' => 'created']),
                'flags' => 'INFO',
            ],
            [
                'id' => Str::uuid()->toString(),
                'timestamp' => now()->subMinute(),
                'initiator_name' => 'Admin',
                'action' => 'JOURNAL_REVIEWED',
                'resource_id' => $this->journalId,
                'change_details_before' => json_encode(['status' => 'created']),
                'change_details_after' => json_encode(['status' => 'reviewed']),
                'flags' => 'WARNING',
            ]
        ]);
    }

    /**
     * API #1: GET /api/v1/dashboard/kpi-summary
     */
    public function test_get_kpi_summary_with_filters(): void
    {
        $response = $this->actingAs($this->adminUser)
                         ->getJson('/api/v1/dashboard/kpi-summary?venue_state=California');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'total_entries',
                         'incomplete',
                         'entries_by_state',
                         'alerts' => ['missing_signatures', 'missing_thumbprints']
                     ]
                 ]);
    }

    /**
     * API #2: GET /api/v1/dashboard/compliance-logs
     */
    public function test_get_recent_compliance_logs(): void
    {
        $response = $this->actingAs($this->complianceUser)
                         ->getJson('/api/v1/dashboard/compliance-logs?limit=5');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'logs' => [
                             '*' => ['id', 'timestamp', 'notary', 'action', 'journal_id', 'flags']
                         ]
                     ]
                 ]);
    }

    /**
     * API #3: GET /api/v1/dashboard/audit-logs/{id}
     */
    public function test_get_audit_log_detail(): void
    {
        $response = $this->actingAs($this->adminUser)
                         ->getJson("/api/v1/dashboard/audit-logs/{$this->auditLogId}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.change_details_after.status', 'created');
    }

    /**
     * API #4: POST /api/v1/notaries/reminders/missing-signatures
     */
    public function test_send_missing_signature_reminders_logs_activity(): void
    {
        $response = $this->actingAs($this->adminUser)
                         ->postJson('/api/v1/notaries/reminders/missing-signatures', [
                             'target' => 'ALL'
                         ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'REMINDER_EMAIL_SENT'
        ]);
    }

    /**
     * API #5: PATCH /api/v1/journals/{id}/waive-thumbprint
     */
    public function test_waive_thumbprint(): void
    {
        $response = $this->actingAs($this->adminUser)
                         ->patchJson("/api/v1/journals/{$this->journalId}/waive-thumbprint", [
                             'action' => 'WAIVE',
                             'notes' => 'Test waive reason'
                         ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true, 'message' => 'Thumbprint waived successfully']);

        $this->assertDatabaseHas('journal_entries', [
            'id' => $this->journalId,
            'thumbprint_waived' => 1
        ]);
    }

    /**
     * API #6 & #8: GET /api/v1/journals (Filter Risk Flag & Venue State)
     */
    public function test_get_journals_with_filters(): void
    {
        // Thêm một bản ghi có Risk Flag để test
        DB::table('journal_entries')->update(['risk_flag' => 'Warning', 'venue_state' => 'California']);

        $response = $this->actingAs($this->adminUser)
                         ->getJson('/api/v1/journals?risk_flag=Warning&venue_state=California');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['entry_id', 'status', 'risk_flag', 'venue_state']
                     ],
                     'meta'
                 ]);
        
        $this->assertEquals('Warning', $response->json('data.0.risk_flag'));
        $this->assertEquals('California', $response->json('data.0.venue_state'));
    }

    /**
     * API #7: GET /api/v1/journals/{id}
     */
    public function test_get_journal_detail_full_info(): void
    {
        $response = $this->actingAs($this->adminUser)
                         ->getJson("/api/v1/journals/{$this->journalId}");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'id',
                         'signers',
                         // Các bảng liên quan theo doc
                         'fee_breakdown'
                     ]
                 ]);
    }

    public function test_notary_cannot_access_admin_dashboard(): void
    {
        $response = $this->actingAs($this->notaryUser)
                         ->getJson('/api/v1/dashboard/kpi-summary');

        $response->assertStatus(403);
    }
}