<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $service) {}

    /**
     * API #1: GET /api/v1/dashboard/kpi-summary
     * Filters: venue_state, notary_id, start_date, end_date
     */
    public function kpiSummary(Request $request)
    {
        $filters = $request->only(['venue_state', 'notary_id', 'start_date', 'end_date']);
<<<<<<< Updated upstream

        return response()->json([
            'success' => true,
            'data'    => $this->service->getKpiSummary($filters),
=======
        $data = $this->service->getKpiSummary($filters);

        return response()->json([
            'success' => true,
            'data'    => $data,
>>>>>>> Stashed changes
        ]);
    }

    /**
     * API #2: GET /api/v1/dashboard/compliance-logs
     * Query param: limit (default 5, max 10)
     */
    public function complianceLogs(Request $request)
    {
<<<<<<< Updated upstream
        $limit = (int) $request->query('limit', 5);

        return response()->json([
            'success' => true,
            'data'    => $this->service->getComplianceLogs($limit),
=======
        $page  = max(1, (int) $request->query('page', 1));
        $limit = min(10, max(1, (int) $request->query('limit', 5)));

        $result = $this->service->getComplianceLogs($limit, $page);

        return response()->json([
            'success' => true,
            'data'    => [
                'logs' => $result['logs'],
            ],
            'meta'    => $result['meta']
>>>>>>> Stashed changes
        ]);
    }

    /**
     * API #3: GET /api/v1/dashboard/audit-logs/{id}
     */
    public function auditLogDetail(string $id)
    {
        $data = $this->service->getAuditLogDetail($id);

        if ($data === false) {
            return response()->json([
                'success' => false,
                'message' => 'Audit log not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * API #4: POST /api/v1/notaries/reminders/missing-signatures
     * Body: { "target": "ALL" } hoặc { "entry_ids": [...] }
     */
    public function sendMissingSignatureReminders(Request $request)
    {
        $request->validate([
            'target'      => 'nullable|string|in:ALL',
            'entry_ids'   => 'nullable|array',
            'entry_ids.*' => 'string',
        ]);

        $emailsSentCount = $this->service->sendMissingSignatureReminders(
            target: $request->input('target'),
            entryIds: $request->input('entry_ids', []),
            initiator: $request->user(),
        );

        return response()->json([
            'success' => true,
            'data'    => ['emails_sent_count' => $emailsSentCount],
        ]);
    }
}
