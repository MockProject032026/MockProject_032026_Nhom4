<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;

class DashboardApiTest extends TestCase
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

        // Create admin user (id_role = 1)
        $this->adminUser = User::create([
            'id' => Str::uuid()->toString(),
            'email' => 'admin@test.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Admin User',
            'id_role' => 1,
            'status' => 'active',
        ]);

        // Create compliance user (id_role = 2)
        $this->complianceUser = User::create([
            'id' => Str::uuid()->toString(),
            'email' => 'compliance@test.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Compliance User',
            'id_role' => 2,
            'status' => 'active',
        ]);

        // Create notary user (id_role = 3)
        $this->notaryUser = User::create([
            'id' => Str::uuid()->toString(),
            'email' => 'notary@test.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Notary User',
            'id_role' => 3,
            'status' => 'active',
            'commission_number' => 'CN-12345',
        ]);

        // Create journal entries
        $this->journalId = Str::uuid()->toString();
        DB::table('journal_entries')->insert([
            [
                'id' => $this->journalId,
                'notary_id' => $this->notaryUser->id,
                'venue_state' => 'CA',
                'venue_county' => 'Los Angeles',
                'execution_date' => '2026-03-15 10:00:00',
                'status' => 'completed',
                'notarial_fee' => 25.00,
                'is_holiday' => false,
                'thumbprint_waived' => false,
                'risk_flag' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'notary_id' => $this->notaryUser->id,
                'venue_state' => 'CA',
                'venue_county' => 'San Francisco',
                'execution_date' => '2026-03-16 11:00:00',
                'status' => 'pending',
                'notarial_fee' => 30.00,
                'is_holiday' => false,
                'thumbprint_waived' => false,
                'risk_flag' => 'HIGH',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'notary_id' => $this->notaryUser->id,
                'venue_state' => 'NY',
                'venue_county' => 'Manhattan',
                'execution_date' => '2026-03-17 09:00:00',
                'status' => 'completed',
                'notarial_fee' => 35.00,
                'is_holiday' => false,
                'thumbprint_waived' => false,
                'risk_flag' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Create signers for the first journal entry
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

        // Create audit logs for compliance tests
        $this->auditLogId = Str::uuid()->toString();
        DB::table('audit_logs')->insert([
            [
                'id' => $this->auditLogId,
                'timestamp' => '2026-03-15 10:30:00',
                'initiator_name' => 'Notary User',
                'action' => 'JOURNAL_CREATED',
                'resource_id' => $this->journalId,
                'change_details_before' => null,
                'change_details_after' => json_encode(['status' => 'created']),
                'flags' => 'INFO',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'timestamp' => '2026-03-15 11:00:00',
                'initiator_name' => 'Admin User',
                'action' => 'JOURNAL_REVIEWED',
                'resource_id' => $this->journalId,
                'change_details_before' => json_encode(['status' => 'created']),
                'change_details_after' => json_encode(['status' => 'reviewed']),
                'flags' => 'WARNING',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Create fee breakdown
        DB::table('fee_breakdowns')->insert([
            'id' => Str::uuid()->toString(),
            'journal_entry_id' => $this->journalId,
            'base_notarial_fee' => 15.00,
            'service_fee' => 5.00,
            'travel_fee' => 5.00,
            'convenience_fee' => 0,
            'rush_fee' => 0,
            'holiday_fee' => 0,
            'total_amount' => 25.00,
            'notary_share' => 20.00,
            'company_share' => 5.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // KPI Summary Tests
    // ────────────────────────────────────────────────────────────

    /**
     * TC_Dashboard_FUN_04: Dashboard KPI cards calculate current journal data correctly
     */
    public function test_get_kpi_summary(): void
    {
        $response = $this->actingAs($this->adminUser)
                        ->getJson('/api/v1/dashboard/kpi-summary');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'kpi' => [
                            'total_entries',
                            'incomplete',
                        ],
                        'charts' => [
                            'by_state',
                        ],
                        'alerts',
                    ]
                ]);
    }

    /**
     * TC_Dashboard_FUN_05: State filter refreshes KPI cards and recent logs
     */
    public function test_kpi_summary_with_state_filter(): void
    {
        $response = $this->actingAs($this->adminUser)
                        ->getJson('/api/v1/dashboard/kpi-summary?venue_state=CA');

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Should only contain CA entries (2 entries)
        $this->assertEquals(2, $response->json('data.kpi.total_entries'));
    }

    /**
     * TC_Dashboard_FUN_06: Notary filter refreshes KPI cards and recent logs
     */
    public function test_kpi_summary_with_notary_filter(): void
    {
        $response = $this->actingAs($this->adminUser)
                        ->getJson('/api/v1/dashboard/kpi-summary?notary_id=' . $this->notaryUser->id);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /**
     * TC_Dashboard_FUN_07: Date range filter refreshes KPI cards and recent logs
     */
    public function test_kpi_summary_with_date_filter(): void
    {
        // One entry is on 2026-03-15, another on 16th, another on 17th
        $response = $this->actingAs($this->adminUser)
                        ->getJson('/api/v1/dashboard/kpi-summary?start_date=2026-03-15&end_date=2026-03-15');

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertEquals(1, $response->json('data.kpi.total_entries'));
    }

    // ────────────────────────────────────────────────────────────
    // Compliance Logs Tests
    // ────────────────────────────────────────────────────────────

    /**
     * TC_Dashboard_FUN_09: Recent Compliance Logs show latest events in descending order
     */
    public function test_get_compliance_logs(): void
    {
        $response = $this->actingAs($this->adminUser)
                        ->getJson('/api/v1/dashboard/compliance-logs');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'logs' => [
                            '*' => [
                                'id',
                                'timestamp',
                                'notary',
                                'action',
                                'journal_id',
                                'flags'
                            ]
                        ]
                    ]
                ]);

        // Check descending order
        $logs = $response->json('data.logs');
        $this->assertGreaterThan(1, count($logs));
        $this->assertTrue(
            strtotime($logs[0]['timestamp']) >= strtotime($logs[1]['timestamp']),
            'Logs should be in descending order by timestamp'
        );
    }

    public function test_compliance_logs_with_limit(): void
    {
        $response = $this->actingAs($this->adminUser)
                        ->getJson('/api/v1/dashboard/compliance-logs?limit=1');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.logs'));
    }

    /**
     * TC_Dashboard_FUN_10: View Journal from Recent Compliance Logs opens related journal context
     */
    public function test_get_audit_log_detail(): void
    {
        $response = $this->actingAs($this->adminUser)
                        ->getJson('/api/v1/dashboard/audit-logs/' . $this->auditLogId);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'timestamp',
                        'initiator_name',
                        'action',
                        'resource_id',
                        'flags',
                        'change_details_before',
                        'change_details_after',
                    ]
                ]);
        
        // Verify resource context mapping
        $this->assertEquals($this->journalId, $response->json('data.resource_id'));
    }

    public function test_send_missing_signature_reminders(): void
    {
        // Missing signatures = no biometric_data; our journal entry has signers without biometric data
        $response = $this->actingAs($this->adminUser)
                        ->postJson('/api/v1/notaries/reminders/missing-signatures', [
                            'target' => 'ALL'
                        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'success',
                    'data' => ['emails_sent_count']
                ]);
    }

    // ────────────────────────────────────────────────────────────
    // Journals Tests
    // ────────────────────────────────────────────────────────────

    public function test_get_journals_list(): void
    {
        $response = $this->getJson('/api/v1/journals');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                    'meta' => [
                        'total',
                        'page',
                        'limit',
                        'total_pages',
                        'has_prev',
                        'has_next',
                    ]
                ]);
    }

    public function test_journals_with_status_filter(): void
    {
        $response = $this->getJson('/api/v1/journals?status=completed');

        $response->assertStatus(200);
        // Should have 2 completed entries
        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_journals_with_pagination(): void
    {
        $response = $this->getJson('/api/v1/journals?page=1&limit=2');

        $response->assertStatus(200)
                ->assertJsonPath('meta.page', 1)
                ->assertJsonPath('meta.limit', 2);
    }

    public function test_get_single_journal(): void
    {
        $response = $this->getJson('/api/v1/journals/' . $this->journalId);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'notary_id',
                        'status',
                        'execution_date',
                        'venue_state',
                        'signers',
                        'fee_breakdown',
                    ]
                ]);
    }

    public function test_waive_thumbprint(): void
    {
        $response = $this->actingAs($this->adminUser)
                        ->patchJson('/api/v1/journals/' . $this->journalId . '/waive-thumbprint', [
                            'action' => 'WAIVE',
                            'notes' => 'Testing waive'
                        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Verify it was updated
        $this->assertEquals(1, DB::table('journal_entries')->where('id', $this->journalId)->value('thumbprint_waived'));
    }

    public function test_journals_with_search(): void
    {
        // Search by notary name or venue_state
        $response = $this->getJson('/api/v1/journals?search=CA');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, $response->json('meta.total'));
    }

    public function test_journals_with_risk_flag_filter(): void
    {
        $response = $this->getJson('/api/v1/journals?risk_flag=HIGH');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_journals_as_notary_only_shows_own(): void
    {
        // Create another notary and a journal for them
        $otherNotary = User::create([
            'id' => Str::uuid()->toString(),
            'email' => 'other@test.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Other Notary',
            'id_role' => 3,
            'status' => 'active',
        ]);

        DB::table('journal_entries')->insert([
            'id'             => Str::uuid()->toString(),
            'notary_id'      => $otherNotary->id,
            'venue_state'    => 'TX',
            'venue_county'   => 'Austin',
            'execution_date' => '2026-03-20 10:00:00',
            'status'         => 'completed',
            'notarial_fee'   => 50.00,
            'thumbprint_waived' => false,
            'is_holiday'     => false,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Request as the first notary
        $response = $this->actingAs($this->notaryUser)
                        ->getJson('/api/v1/journals');

        $response->assertStatus(200);

        // Total should be 3 (from setUp), not including the one from otherNotary
        $this->assertEquals(3, $response->json('meta.total'));

        // Ensure none of the entries belong to the other notary
        $data = $response->json('data');
        foreach ($data as $entry) {
            $this->assertNotEquals('TX', $entry['venue_state']);
        }
    }

    // ────────────────────────────────────────────────────────────
    // Authorization Tests
    // ────────────────────────────────────────────────────────────

    /**
     * TC_Dashboard_FUN_03: Regular Notary cannot access Journal Dashboard by direct URL
     */
    public function test_notary_cannot_access_dashboard(): void
    {
        $response = $this->actingAs($this->notaryUser)
                        ->getJson('/api/v1/dashboard/kpi-summary');

        // Middleware checkRole:1,2 blocks notary (id_role=3)
        $response->assertStatus(403);
    }

    /**
     * TC_Dashboard_FUN_01: Admin can access Journal Dashboard
     */
    public function test_admin_can_access_dashboard(): void
    {
        $response = $this->actingAs($this->adminUser)
                        ->getJson('/api/v1/dashboard/kpi-summary');

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /**
     * TC_Dashboard_FUN_02: Compliance Officer can access Journal Dashboard
     */
    public function test_compliance_officer_can_access_dashboard(): void
    {
        $response = $this->actingAs($this->complianceUser)
                        ->getJson('/api/v1/dashboard/kpi-summary');

        $response->assertStatus(200);
    }

    /**
     * TC_Dashboard_FUN_08: Clear All button resets dashboard to default view
     */
    public function test_dashboard_reset_view(): void
    {
        // Default call without filters returns all data
        $response = $this->actingAs($this->adminUser)
                        ->getJson('/api/v1/dashboard/kpi-summary');

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('data.kpi.total_entries')); 
    }
}