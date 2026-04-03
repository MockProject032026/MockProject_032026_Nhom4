<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\JournalService;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

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
            'page',
            'limit',
            'search',
            'venue_state',
            'status',
            'risk_flag',
            'notary_id',
            'start_date',
            'end_date',
            'act_type',
            'is_holiday',
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


    // TranCongAnh - Journal export
    /**
     * =========================================================
     * Chức năng: Tải xuống PDF của 1 journal entry
     * Màn hình: SC_009 - Journal Export & Retention
     * Method: GET
     * Endpoint: /api/v1/journals/{id}/export-pdf
     *
     * Mô tả:
     * - Kiểm tra journal có tồn tại hay không
     * - Tạo file export giả lập để test
     * - Trả về link tải file cho frontend
     * =========================================================
     */
    public function exportPdf(string $id): JsonResponse
    {
        try {
            $journal = JournalEntry::query()->find($id);

            if (!$journal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Journal not found.',
                    'data' => null,
                    'meta' => null,
                    'errors' => [
                        'id' => ['Journal not found.']
                    ]
                ], 404);
            }

            $journalNo = $this->buildJournalNo($journal->id, $journal->execution_date);
            $fileName = "JournalEntry_{$journalNo}.pdf";
            $filePath = "exports/journals/{$fileName}";

            // Mock nội dung file PDF để test API trước
            // Hiện đang ghi file text với đuôi .pdf cho đúng flow tài liệu
            $content = implode(PHP_EOL, [
                'Journal Entry Export',
                "Journal ID: {$journal->id}",
                "Journal No: {$journalNo}",
                "Status: {$journal->status}",
                "Execution Date: {$journal->execution_date}",
                "Venue State: {$journal->venue_state}",
                "Venue County: {$journal->venue_county}",
                "Generated At: " . now()->toDateTimeString(),
                'Watermark: Official System Extract',
            ]);

            Storage::disk('public')->put($filePath, $content);

            return response()->json([
                'success' => true,
                'message' => 'PDF generated successfully.',
                'data' => [
                    'download_url' => asset("storage/{$filePath}"),
                    'file_name' => $fileName,
                    'generated_at' => Carbon::now()->format('Y-m-d\TH:i:s\Z'),
                    'watermark' => 'Official System Extract',
                ],
                'meta' => null,
                'errors' => null,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'PDF generation failed.',
                'data' => null,
                'meta' => null,
                'errors' => [
                    'exception' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * =========================================================
     * Chức năng: Tạo job export journal hàng loạt
     * Màn hình: SC_009 - Journal Export & Retention
     * Method: POST
     * Endpoint: /api/v1/journals/export
     *
     * Mô tả:
     * - Nhận filter từ frontend
     * - Đếm số journal phù hợp
     * - Tạo job export giả lập
     * - Trả về job_id để frontend polling tiếp
     * =========================================================
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'from_date' => ['nullable', 'date'],
                'to_date' => ['nullable', 'date'],
                'notary_id' => ['nullable', 'string'],
                'state' => ['nullable', 'string'],
                'format' => ['required', 'in:csv,pdf'],
            ]);

            $query = JournalEntry::query();

            if (!empty($validated['from_date'])) {
                $query->whereDate('execution_date', '>=', $validated['from_date']);
            }

            if (!empty($validated['to_date'])) {
                $query->whereDate('execution_date', '<=', $validated['to_date']);
            }

            if (!empty($validated['notary_id'])) {
                $query->where('notary_id', $validated['notary_id']);
            }

            if (!empty($validated['state'])) {
                $query->where('venue_state', $validated['state']);
            }

            $recordCount = $query->count();
            $jobId = 'EXP-' . now()->format('YmdHis') . '-001';

            return response()->json([
                'success' => true,
                'message' => 'Export job created successfully.',
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'PROCESSING',
                    'format' => $validated['format'],
                    'record_count' => $recordCount,
                    'is_async' => true,
                ],
                'meta' => null,
                'errors' => null,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export job creation failed.',
                'data' => null,
                'meta' => null,
                'errors' => [
                    'exception' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * =========================================================
     * Chức năng: Kiểm tra trạng thái export job
     * Màn hình: SC_009 - Journal Export & Retention
     * Method: GET
     * Endpoint: /api/v1/journals/export/{jobId}
     *
     * Mô tả:
     * - Nhận job_id từ frontend
     * - Trả trạng thái PROCESSING hoặc COMPLETED
     * - Nếu COMPLETED thì trả download_url
     *
     * Ghi chú:
     * - Đây là bản mock để test flow frontend
     * - Chưa dùng DB/table export_jobs
     * =========================================================
     */
    public function exportStatus(string $jobId): JsonResponse
    {
        try {
            // Mock: nếu jobId chứa chữ PROCESSING thì trả đang xử lý
            if (str_contains(strtoupper($jobId), 'PROCESSING')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Export job is still processing.',
                    'data' => [
                        'job_id' => $jobId,
                        'status' => 'PROCESSING',
                        'format' => 'csv',
                        'record_count' => 742,
                        'download_url' => null,
                    ],
                    'meta' => null,
                    'errors' => null,
                ]);
            }

            // Mock completed
            $fileName = "journal-export-{$jobId}.csv";
            $filePath = "exports/journals/{$fileName}";

            $content = "id,notary_id,status,execution_date" . PHP_EOL;
            $content .= "1,NOTARY001,completed,2026-03-01" . PHP_EOL;
            $content .= "2,NOTARY002,pending,2026-03-02" . PHP_EOL;

            Storage::disk('public')->put($filePath, $content);

            return response()->json([
                'success' => true,
                'message' => 'Export file is ready.',
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'COMPLETED',
                    'format' => 'csv',
                    'record_count' => 742,
                    'download_url' => asset("storage/{$filePath}"),
                    'generated_at' => Carbon::now()->format('Y-m-d\TH:i:s\Z'),
                ],
                'meta' => null,
                'errors' => null,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get export status.',
                'data' => null,
                'meta' => null,
                'errors' => [
                    'exception' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * =========================================================
     * Chức năng: Tạo journal number để hiển thị file export
     * =========================================================
     */
    private function buildJournalNo(string $id, $executionDate): string
    {
        $year = $executionDate
            ? Carbon::parse($executionDate)->format('Y')
            : now()->format('Y');

        $suffix = strtoupper(substr(str_replace('-', '', $id), 0, 4));

        return "JE-{$year}-{$suffix}";
    }
}
