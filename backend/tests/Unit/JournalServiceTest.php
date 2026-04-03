<?php

namespace Tests\Unit;

use App\Repositories\JournalRepository;
use App\Services\JournalService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Mockery;

class JournalServiceTest extends TestCase
{
    protected JournalRepository $repo;
    protected JournalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo    = Mockery::mock(JournalRepository::class);
        $this->service = new JournalService($this->repo);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────
    // listJournals
    // ─────────────────────────────────────────────────────────────────

    public function test_list_journals_returns_correct_meta(): void
    {
        $this->repo->shouldReceive('countJournals')
            ->once()
            ->andReturn(25);

        $this->repo->shouldReceive('getJournalList')
            ->once()
            ->andReturn(collect(array_fill(0, 10, ['entry_id' => 'test'])));

        $result = $this->service->listJournals(['page' => 1, 'limit' => 10], null);

        $this->assertEquals(25, $result['meta']['total']);
        $this->assertEquals(3, $result['meta']['total_pages']);
        $this->assertEquals(1, $result['meta']['page']);
        $this->assertEquals(10, $result['meta']['limit']);
        $this->assertFalse($result['meta']['has_prev']);
        $this->assertTrue($result['meta']['has_next']);
    }

    public function test_list_journals_last_page_has_no_next(): void
    {
        $this->repo->shouldReceive('countJournals')->andReturn(25);
        $this->repo->shouldReceive('getJournalList')->andReturn(collect([]));

        $result = $this->service->listJournals(['page' => 3, 'limit' => 10], null);

        $this->assertTrue($result['meta']['has_prev']);
        $this->assertFalse($result['meta']['has_next']);
    }

    public function test_list_journals_single_page(): void
    {
        $this->repo->shouldReceive('countJournals')->andReturn(5);
        $this->repo->shouldReceive('getJournalList')->andReturn(collect([]));

        $result = $this->service->listJournals(['page' => 1, 'limit' => 10], null);

        $this->assertEquals(1, $result['meta']['total_pages']);
        $this->assertFalse($result['meta']['has_prev']);
        $this->assertFalse($result['meta']['has_next']);
    }

    public function test_list_journals_empty_result(): void
    {
        $this->repo->shouldReceive('countJournals')->andReturn(0);
        $this->repo->shouldReceive('getJournalList')->andReturn(collect([]));

        $result = $this->service->listJournals([], null);

        $this->assertEquals(0, $result['meta']['total']);
        $this->assertEmpty($result['data']->toArray());
    }

    // ─────────────────────────────────────────────────────────────────
    // getJournalDetail
    // ─────────────────────────────────────────────────────────────────

    public function test_get_journal_detail_returns_false_when_not_found(): void
    {
        $this->repo->shouldReceive('findJournalById')
            ->once()
            ->andReturn(null);

        $result = $this->service->getJournalDetail('nonexistent-id', null);

        $this->assertFalse($result);
    }

    public function test_get_journal_detail_returns_forbidden_for_wrong_notary(): void
    {
        $entry = (object) ['id' => 'e1', 'notary_id' => 'notary-A', 'notary_name' => 'Alice'];
        $user  = (object) ['id' => 'notary-B', 'id_role' => 3];

        $this->repo->shouldReceive('findJournalById')->andReturn($entry);

        $result = $this->service->getJournalDetail('e1', $user);

        $this->assertEquals('forbidden', $result);
    }

    public function test_get_journal_detail_admin_can_access_any_entry(): void
    {
        $entry = (object) [
            'id' => 'e1', 'notary_id' => 'notary-A', 'notary_name' => 'Alice',
            'execution_date' => '2026-01-01', 'venue_state' => 'CA',
            'venue_county' => 'LA', 'status' => 'completed',
            'notarial_fee' => 25.00, 'act_type' => 'Acknowledgment',
            'is_holiday' => false, 'holiday_name' => null, 'holiday_type' => null,
            'document_description' => null, 'risk_flag' => null,
            'verification_method' => 'ID_CARD', 'thumbprint_waived' => false,
        ];
        $admin = (object) ['id' => 'admin-001', 'id_role' => 1];

        $this->repo->shouldReceive('findJournalById')->andReturn($entry);
        $this->repo->shouldReceive('getSignersForJournal')->andReturn(collect([]));
        $this->repo->shouldReceive('getFeeBreakdown')->andReturn(null);

        $result = $this->service->getJournalDetail('e1', $admin);

        $this->assertIsArray($result);
        $this->assertEquals('e1', $result['id']);
    }

    public function test_get_journal_detail_notary_can_access_own_entry(): void
    {
        $entry = (object) [
            'id' => 'e1', 'notary_id' => 'notary-A', 'notary_name' => 'Alice',
            'execution_date' => '2026-01-01', 'venue_state' => 'CA',
            'venue_county' => 'LA', 'status' => 'draft', 'notarial_fee' => 15,
            'act_type' => 'Jurat', 'is_holiday' => false,
            'holiday_name' => null, 'holiday_type' => null,
            'document_description' => null, 'risk_flag' => null,
            'verification_method' => null, 'thumbprint_waived' => false,
        ];
        $notary = (object) ['id' => 'notary-A', 'id_role' => 3];

        $this->repo->shouldReceive('findJournalById')->andReturn($entry);
        $this->repo->shouldReceive('getSignersForJournal')->andReturn(collect([]));
        $this->repo->shouldReceive('getFeeBreakdown')->andReturn(null);

        $result = $this->service->getJournalDetail('e1', $notary);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('signers', $result);
        $this->assertArrayHasKey('fee_breakdown', $result);
    }

    // ─────────────────────────────────────────────────────────────────
    // waiveThumbprint
    // ─────────────────────────────────────────────────────────────────

    public function test_waive_thumbprint_returns_false_when_not_found(): void
    {
        $this->repo->shouldReceive('findJournalById')->andReturn(null);

        $result = $this->service->waiveThumbprint('nonexistent', '', null);

        $this->assertFalse($result);
    }

    public function test_waive_thumbprint_updates_and_inserts_audit_log(): void
    {
        $entry = (object) [
            'id' => 'e1', 'notary_id' => 'n1', 'notary_name' => 'Alice',
            'thumbprint_waived' => false,
            'execution_date' => '2026-01-01', 'venue_state' => 'CA',
            'venue_county' => 'LA', 'status' => 'draft', 'notarial_fee' => 15,
            'act_type' => 'Jurat', 'is_holiday' => false,
        ];
        $initiator = (object) ['full_name' => 'Admin User'];

        $this->repo->shouldReceive('findJournalById')->andReturn($entry);
        $this->repo->shouldReceive('updateThumbprintWaived')->once()->with('e1');
        $this->repo->shouldReceive('insertAuditLog')->once();

        $result = $this->service->waiveThumbprint('e1', 'Test notes', $initiator);

        $this->assertTrue($result);
    }
}
