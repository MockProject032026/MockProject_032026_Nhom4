<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JournalRepository
{
    // ─────────────────────────────────────────────────────────────────
    // Journal Entries
    // ─────────────────────────────────────────────────────────────────

    /**
     * Build base query với filters chung, trả về Query Builder.
     * Không có select/group-by để có thể dùng chung cho COUNT và DATA.
     */
    public function buildJournalQuery(array $filters, ?object $user = null)
    {
        $query = DB::table('journal_entries as je')
            ->join('users as u', 'u.id', '=', 'je.notary_id');

        // Search (entry_id, notary name, venue)
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('je.id', 'like', $search)
                  ->orWhere('u.full_name', 'like', $search)
                  ->orWhere('je.venue_state', 'like', $search)
                  ->orWhere('je.venue_county', 'like', $search);
            });
        }

        if (!empty($filters['status'])) {
            $query->where('je.status', $filters['status']);
        }

        if (!empty($filters['risk_flag'])) {
            $query->where('je.risk_flag', $filters['risk_flag']);
        }

        if (!empty($filters['act_type'])) {
            $query->where('je.act_type', $filters['act_type']);
        }

        if (isset($filters['is_holiday'])) {
            $query->where('je.is_holiday', (bool) $filters['is_holiday']);
        }

        if (!empty($filters['venue_state'])) {
            $query->where('je.venue_state', $filters['venue_state']);
        }

        if (!empty($filters['notary_id'])) {
            $query->where('je.notary_id', $filters['notary_id']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('je.execution_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('je.execution_date', '<=', $filters['end_date']);
        }

        // Role-based filter: chỉ Notary (id_role != 1,2) thấy bản ghi của mình
        if ($user && !in_array((int) $user->id_role, [1, 2])) {
            $query->where('je.notary_id', $user->id);
        }

        return $query;
    }

    /**
     * Đếm tổng số bản ghi khớp filter.
     */
    public function countJournals(array $filters, ?object $user = null): int
    {
        return $this->buildJournalQuery($filters, $user)
            ->distinct()
            ->count('je.id');
    }

    /**
     * Lấy danh sách journal phân trang.
     */
    public function getJournalList(array $filters, ?object $user, int $offset, int $limit)
    {
        return $this->buildJournalQuery($filters, $user)
            ->leftJoin('signers as s', 's.journal_entry_id', '=', 'je.id')
            ->select([
                'je.id as entry_id',
                'je.execution_date',
                'u.full_name as notary_name',
                'je.notarial_fee as fee',
                'je.status',
                'je.risk_flag',
                'je.venue_state',
                'je.venue_county',
                'je.is_holiday',
                'je.act_type',
                DB::raw('MAX(s.full_name) as signer_name'),
            ])
            ->groupBy(
                'je.id', 'je.execution_date', 'u.full_name',
                'je.notarial_fee', 'je.status', 'je.risk_flag',
                'je.venue_state', 'je.venue_county', 'je.is_holiday', 'je.act_type'
            )
            ->orderBy('je.execution_date', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * Lấy chi tiết một journal entry.
     */
    public function findJournalById(string $id): ?object
    {
        return DB::table('journal_entries as je')
            ->join('users as u', 'u.id', '=', 'je.notary_id')
            ->where('je.id', $id)
            ->select([
                'je.id', 'je.notary_id', 'u.full_name as notary_name',
                'je.execution_date', 'je.venue_state', 'je.venue_county',
                'je.status', 'je.notarial_fee', 'je.act_type', 'je.is_holiday',
                'je.holiday_name', 'je.holiday_type', 'je.document_description',
                'je.risk_flag', 'je.verification_method', 'je.thumbprint_waived',
            ])
            ->first();
    }

    /**
     * Lấy danh sách signers + biometric của một journal.
     */
    public function getSignersForJournal(string $journalId)
    {
        return DB::table('signers as s')
            ->leftJoin('biometric_data as bd', 'bd.signer_id', '=', 's.id')
            ->where('s.journal_entry_id', $journalId)
            ->select([
                's.id as signer_id', 's.full_name', 's.email', 's.phone',
                's.address', 's.id_type', 's.id_number', 's.id_issuing_authority',
                's.id_expiration_date', 's.customer_notes',
                'bd.signature_image', 'bd.thumbprint_image',
                'bd.biometric_match_hash', 'bd.capture_device_id', 'bd.capture_location',
            ])
            ->get();
    }

    /**
     * Lấy fee breakdown của một journal.
     */
    public function getFeeBreakdown(string $journalId): ?object
    {
        return DB::table('fee_breakdowns')
            ->where('journal_entry_id', $journalId)
            ->select([
                'base_notarial_fee', 'service_fee', 'travel_fee',
                'convenience_fee', 'rush_fee', 'holiday_fee',
                'total_amount', 'notary_share', 'company_share',
            ])
            ->first();
    }

    /**
     * Cập nhật thumbprint_waived của một journal.
     */
    public function updateThumbprintWaived(string $id): void
    {
        DB::table('journal_entries')
            ->where('id', $id)
            ->update(['thumbprint_waived' => 1]);
    }

    // ─────────────────────────────────────────────────────────────────
    // KPI Summary
    // ─────────────────────────────────────────────────────────────────

    public function countJournalEntries(array $filters): int
    {
        return $this->buildKpiBaseQuery($filters)->count();
    }

    public function countIncomplete(array $filters): int
    {
        return $this->buildKpiBaseQuery($filters)
            ->where(function ($q) {
                $q->where('status', '!=', 'completed')->orWhereNull('status');
            })
            ->count();
    }

    public function getEntriesByState(array $filters)
    {
        return $this->buildKpiBaseQuery($filters)
            ->select('venue_state', DB::raw('COUNT(*) as count'))
            ->whereNotNull('venue_state')
            ->groupBy('venue_state')
            ->orderByDesc('count')
            ->get();
    }

    public function countMissingSignatures(array $filters): int
    {
        return $this->buildBiometricAlertQuery($filters)
            ->where(function ($q) {
                $q->whereNull('bd.signature_image')
                  ->orWhere('bd.signature_image', '=', '');
            })
            ->count();
    }

    public function countMissingThumbprints(array $filters): int
    {
        return $this->buildBiometricAlertQuery($filters)
            ->where('je.thumbprint_waived', 0)
            ->where(function ($q) {
                $q->whereNull('bd.thumbprint_image')
                  ->orWhere('bd.thumbprint_image', '=', '');
            })
            ->count();
    }

    // ─────────────────────────────────────────────────────────────────
    // Audit Logs
    // ─────────────────────────────────────────────────────────────────

    public function getComplianceLogs(int $limit)
    {
        return DB::table('audit_logs')
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->select([
                'id', 'timestamp',
                'initiator_name as notary',
                'action',
                'resource_id as journal_id',
                'flags',
            ])
            ->get();
    }

    public function findAuditLogById(string $id): ?object
    {
        return DB::table('audit_logs')->where('id', $id)->first();
    }

    public function insertAuditLog(array $data): void
    {
        DB::table('audit_logs')->insert($data);
    }

    // ─────────────────────────────────────────────────────────────────
    // Missing Signature Reminders
    // ─────────────────────────────────────────────────────────────────

    public function getMissingSignatureEntries(?string $target, array $entryIds)
    {
        $query = DB::table('journal_entries as je')
            ->join('signers as s', 's.journal_entry_id', '=', 'je.id')
            ->leftJoin('biometric_data as bd', 'bd.signer_id', '=', 's.id')
            ->join('users as u', 'u.id', '=', 'je.notary_id')
            ->where(function ($q) {
                $q->whereNull('bd.signature_image')
                  ->orWhere('bd.signature_image', '=', '');
            });

        if ($target !== 'ALL' && !empty($entryIds)) {
            $query->whereIn('je.id', $entryIds);
        }

        return $query->select([
            'je.id as entry_id',
            'u.id as notary_id',
            'u.email as notary_email',
            'u.full_name as notary_name',
        ])->distinct()->get();
    }

    // ─────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────

    private function buildKpiBaseQuery(array $filters)
    {
        $query = DB::table('journal_entries');

        if (!empty($filters['venue_state'])) {
            $query->where('venue_state', $filters['venue_state']);
        }
        if (!empty($filters['notary_id'])) {
            $query->where('notary_id', $filters['notary_id']);
        }
        if (!empty($filters['start_date'])) {
            $query->whereDate('execution_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('execution_date', '<=', $filters['end_date']);
        }

        return $query;
    }

    private function buildBiometricAlertQuery(array $filters)
    {
        $query = DB::table('journal_entries as je')
            ->join('signers as s', 's.journal_entry_id', '=', 'je.id')
            ->leftJoin('biometric_data as bd', 'bd.signer_id', '=', 's.id');

        if (!empty($filters['venue_state'])) {
            $query->where('je.venue_state', $filters['venue_state']);
        }
        if (!empty($filters['notary_id'])) {
            $query->where('je.notary_id', $filters['notary_id']);
        }
        if (!empty($filters['start_date'])) {
            $query->whereDate('je.execution_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('je.execution_date', '<=', $filters['end_date']);
        }

        return $query;
    }
}
