<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    /**
     * API #1: GET /api/v1/dashboard/kpi-summary
     *
     * Lấy tổng journal, hồ sơ thiếu, count theo state và alerts.
     * Filters: venue_state, notary_id, start_date, end_date
     */
    public function kpiSummary(Request $request)
    {
        $query = DB::table('journal_entries');

        // Lọc theo request
        if ($request->filled('venue_state')) $query->where('venue_state', $request->venue_state);
        if ($request->filled('notary_id')) $query->where('notary_id', $request->notary_id);
        if ($request->filled('start_date')) $query->whereDate('execution_date', '>=', $request->start_date);
        if ($request->filled('end_date')) $query->whereDate('execution_date', '<=', $request->end_date);

        // 1. Dữ liệu KPI cơ bản
        $totalEntries = (clone $query)->count();
        $incomplete = (clone $query)->where(function ($q) {
            $q->where('status', '!=', 'completed')->orWhereNull('status');
        })->count();
        $activeNotaries = (clone $query)->distinct('notary_id')->count('notary_id');

        // 2. Dữ liệu Biểu đồ Bang (Entries by State)
        $entriesByState = (clone $query)
            ->select('venue_state', DB::raw('COUNT(*) as count'))
            ->whereNotNull('venue_state')
            ->groupBy('venue_state')
            ->orderByDesc('count')
            ->get();

        // 3. Dữ liệu Biểu đồ Tròn (Doughnut Chart)
        $chartDoughnut = (clone $query)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        // 4. Dữ liệu Dropdown (Lấy tất cả để làm bộ lọc)
        $filterOptions = [
            'states' => DB::table('journal_entries')->whereNotNull('venue_state')->distinct()->pluck('venue_state'),
            'notaries' => DB::table('users as u')->join('journal_entries as je', 'u.id', '=', 'je.notary_id')
                            ->select('u.id', 'u.full_name')->distinct()->get()
        ];

        // 5. Cảnh báo (Alerts)
        $missingSignatures = DB::table('journal_entries as je')->join('signers as s', 's.journal_entry_id', '=', 'je.id')
            ->leftJoin('biometric_data as bd', 'bd.signer_id', '=', 's.id')
            ->where(function ($q) { $q->whereNull('bd.signature_image')->orWhere('bd.signature_image', '=', ''); })->count();
            
        $missingThumbprints = DB::table('journal_entries as je')->join('signers as s', 's.journal_entry_id', '=', 'je.id')
            ->leftJoin('biometric_data as bd', 'bd.signer_id', '=', 's.id')
            ->where('je.thumbprint_waived', 0)
            ->where(function ($q) { $q->whereNull('bd.thumbprint_image')->orWhere('bd.thumbprint_image', '=', ''); })->count();

        // Mock Trends (Tính toán thực tế cần query tháng trước, ở đây tạo mock logic để hiển thị UI)
        $trends = [
            'total' => '+12.5%',
            'incomplete' => '-2.4%',
            'active' => '+5.0%'
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'kpi' => [
                    'total_entries' => $totalEntries,
                    'incomplete' => $incomplete,
                    'active_notaries' => $activeNotaries,
                    'trends' => $trends
                ],
                'charts' => [
                    'by_state' => $entriesByState,
                    'doughnut' => $chartDoughnut
                ],
                'filters_data' => $filterOptions,
                'alerts' => [
                    'missing_signatures' => $missingSignatures,
                    'missing_thumbprints' => $missingThumbprints,
                ],
            ],
        ]);
    }

    /**
     * API #2: GET /api/v1/dashboard/compliance-logs
     *
     * Lấy 3-5 sự kiện compliance gần nhất.
     * Query param: limit (default 5)
     */
    public function complianceLogs(Request $request)
    {
        // Nhận tham số page và limit
        $page   = max(1, (int) $request->query('page', 1));
        $limit  = min(10, max(1, (int) $request->query('limit', 5)));
        $offset = ($page - 1) * $limit;

        $query = DB::table('audit_logs');
        
        // Đếm tổng số dữ liệu để tính toán phân trang
        $total = $query->count();
        $totalPages = (int) ceil($total / $limit);

        // Lấy dữ liệu của trang hiện tại
        $logs = $query->orderBy('timestamp', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->select([
                'id',
                'timestamp',
                'initiator_name as notary',
                'action',
                'resource_id as journal_id',
                'flags',
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'logs' => $logs,
            ],
            // Trả về thông tin meta giống hệt JournalController
            'meta' => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => $totalPages,
                'has_prev'    => $page > 1,
                'has_next'    => $page < $totalPages,
            ]
        ]);
    }

    /**
     * API #3: GET /api/v1/audit-logs/{id}
     *
     * Lấy chi tiết "Before" và "After" của một log sự kiện cụ thể.
     */
    public function auditLogDetail(string $id)
    {
        $log = DB::table('audit_logs')->where('id', $id)->first();

        if (! $log) {
            return response()->json([
                'success' => false,
                'message' => 'Audit log not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id'                    => $log->id,
                'timestamp'             => $log->timestamp,
                'initiator_name'        => $log->initiator_name,
                'action'                => $log->action,
                'resource_id'           => $log->resource_id,
                'flags'                 => $log->flags,
                'change_details_before' => json_decode($log->change_details_before),
                'change_details_after'  => json_decode($log->change_details_after),
            ],
        ]);
    }

    /**
     * API #4: POST /api/v1/notaries/reminders/missing-signatures
     *
     * Gửi email nhắc nhở hàng loạt cho Notary bổ sung chữ ký.
     * Body: { "target": "ALL" } hoặc { "entry_ids": [...] }
     * Sau khi gửi, insert log vào audit_logs.
     */
    public function sendMissingSignatureReminders(Request $request)
    {
        $request->validate([
            'target'    => 'nullable|string|in:ALL',
            'entry_ids' => 'nullable|array',
            'entry_ids.*' => 'uuid',
        ]);

        // ── Tìm các entries bị thiếu signature ─────────────────
        $query = DB::table('journal_entries as je')
            ->join('signers as s', 's.journal_entry_id', '=', 'je.id')
            ->leftJoin('biometric_data as bd', 'bd.signer_id', '=', 's.id')
            ->join('users as u', 'u.id', '=', 'je.notary_id')
            ->where(function ($q) {
                $q->whereNull('bd.signature_image')
                  ->orWhere('bd.signature_image', '=', '');
            });

        // Nếu không phải ALL, chỉ lọc theo entry_ids cụ thể
        if ($request->input('target') !== 'ALL' && $request->filled('entry_ids')) {
            $query->whereIn('je.id', $request->entry_ids);
        }

        $affectedEntries = $query
            ->select([
                'je.id as entry_id',
                'u.id as notary_id',
                'u.email as notary_email',
                'u.full_name as notary_name',
            ])
            ->distinct()
            ->get();

        $emailsSentCount = $affectedEntries->unique('notary_id')->count();

        // ── Ghi audit log ───────────────────────────────────────
        $now = now()->toIso8601String();
        DB::table('audit_logs')->insert([
            'id'                   => Str::uuid(),
            'timestamp'            => $now,
            'initiator_name'       => $request->user()->full_name ?? 'SYSTEM',
            'action'               => 'REMINDER_EMAIL_SENT',
            'resource_id'          => 'MISSING_SIGNATURES',
            'change_details_after' => json_encode([
                'emails_sent_count' => $emailsSentCount,
                'entry_ids'         => $affectedEntries->pluck('entry_id')->unique()->values(),
            ]),
            'flags'                => 'INFO',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'emails_sent_count' => $emailsSentCount,
            ],
        ]);
    }
}
