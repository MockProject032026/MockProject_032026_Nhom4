<?php

namespace App\Services;

use App\Repositories\JournalRepository;
use Illuminate\Support\Str;

class JournalService
{
    public function __construct(protected JournalRepository $repo) {}

    /**
     * Lấy danh sách journal phân trang với filter và role-based access.
     * Trả về array gồm data + meta.
     */
    public function listJournals(array $filters, ?object $user): array
    {
        $page   = max(1, (int) ($filters['page'] ?? 1));
        $limit  = min(50, max(1, (int) ($filters['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;

        $total      = $this->repo->countJournals($filters, $user);
        $totalPages = (int) ceil($total / $limit);
        $data       = $this->repo->getJournalList($filters, $user, $offset, $limit);

        return [
            'data' => $data,
            'meta' => [
                'total'       => $total,
                'total_pages' => $totalPages,
                'page'        => $page,
                'limit'       => $limit,
                'has_prev'    => $page > 1,
                'has_next'    => $page < $totalPages,
            ],
        ];
    }

    /**
     * Lấy chi tiết một journal entry kèm signer và fee breakdown.
     * Trả về false nếu không tìm thấy, 403 string nếu bị cấm.
     */
    public function getJournalDetail(string $id, ?object $user): array|false|string
    {
        $entry = $this->repo->findJournalById($id);

        if (!$entry) {
            return false;
        }

        // Ownership check: Notary chỉ xem bản ghi của mình
        if ($user && !in_array((int) $user->id_role, [1, 2]) && $entry->notary_id !== $user->id) {
            return 'forbidden';
        }

        $signers = $this->repo->getSignersForJournal($id);
        $fees    = $this->repo->getFeeBreakdown($id);

        return [
            'id'                   => $entry->id,
            'notary_id'            => $entry->notary_id,
            'notary_name'          => $entry->notary_name,
            'execution_date'       => $entry->execution_date,
            'venue_state'          => $entry->venue_state,
            'venue_county'         => $entry->venue_county,
            'status'               => $entry->status,
            'notarial_fee'         => $entry->notarial_fee,
            'act_type'             => $entry->act_type,
            'is_holiday'           => (bool) $entry->is_holiday,
            'holiday_name'         => $entry->holiday_name,
            'holiday_type'         => $entry->holiday_type,
            'document_description' => $entry->document_description,
            'risk_flag'            => $entry->risk_flag,
            'verification_method'  => $entry->verification_method,
            'thumbprint_waived'    => (bool) $entry->thumbprint_waived,
            'signers'              => $signers,
            'fee_breakdown'        => $fees,
        ];
    }

    /**
     * Waive thumbprint cho một journal entry.
     * Trả về false nếu không tìm thấy entry.
     */
    public function waiveThumbprint(string $id, string $notes, ?object $initiator): bool
    {
        $entry = $this->repo->findJournalById($id);

        if (!$entry) {
            return false;
        }

        $before = json_encode(['thumbprint_waived' => (bool) $entry->thumbprint_waived]);
        $after  = json_encode(['thumbprint_waived' => true, 'notes' => $notes]);

        $this->repo->updateThumbprintWaived($id);

        $this->repo->insertAuditLog([
            'id'                    => Str::uuid()->toString(),
            'timestamp'             => now()->toIso8601String(),
            'initiator_name'        => $initiator?->full_name ?? 'SYSTEM',
            'action'                => 'WAIVE_THUMBPRINT',
            'resource_id'           => $id,
            'change_details_before' => $before,
            'change_details_after'  => $after,
            'flags'                 => 'INFO',
        ]);

        return true;
    }
}
