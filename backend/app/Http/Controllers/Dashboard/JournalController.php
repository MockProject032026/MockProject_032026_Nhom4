<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JournalController extends Controller
{
    /**
     * API #6: GET /api/v1/journals
     *
     * Danh sách journal phân trang, hỗ trợ multi-filter và role-based.
     * - Admin/Compliance: thấy tất cả
     * - Notary: chỉ thấy bản ghi của mình
     *
     * Query params: page, limit, search, venue_state, status, risk_flag,
     *               notary_id, start_date, end_date
     */
    public function index(Request $request)
    {
        $page   = max(1, (int) $request->query('page', 1));
        $limit  = min(50, max(1, (int) $request->query('limit', 10)));
        $offset = ($page - 1) * $limit;

        $query = DB::table('journal_entries as je')
            ->join('users as u', 'u.id', '=', 'je.notary_id')
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
                's.full_name as signer_name',
            ])
            ->groupBy(
                'je.id', 'je.execution_date', 'u.full_name',
                'je.notarial_fee', 'je.status', 'je.risk_flag',
                'je.venue_state', 'je.venue_county', 'je.is_holiday',
                's.full_name'
            );

        // ── Role-based filtering ────────────────────────────────
        $user = $request->user();
        if ($user) {
            // id_role: Notary (giả sử id_role != 1 và != 2 = notary)
            // Admin = 1, Compliance = 2, Notary = 3 (hoặc theo convention)
            if (! in_array($user->id_role, [1, 2])) {
                $query->where('je.notary_id', $user->id);
            }
        }

        // ── Search (by Entry ID or partial match) ───────────────
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('je.id', 'LIKE', $search)
                  ->orWhere('u.full_name', 'LIKE', $search)
                  ->orWhere('s.full_name', 'LIKE', $search);
            });
        }

        // ── Filters ─────────────────────────────────────────────
        if ($request->filled('venue_state')) {
            $query->where('je.venue_state', $request->venue_state);
        }
        if ($request->filled('status')) {
            $query->where('je.status', $request->status);
        }
        if ($request->filled('risk_flag')) {
            $query->where('je.risk_flag', $request->risk_flag);
        }
        if ($request->filled('notary_id')) {
            $query->where('je.notary_id', $request->notary_id);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('je.execution_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('je.execution_date', '<=', $request->end_date);
        }

        // ── Pagination ──────────────────────────────────────────
        // Count total from a subquery to handle GROUP BY correctly
        $countQuery = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query);
        $total = $countQuery->count();

        $totalPages = (int) ceil($total / $limit);

        $data = $query
            ->orderBy('je.execution_date', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => $totalPages,
                'has_prev'    => $page > 1,
                'has_next'    => $page < $totalPages,
            ],
        ]);
    }

    /**
     * API #7: GET /api/v1/journals/{id}
     *
     * Lấy toàn bộ thông tin chi tiết của một hồ sơ nhật ký.
     * Join: journal_entries + signers + biometric_data + fee_breakdowns
     */
    public function show(string $id)
    {
        // ── Journal entry base info ─────────────────────────────
        $entry = DB::table('journal_entries as je')
            ->join('users as u', 'u.id', '=', 'je.notary_id')
            ->where('je.id', $id)
            ->select([
                'je.id',
                'je.notary_id',
                'u.full_name as notary_name',
                'je.execution_date',
                'je.venue_state',
                'je.venue_county',
                'je.status',
                'je.notarial_fee',
                'je.is_holiday',
                'je.holiday_name',
                'je.holiday_type',
                'je.document_description',
                'je.risk_flag',
                'je.verification_method',
                'je.thumbprint_waived',
            ])
            ->first();

        if (! $entry) {
            return response()->json([
                'success' => false,
                'message' => 'Journal entry not found',
            ], 404);
        }

        // ── Signers + biometric data ────────────────────────────
        $signers = DB::table('signers as s')
            ->leftJoin('biometric_data as bd', 'bd.signer_id', '=', 's.id')
            ->where('s.journal_entry_id', $id)
            ->select([
                's.id as signer_id',
                's.full_name',
                's.email',
                's.phone',
                's.address',
                's.id_type',
                's.id_number',
                's.id_issuing_authority',
                's.id_expiration_date',
                's.customer_notes',
                'bd.signature_image',
                'bd.thumbprint_image',
                'bd.biometric_match_hash',
                'bd.capture_device_id',
                'bd.capture_location',
            ])
            ->get();

        // ── Fee breakdown ───────────────────────────────────────
        $fees = DB::table('fee_breakdowns')
            ->where('journal_entry_id', $id)
            ->select([
                'base_notarial_fee',
                'service_fee',
                'travel_fee',
                'convenience_fee',
                'rush_fee',
                'holiday_fee',
                'total_amount',
                'notary_share',
                'company_share',
            ])
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id'                    => $entry->id,
                'notary_id'             => $entry->notary_id,
                'notary_name'           => $entry->notary_name,
                'execution_date'        => $entry->execution_date,
                'venue_state'           => $entry->venue_state,
                'venue_county'          => $entry->venue_county,
                'status'                => $entry->status,
                'notarial_fee'          => $entry->notarial_fee,
                'is_holiday'            => (bool) $entry->is_holiday,
                'holiday_name'          => $entry->holiday_name,
                'holiday_type'          => $entry->holiday_type,
                'document_description'  => $entry->document_description,
                'risk_flag'             => $entry->risk_flag,
                'verification_method'   => $entry->verification_method,
                'thumbprint_waived'     => (bool) $entry->thumbprint_waived,
                'signers'               => $signers,
                'fee_breakdown'         => $fees,
            ],
        ]);
    }

    /**
     * API #5: PATCH /api/v1/journals/{id}/waive-thumbprint
     *
     * Admin duyệt miễn trừ yêu cầu vân tay cho hồ sơ.
     * Body: { "action": "WAIVE", "notes": "..." }
     * Cập nhật thumbprint_waived = 1, ghi audit log.
     */
    public function waiveThumbprint(Request $request, string $id)
    {
        $request->validate([
            'action' => 'required|string|in:WAIVE',
            'notes'  => 'nullable|string|max:1000',
        ]);

        // ── Kiểm tra entry tồn tại ────────────────────────────
        $entry = DB::table('journal_entries')->where('id', $id)->first();

        if (! $entry) {
            return response()->json([
                'success' => false,
                'message' => 'Journal entry not found',
            ], 404);
        }

        // ── Lưu trạng thái trước khi thay đổi (cho audit log) ──
        $before = json_encode([
            'thumbprint_waived' => (bool) $entry->thumbprint_waived,
        ]);

        // ── Cập nhật thumbprint_waived ──────────────────────────
        DB::table('journal_entries')
            ->where('id', $id)
            ->update(['thumbprint_waived' => 1]);

        $after = json_encode([
            'thumbprint_waived' => true,
            'notes'             => $request->input('notes', ''),
        ]);

        // ── Ghi audit log ───────────────────────────────────────
        $now = now()->toIso8601String();
        DB::table('audit_logs')->insert([
            'id'                    => Str::uuid(),
            'timestamp'             => $now,
            'initiator_name'        => $request->user()->full_name ?? 'SYSTEM',
            'action'                => 'WAIVE_THUMBPRINT',
            'resource_id'           => $id,
            'change_details_before' => $before,
            'change_details_after'  => $after,
            'flags'                 => 'INFO',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thumbprint waived successfully',
        ]);
    }
}
