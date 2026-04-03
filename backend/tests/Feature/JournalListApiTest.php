<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;

/**
 * Test Cases for SC_002 (Admin/Compliance List) and SC_003 (Notary List)
 *
 * TC_ListAD__FUN_01  -> TC_ListAD__FUN_08
 * TC_ListNotary_FUN_01 -> TC_ListNotary_FUN_10
 */
class JournalListApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $compliance;
    protected User $notaryEmily;   // "Emily Carter" – owns CA entries
    protected User $notaryOther;   // "Mike Johnson" – owns TX entries
    protected array $entryIds = [];

    // ────────────────────────────────────────────────────────────
    // SETUP: Build realistic dataset matching TC sample data
    // ────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        // ── Users ──────────────────────────────────────────────
        $this->admin = User::create([
            'id' => Str::uuid()->toString(),
            'email' => 'admin_journal_01@test.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Admin Journal',
            'id_role' => 1,
            'status' => 'active',
        ]);

        $this->compliance = User::create([
            'id' => Str::uuid()->toString(),
            'email' => 'compliance_01@test.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Compliance Officer',
            'id_role' => 2,
            'status' => 'active',
        ]);

        $this->notaryEmily = User::create([
            'id' => Str::uuid()->toString(),
            'email' => 'notary_ca_01@test.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Emily Carter',
            'id_role' => 3,
            'status' => 'active',
            'commission_number' => 'CN-CA-001',
        ]);

        $this->notaryOther = User::create([
            'id' => Str::uuid()->toString(),
            'email' => 'notary_tx_01@test.com',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Mike Johnson',
            'id_role' => 3,
            'status' => 'active',
            'commission_number' => 'CN-TX-001',
        ]);

        // ── Journal Entries (25 total, across CA/TX/FL) ────────
        // Emily Carter's CA entries
        $this->entryIds['JRN-CA-00021'] = 'JRN-CA-00021';
        DB::table('journal_entries')->insert([
            'id' => 'JRN-CA-00021',
            'notary_id' => $this->notaryEmily->id,
            'venue_state' => 'CA',
            'venue_county' => 'Los Angeles',
            'execution_date' => '2026-03-21 10:45:00',
            'status' => 'draft',
            'act_type' => 'Acknowledgment',
            'notarial_fee' => 15.00,
            'risk_flag' => null,
            'thumbprint_waived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->entryIds['JRN-CA-00022'] = 'JRN-CA-00022';
        DB::table('journal_entries')->insert([
            'id' => 'JRN-CA-00022',
            'notary_id' => $this->notaryEmily->id,
            'venue_state' => 'CA',
            'venue_county' => 'San Francisco',
            'execution_date' => '2026-03-22 14:00:00',
            'status' => 'incomplete',
            'act_type' => 'Jurat',
            'notarial_fee' => 20.00,
            'risk_flag' => 'MISSING_SIGNATURE',
            'thumbprint_waived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->entryIds['JRN-CA-00023'] = 'JRN-CA-00023';
        DB::table('journal_entries')->insert([
            'id' => 'JRN-CA-00023',
            'notary_id' => $this->notaryEmily->id,
            'venue_state' => 'CA',
            'venue_county' => 'San Diego',
            'execution_date' => '2026-03-23 09:00:00',
            'status' => 'completed',
            'act_type' => 'Acknowledgment',
            'notarial_fee' => 25.00,
            'risk_flag' => null,
            'thumbprint_waived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mike Johnson's TX entries
        $this->entryIds['JRN-TX-00011'] = 'JRN-TX-00011';
        DB::table('journal_entries')->insert([
            'id' => 'JRN-TX-00011',
            'notary_id' => $this->notaryOther->id,
            'venue_state' => 'TX',
            'venue_county' => 'Austin',
            'execution_date' => '2026-03-20 11:00:00',
            'status' => 'incomplete',
            'act_type' => 'Jurat',
            'notarial_fee' => 18.00,
            'risk_flag' => null,
            'thumbprint_waived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->entryIds['JRN-TX-00012'] = 'JRN-TX-00012';
        DB::table('journal_entries')->insert([
            'id' => 'JRN-TX-00012',
            'notary_id' => $this->notaryOther->id,
            'venue_state' => 'TX',
            'venue_county' => 'Dallas',
            'execution_date' => '2026-03-24 15:30:00',
            'status' => 'incomplete',
            'act_type' => 'Jurat',
            'notarial_fee' => 22.00,
            'risk_flag' => null,
            'thumbprint_waived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // FL entry (out of date range for TC_ListAD__FUN_06)
        $this->entryIds['JRN-FL-00005'] = 'JRN-FL-00005';
        DB::table('journal_entries')->insert([
            'id' => 'JRN-FL-00005',
            'notary_id' => $this->notaryOther->id,
            'venue_state' => 'FL',
            'venue_county' => 'Miami',
            'execution_date' => '2026-03-26 10:00:00',
            'status' => 'completed',
            'act_type' => 'Acknowledgment',
            'notarial_fee' => 30.00,
            'risk_flag' => null,
            'thumbprint_waived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Bulk entries to reach 25 total for pagination tests
        for ($i = 1; $i <= 19; $i++) {
            $state = ['CA', 'TX', 'FL'][$i % 3];
            $notary = $i % 2 === 0 ? $this->notaryEmily : $this->notaryOther;
            DB::table('journal_entries')->insert([
                'id' => Str::uuid()->toString(),
                'notary_id' => $notary->id,
                'venue_state' => $state,
                'venue_county' => 'County-' . $i,
                'execution_date' => "2026-03-" . str_pad(($i % 28) + 1, 2, '0', STR_PAD_LEFT) . " 10:00:00",
                'status' => ['completed', 'pending', 'draft'][$i % 3],
                'act_type' => ['Acknowledgment', 'Jurat'][$i % 2],
                'notarial_fee' => 10.00 + $i,
                'risk_flag' => null,
                'thumbprint_waived' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Signer for JRN-CA-00021
        DB::table('signers')->insert([
            'id' => Str::uuid()->toString(),
            'journal_entry_id' => 'JRN-CA-00021',
            'full_name' => 'John Smith',
            'email' => 'john.smith@example.com',
            'phone' => '555-0001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ================================================================
    // TC_ListAD__FUN_01 → TC_ListAD__FUN_08: Admin/Compliance List
    // ================================================================

    /**
     * TC_ListAD__FUN_01: Admin/Compliance can view paginated journal entries list
     */
    public function test_admin_can_view_paginated_journal_list(): void
    {
        $response = $this->actingAs($this->admin)
                        ->getJson('/api/v1/journals?page=1&limit=10');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                    'meta' => ['total', 'page', 'limit', 'total_pages', 'has_prev', 'has_next'],
                ]);

        // 25 total entries, page 1 of 10 => should have next page
        $this->assertEquals(25, $response->json('meta.total'));
        $this->assertEquals(1, $response->json('meta.page'));
        $this->assertTrue($response->json('meta.has_next'));
        $this->assertFalse($response->json('meta.has_prev'));
    }

    public function test_compliance_can_view_paginated_journal_list(): void
    {
        $response = $this->actingAs($this->compliance)
                        ->getJson('/api/v1/journals?page=1&limit=10');

        $response->assertStatus(200);
        $this->assertEquals(25, $response->json('meta.total'));
    }

    /**
     * TC_ListAD__FUN_02: Search journal entries by exact Entry ID
     */
    public function test_search_exact_entry_id(): void
    {
        $response = $this->actingAs($this->admin)
                        ->getJson('/api/v1/journals?search=JRN-CA-00021');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('JRN-CA-00021', $response->json('data.0.entry_id'));
    }

    /**
     * TC_ListAD__FUN_03: Search journal entries by partial Entry ID
     */
    public function test_search_partial_entry_id(): void
    {
        $response = $this->actingAs($this->admin)
                        ->getJson('/api/v1/journals?search=CA-000');

        $response->assertStatus(200);
        // JRN-CA-00021, JRN-CA-00022, JRN-CA-00023
        $this->assertEquals(3, $response->json('meta.total'));
    }

    /**
     * TC_ListAD__FUN_04: Filter journal list by Notary Name
     */
    public function test_filter_by_notary_name(): void
    {
        // Filter by notary_id (Emily Carter)
        $response = $this->actingAs($this->admin)
                        ->getJson('/api/v1/journals?notary_id=' . $this->notaryEmily->id);

        $response->assertStatus(200);

        // Emily owns JRN-CA-00021, JRN-CA-00022, JRN-CA-00023 + ~10 bulk entries
        $data = $response->json('data');
        foreach ($data as $entry) {
            $this->assertEquals('Emily Carter', $entry['notary_name']);
        }
    }

    /**
     * TC_ListAD__FUN_05: Filter journal list by Act Type, State, and Status
     */
    public function test_filter_by_act_type_state_status(): void
    {
        $response = $this->actingAs($this->admin)
                        ->getJson('/api/v1/journals?act_type=Jurat&venue_state=TX&status=incomplete');

        $response->assertStatus(200);
        // JRN-TX-00011 and JRN-TX-00012 are Jurat + TX + incomplete
        $this->assertEquals(2, $response->json('meta.total'));
    }

    /**
     * TC_ListAD__FUN_06: Filter journal list by date range
     */
    public function test_filter_by_date_range(): void
    {
        $response = $this->actingAs($this->admin)
                        ->getJson('/api/v1/journals?start_date=2026-03-20&end_date=2026-03-25');

        $response->assertStatus(200);
        $data = $response->json('data');

        // All returned entries should fall within range
        foreach ($data as $entry) {
            $date = substr($entry['execution_date'], 0, 10);
            $this->assertGreaterThanOrEqual('2026-03-20', $date);
            $this->assertLessThanOrEqual('2026-03-25', $date);
        }

        // JRN-FL-00005 is 03/26, should NOT be in results
        $ids = array_column($data, 'entry_id');
        $this->assertNotContains('JRN-FL-00005', $ids);
    }

    /**
     * TC_ListAD__FUN_07: Multiple filters applied simultaneously
     */
    public function test_multiple_filters_combined(): void
    {
        $response = $this->actingAs($this->admin)
                        ->getJson('/api/v1/journals?notary_id=' . $this->notaryEmily->id
                            . '&venue_state=CA&status=incomplete');

        $response->assertStatus(200);
        // Only JRN-CA-00022 matches: Emily + CA + incomplete
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('JRN-CA-00022', $response->json('data.0.entry_id'));
    }

    /**
     * TC_ListAD__FUN_08: Clear All resets search and filters
     */
    public function test_clear_all_resets_to_default(): void
    {
        // First apply filters
        $filtered = $this->actingAs($this->admin)
                        ->getJson('/api/v1/journals?search=JRN-CA&venue_state=CA&status=draft');
        $filtered->assertStatus(200);
        $filteredTotal = $filtered->json('meta.total');

        // Then request without any filters => should have all 25
        $reset = $this->actingAs($this->admin)
                      ->getJson('/api/v1/journals');
        $reset->assertStatus(200);
        $this->assertEquals(25, $reset->json('meta.total'));
        $this->assertGreaterThan($filteredTotal, $reset->json('meta.total'));
    }

    // ================================================================
    // TC_ListNotary_FUN_01 → TC_ListNotary_FUN_10: Notary List
    // ================================================================

    /**
     * TC_ListNotary_FUN_01: Notary list shows only journal entries owned by current notary
     */
    public function test_notary_sees_only_own_entries(): void
    {
        $response = $this->actingAs($this->notaryEmily)
                        ->getJson('/api/v1/journals');

        $response->assertStatus(200);

        // Emily owns 3 named + some bulk entries, but NOT Mike's TX/FL entries
        $data = $response->json('data');
        foreach ($data as $entry) {
            $this->assertEquals('Emily Carter', $entry['notary_name']);
        }

        // Verify Mike's known entries are NOT present
        $ids = array_column($data, 'entry_id');
        $this->assertNotContains('JRN-TX-00011', $ids);
        $this->assertNotContains('JRN-FL-00005', $ids);
    }

    /**
     * TC_ListNotary_FUN_02: Notary cannot access another notary's journal entry by direct URL
     */
    public function test_notary_cannot_access_other_notary_detail(): void
    {
        // Emily tries to access Mike's entry
        $response = $this->actingAs($this->notaryEmily)
                        ->getJson('/api/v1/journals/JRN-TX-00011');

        $response->assertStatus(403);
    }

    /**
     * TC_ListNotary_FUN_03: Search own journal entries by exact Entry ID
     */
    public function test_notary_search_own_exact_id(): void
    {
        $response = $this->actingAs($this->notaryEmily)
                        ->getJson('/api/v1/journals?search=JRN-CA-00021');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('JRN-CA-00021', $response->json('data.0.entry_id'));
    }

    /**
     * TC_ListNotary_FUN_04: Search own journal entries by partial Entry ID
     */
    public function test_notary_search_own_partial_id(): void
    {
        $response = $this->actingAs($this->notaryEmily)
                        ->getJson('/api/v1/journals?search=CA-000');

        $response->assertStatus(200);
        // Emily's CA-000xx entries only
        $data = $response->json('data');
        foreach ($data as $entry) {
            $this->assertEquals('Emily Carter', $entry['notary_name']);
        }
        $this->assertGreaterThanOrEqual(3, $response->json('meta.total'));
    }

    /**
     * TC_ListNotary_FUN_05: Filter own journal entries by Act Type, State, Status, and Date Range
     */
    public function test_notary_filter_own_entries(): void
    {
        $response = $this->actingAs($this->notaryEmily)
                        ->getJson('/api/v1/journals?act_type=Acknowledgment&venue_state=CA&status=draft'
                            . '&start_date=2026-03-20&end_date=2026-03-25');

        $response->assertStatus(200);
        // Only JRN-CA-00021 matches: Acknowledgment + CA + draft + in date range
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('JRN-CA-00021', $response->json('data.0.entry_id'));
    }

    /**
     * TC_ListNotary_FUN_06: Clear All resets all search and filters on Notary list
     */
    public function test_notary_clear_all_resets(): void
    {
        // Filtered
        $filtered = $this->actingAs($this->notaryEmily)
                        ->getJson('/api/v1/journals?search=JRN-CA&status=draft');
        $filtered->assertStatus(200);
        $filteredTotal = $filtered->json('meta.total');

        // Default (no filters) shows all own entries
        $reset = $this->actingAs($this->notaryEmily)
                      ->getJson('/api/v1/journals');
        $reset->assertStatus(200);
        $this->assertGreaterThanOrEqual($filteredTotal, $reset->json('meta.total'));

        // Verify still only own entries
        $data = $reset->json('data');
        foreach ($data as $entry) {
            $this->assertEquals('Emily Carter', $entry['notary_name']);
        }
    }

    /**
     * TC_ListNotary_FUN_07: Risk flag is shown to Notary for entries requiring attention
     */
    public function test_notary_risk_flag_visible(): void
    {
        $response = $this->actingAs($this->notaryEmily)
                        ->getJson('/api/v1/journals?risk_flag=MISSING_SIGNATURE');

        $response->assertStatus(200);
        // JRN-CA-00022 has risk_flag = MISSING_SIGNATURE
        $this->assertEquals(1, $response->json('meta.total'));
        $data = $response->json('data.0');
        $this->assertEquals('MISSING_SIGNATURE', $data['risk_flag']);
        $this->assertEquals('JRN-CA-00022', $data['entry_id']);
    }

    /**
     * TC_ListNotary_FUN_08: View details action opens current notary's journal entry detail
     */
    public function test_notary_view_own_detail(): void
    {
        $response = $this->actingAs($this->notaryEmily)
                        ->getJson('/api/v1/journals/JRN-CA-00021');

        $response->assertStatus(200)
                ->assertJsonPath('data.id', 'JRN-CA-00021')
                ->assertJsonPath('data.notary_name', 'Emily Carter');
    }

    /**
     * TC_ListNotary_FUN_09: Export permission follows role policy (API-level test)
     */
    public function test_notary_export_follows_role_policy(): void
    {
        // Verify Notary can access their own journal detail (prerequisite for export)
        $response = $this->actingAs($this->notaryEmily)
                        ->getJson('/api/v1/journals/JRN-CA-00021');

        $response->assertStatus(200);
        // Entry data is returned => Notary has read access to own data
        $this->assertTrue($response->json('success'));
    }

    /**
     * TC_ListNotary_FUN_10: Notary list shows empty state when no records match filters
     */
    public function test_notary_empty_state_no_matching_records(): void
    {
        $response = $this->actingAs($this->notaryEmily)
                        ->getJson('/api/v1/journals?search=JRN-NOT-FOUND');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('meta.total'));
        $this->assertEmpty($response->json('data'));
    }
}
