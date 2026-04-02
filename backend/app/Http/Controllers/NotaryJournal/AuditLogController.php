<?php

namespace App\Http\Controllers\NotaryJournal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    // ── API #35: GET KPI Summary ───────────────────────────────────
    public function kpiSummary(Request $request)
    {
        $date = $request->query('date', now()->toDateString());

        $kpi = DB::table('audit_logs')
            ->whereDate('timestamp', $date)
            ->selectRaw("
                COUNT(*) as logs_today,
                COUNT(DISTINCT initiator_name) as active_users,
                SUM(CASE WHEN flags = 'FAILED'   THEN 1 ELSE 0 END) as failed_actions,
                SUM(CASE WHEN flags = 'CRITICAL' THEN 1 ELSE 0 END) as critical_alerts
            ")
            ->first();

        // Tính trend: so với hôm qua
        $yesterday = DB::table('audit_logs')
            ->whereDate('timestamp', now()->subDay()->toDateString())
            ->whereIn('flags', ['CRITICAL'])
            ->count();

        $trend = $kpi->critical_alerts > $yesterday ? 'up'
               : ($kpi->critical_alerts < $yesterday ? 'down' : 'stable');

        return response()->json([
            'success' => true,
            'data' => [
                'logs_today'           => (int) $kpi->logs_today,
                'active_users'         => (int) $kpi->active_users,
                'failed_actions'       => (int) $kpi->failed_actions,
                'critical_alerts'      => (int) $kpi->critical_alerts,
                'critical_alerts_trend'=> $trend,
            ],
        ]);
    }

    // ── API #36 + #38: GET audit log list (filter + search + phân trang) ─
    public function index(Request $request)
    {
        $page   = max(1, (int) $request->query('page', 1));
        $limit  = min(100, max(1, (int) $request->query('limit', 50)));
        $offset = ($page - 1) * $limit;

        $query = DB::table('audit_logs');

        // Filters
        if ($request->filled('event_type')) {
            $query->where('action', $request->event_type);
        }
        if ($request->filled('status')) {
            $statusMap = ['Success' => 'INFO', 'Failed' => 'FAILED'];
            if (isset($statusMap[$request->status])) {
                $query->where('flags', $statusMap[$request->status]);
            }
        }
        if ($request->filled('start_date')) {
            $query->whereDate('timestamp', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('timestamp', '<=', $request->end_date);
        }
        if ($request->filled('initiator_name')) {
            $query->where('initiator_name', $request->initiator_name);
        }
        // API #38: filter SYSTEM events
        if ($request->query('initiator_type') === 'SYSTEM') {
            $query->where('initiator_name', 'SYSTEM');
        }
        // Search (debounce là phía frontend)
        if ($request->filled('search') && strlen($request->search) >= 2) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('initiator_name', 'LIKE', $search)
                  ->orWhere('resource_id', 'LIKE', $search)
                  ->orWhere('action', 'LIKE', $search);
            });
        }

        $total      = $query->count();
        $totalPages = (int) ceil($total / $limit);

        $logs = $query->orderBy('timestamp', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->select([
                'id', 'timestamp', 'initiator_name', 'action',
                'resource_id', 'change_details_before',
                'change_details_after', 'flags',
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $logs,
            'meta'    => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => $totalPages,
                'has_prev'    => $page > 1,            // Gap 4
                'has_next'    => $page < $totalPages,  // Gap 4
            ],
        ]);
    }

    // ── API #37: GET single log detail ────────────────────────────
    public function show(string $log_id)
    {
        $log = DB::table('audit_logs')->where('id', $log_id)->first();

        if (!$log) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id'                    => $log->id,
                'timestamp'             => $log->timestamp,
                'initiator_name'        => $log->initiator_name,
                'action'                => $log->action,
                'resource_id'           => $log->resource_id,
                'change_details_before' => $log->change_details_before,
                'change_details_after'  => $log->change_details_after,
                'flags'                 => $log->flags,
            ],
        ]);
    }

    // ── API #39: POST export audit log ───────────────────────────
    public function export(Request $request)
    {
        // TẠM COMMENT KHI TEST - bỏ auth check
        // $user = $request->user();
 
        // Role check
        // if (!in_array($user->id_role, ['admin', 'compliance'])) {
        //     return response()->json([
        //         'success' => false,
        //         'error'   => ['code' => 403, 'message' => 'Access denied'],
        //     ], 403);
        // }
 
        $request->validate([
            'format'                => 'required|in:PDF,CSV',
            'filters'               => 'nullable|array',
            'filters.event_type'    => 'nullable|string',
            'filters.start_date'    => 'nullable|date',
            'filters.end_date'      => 'nullable|date',
            'filters.initiator_name'=> 'nullable|string',
        ]);
 
        $exportedAt = now()->toIso8601String();
 
        // TẠM COMMENT KHI TEST - cần $user->full_name
        // DB::table('audit_logs')->insert([
        //     'id'             => \Illuminate\Support\Str::uuid(),
        //     'initiator_name' => $user->full_name,
        //     'action'         => 'EXPORT_AUDIT_LOG',
        //     'resource_id'    => 'AUDIT_LOG',
        //     'timestamp'      => $exportedAt,
        //     'flags'          => 'INFO',
        // ]);
 
        // Đếm tổng records sẽ được export
        $query = DB::table('audit_logs');
        $filters = $request->input('filters', []);
        if (!empty($filters['event_type']))     $query->where('action', $filters['event_type']);
        if (!empty($filters['start_date']))     $query->whereDate('timestamp', '>=', $filters['start_date']);
        if (!empty($filters['end_date']))       $query->whereDate('timestamp', '<=', $filters['end_date']);
        if (!empty($filters['initiator_name'])) $query->where('initiator_name', $filters['initiator_name']);
 
        $totalRecords = $query->count();
        $format = $request->format;
        $exportUrl = "https://cdn.example.com/exports/audit_log_{$exportedAt}.{$format}";
 
        return response()->json([
            'success' => true,
            'data' => [
                'export_url'    => $exportUrl,
                'total_records' => $totalRecords,
                'format'        => $format,
                'exported_at'   => $exportedAt,
            ],
        ]);
    }
}