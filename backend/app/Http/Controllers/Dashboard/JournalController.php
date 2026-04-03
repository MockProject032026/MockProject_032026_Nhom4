<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\JournalService;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    public function __construct(protected JournalService $service) {}

    /**
     * API #6: GET /api/v1/journals
     * Danh sách phân trang, multi-filter, role-based.
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'page', 'limit', 'search', 'venue_state', 'status',
            'risk_flag', 'notary_id', 'start_date', 'end_date',
            'act_type', 'is_holiday',
        ]);

        $result = $this->service->listJournals($filters, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách journal thành công.',
            'data'    => $result['data'],
            'meta'    => $result['meta'],
        ], 200);
    }

    /**
     * API #7: GET /api/v1/journals/{id}
     * Chi tiết một journal entry kèm signers + fee breakdown.
     */
    public function show(Request $request, string $id)
    {
        $result = $this->service->getJournalDetail($id, $request->user());

        if ($result === false) {
            return response()->json([
                'success' => false,
                'message' => 'Journal entry not found',
            ], 404);
        }

        if ($result === 'forbidden') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. You do not have permission to access this resource.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /**
     * API #5: PATCH /api/v1/journals/{id}/waive-thumbprint
     * Body: { "action": "WAIVE", "notes": "..." }
     */
    public function waiveThumbprint(Request $request, string $id)
    {
        $request->validate([
            'action' => 'required|string|in:WAIVE',
            'notes'  => 'nullable|string|max:1000',
        ]);

        $success = $this->service->waiveThumbprint(
            id: $id,
            notes: $request->input('notes', ''),
            initiator: $request->user(),
        );

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Journal entry not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Thumbprint waived successfully',
        ]);
    }
}
