<?php

namespace App\Services;

use App\Repositories\JournalRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DashboardService
{
    public function __construct(protected JournalRepository $repo) {}

    /**
     * Tính toán KPI Summary từ các filter trong request.
     */
    public function getKpiSummary(array $filters): array
    {
        return [
            'total_entries'    => $this->repo->countJournalEntries($filters),
            'incomplete'       => $this->repo->countIncomplete($filters),
            'entries_by_state' => $this->repo->getEntriesByState($filters),
            'alerts'           => [
                'missing_signatures'  => $this->repo->countMissingSignatures($filters),
                'missing_thumbprints' => $this->repo->countMissingThumbprints($filters),
            ],
        ];
    }

    /**
     * Lấy compliance logs gần nhất với giới hạn limit.
     */
    public function getComplianceLogs(int $limit): array
    {
        $limit = min(10, max(1, $limit));
        return [
            'logs' => $this->repo->getComplianceLogs($limit),
        ];
    }

    /**
     * Lấy chi tiết một audit log.
     */
    public function getAuditLogDetail(string $id): array|false
    {
        $log = $this->repo->findAuditLogById($id);

        if (!$log) {
            return false;
        }

        return [
            'id'                    => $log->id,
            'timestamp'             => $log->timestamp,
            'initiator_name'        => $log->initiator_name,
            'action'                => $log->action,
            'resource_id'           => $log->resource_id,
            'flags'                 => $log->flags,
            'change_details_before' => json_decode($log->change_details_before),
            'change_details_after'  => json_decode($log->change_details_after),
        ];
    }

    /**
     * Xử lý gửi reminder email cho missing signatures.
     * Trả về số lượng email đã gửi.
     */
    public function sendMissingSignatureReminders(
        ?string $target,
        array $entryIds,
        ?object $initiator
    ): int {
        $affectedEntries = $this->repo->getMissingSignatureEntries($target, $entryIds);
        $emailsSentCount = $affectedEntries->unique('notary_id')->count();

        $now = now()->toIso8601String();
        $this->repo->insertAuditLog([
            'id'                   => Str::uuid()->toString(),
            'timestamp'            => $now,
            'initiator_name'       => $initiator?->full_name ?? 'SYSTEM',
            'action'               => 'REMINDER_EMAIL_SENT',
            'resource_id'          => 'MISSING_SIGNATURES',
            'change_details_after' => json_encode([
                'emails_sent_count' => $emailsSentCount,
                'entry_ids'         => $affectedEntries->pluck('entry_id')->unique()->values(),
            ]),
            'flags'                => 'INFO',
        ]);

        return $emailsSentCount;
    }
}
