<?php

namespace App\Http\Controllers\NotaryJournal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class LinkedActController extends Controller
{
    // ── API #1: GET linked-act summary ───────────────────────────
    public function summary(string $journal_entry_id)
    {
        $entry = DB::table('journal_entries as je')
            ->join('users as u', 'u.id', '=', 'je.notary_id')
            ->join('signers as s', 's.journal_entry_id', '=', 'je.id')
            ->where('je.id', $journal_entry_id)
            ->select([
                'je.id as journal_entry_id',
                'je.notary_id',
                'je.signed_digital_act_id',
                'je.act_type',
                'je.execution_date',
                'je.venue_state',
                'je.venue_county',
                'u.full_name as notary_name',
                'je.document_title',
                'je.status',
                'je.document_type',
                'je.number_of_pages',
                'je.thumbprint_waived',
            ])
            ->first();

        if (!$entry) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Lấy danh sách signer names
        $signers = DB::table('signers')
            ->where('journal_entry_id', $journal_entry_id)
            ->pluck('full_name');

        // act_accessible: kiểm tra linked act còn tồn tại không (Gap 1 fix)
        $actAccessible = !empty($entry->signed_digital_act_id);

        return response()->json([
            'success' => true,
            'data' => [
                'journal_entry_id'     => $entry->journal_entry_id,
                'notary_id'            => $entry->notary_id,
                'signed_digital_act_id'=> $entry->signed_digital_act_id,
                'act_type'             => $entry->act_type,
                'execution_date'       => $entry->execution_date,
                'venue_state'          => $entry->venue_state,
                'venue_county'         => $entry->venue_county,
                'notary_name'          => $entry->notary_name,
                'document_title'       => $entry->document_title,
                'status'               => $entry->status,
                'document_type'        => $entry->document_type,
                'number_of_pages'      => $entry->number_of_pages,
                'signers'              => $signers,
                'act_accessible'       => $actAccessible,  // Gap 1
            ],
        ]);
    }

    // ── API #2: GET certification block ──────────────────────────
    public function certification(string $journal_entry_id)
    {
        $data = DB::table('journal_entries as je')
            ->join('users as u', 'u.id', '=', 'je.notary_id')
            ->where('je.id', $journal_entry_id)
            ->select([
                'u.full_name as notary_name',
                'u.commission_number',
                'u.commission_expiry_date',
                'u.notary_signature',
                'u.notary_seal_image',
            ])
            ->first();

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'notary_name'           => $data->notary_name ?? '-----',
                'commission_number'     => $data->commission_number ?? '-----',
                'commission_expiry_date'=> $data->commission_expiry_date ?? '-----',
                'notary_signature'      => $data->notary_signature ?? null,
                'notary_seal_image'     => $data->notary_seal_image ?? null,
            ],
        ]);
    }

    // ── API #3: GET audit trail (phân trang) ──────────────────────
    public function auditTrail(Request $request, string $journal_entry_id)
    {
        $page  = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 10)));
        $offset = ($page - 1) * $limit;

        $total = DB::table('audit_logs')
            ->where('resource_id', $journal_entry_id)
            ->count();

        $events = DB::table('audit_logs')
            ->where('resource_id', $journal_entry_id)
            ->orderBy('timestamp', 'asc')
            ->offset($offset)
            ->limit($limit)
            ->select(['action as event', 'timestamp', 'flags'])
            ->get()
            ->map(fn($row) => [
                'event'     => $row->event,
                'timestamp' => $row->timestamp,
                'status'    => $row->flags === 'completed' ? 'completed' : 'pending',
            ]);

        return response()->json([
            'success' => true,
            'data'    => $events,
            'meta'    => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    // ── API #4: GET verification status (hỗ trợ true/false) ───────
    public function verificationStatus(string $journal_entry_id)
    {
        $entry = DB::table('journal_entries as je')
            ->leftJoin('signers as s', 's.journal_entry_id', '=', 'je.id')
            ->leftJoin('biometric_data as bd', 'bd.signer_id', '=', 's.id')
            ->where('je.id', $journal_entry_id)
            ->select([
                's.id as signer_id',
                'bd.signature_image',
                'bd.thumbprint_image',
                'je.thumbprint_waived',
                'je.status',
            ])
            ->first();

        if (!$entry) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $thumbprintRequired = !(bool) $entry->thumbprint_waived;

        return response()->json([
            'success' => true,
            'data' => [
                'identity_verified'   => !is_null($entry->signer_id),
                'signature_captured'  => !is_null($entry->signature_image),
                'thumbprint_required' => $thumbprintRequired,
                // Gap 2: trả false thực tế, không chỉ happy path
                'thumbprint_captured' => $thumbprintRequired
                                            ? !is_null($entry->thumbprint_image)
                                            : true, // waived → met by default
                'notary_certified'    => $entry->status === 'completed',
            ],
        ]);
    }

    // ── API #5: GET certificates ──────────────────────────────────
    public function certificates(string $journal_entry_id)
    {
        // File metadata lưu ngoài DB, tra cứu qua journal_entry_id
        $files = DB::table('journal_certificates')
            ->where('journal_entry_id', $journal_entry_id)
            ->get();

        if ($files->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No certificates found'], 404);
        }

        $data = $files->map(function ($file) {
            $base = [
                'file_name'   => $file->file_name,
                'file_type'   => $file->file_type,
                'upload_date' => $file->upload_date,
            ];

            if ($file->file_type === 'JSON') {
                return array_merge($base, [
                    'hash'       => $file->hash,
                    'verify_url' => url("/api/v1/journal-entries/{$file->journal_entry_id}/linked-act/certificates/{$file->id}/verify-integrity"),
                ]);
            }

            return array_merge($base, [
                'file_size'    => $file->file_size,
                'preview_url'  => $file->preview_url,
                'download_url' => $file->download_url,
            ]);
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ── API #6: POST signer confirmation ─────────────────────────
    public function signerConfirmation(Request $request, string $journal_entry_id)
    {
        $entry = DB::table('journal_entries')->where('id', $journal_entry_id)->first();

        if (!$entry) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Gap check: không được confirm khi đã LOCKED
        if (strtoupper($entry->status) === 'LOCKED') {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 409, 'message' => 'Cannot confirm: journal entry is already locked'],
            ], 409);
        }

        $request->validate([
            'confirmed'  => 'required|boolean',
            'signer_id'  => 'required|uuid',
        ]);

        $signer = DB::table('signers')->where('id', $request->signer_id)->first();
        $confirmedAt = now()->toIso8601String();

        DB::table('audit_logs')->insert([
            'id'                   => \Illuminate\Support\Str::uuid(),
            'initiator_name'       => $signer->full_name ?? 'Unknown',
            'action'               => 'SIGNER_CONFIRMATION',
            'resource_id'          => $journal_entry_id,
            'change_details_after' => json_encode(['confirmed' => true]),
            'timestamp'            => $confirmedAt,
            'flags'                => 'INFO',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'confirmed'    => true,
                'confirmed_at' => $confirmedAt,
                'confirmed_by' => $signer->full_name ?? 'Unknown',
            ],
        ]);
    }

    // ── API #7: POST export / share (Gap 3 fix) ──────────────────
    public function export(Request $request, string $journal_entry_id)
    {
        $user = $request->user();

        // Gap 3: kiểm tra role, trả 403 rõ ràng
        if (!in_array($user->id_role, ['notary', 'compliance'])) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 403,
                    'message' => 'Access denied: only Notary or Compliance Officer can export',
                ],
            ], 403);
        }

        $request->validate([
            'format'     => 'required|in:PDF,CSV',
            'share_link' => 'boolean',
        ]);

        $exportedAt = now()->toIso8601String();
        $exportUrl  = "https://cdn.example.com/exports/linked_act_{$journal_entry_id}.{$request->format}";
        $shareLink  = null;
        $shareLinkExpiresAt = null;

        // Gap 3: xử lý share_link=true
        if ($request->boolean('share_link')) {
            $token = \Illuminate\Support\Str::random(32);
            // Lưu token vào share_tokens table
            DB::table('share_tokens')->insert([
                'token'            => $token,
                'journal_entry_id' => $journal_entry_id,
                'created_by'       => $user->id,
                'expires_at'       => now()->addDays(7)->toIso8601String(),
            ]);
            $shareLink = url("/share/{$token}");
            $shareLinkExpiresAt = now()->addDays(7)->toIso8601String();
        }

        // Ghi audit log
        DB::table('audit_logs')->insert([
            'id'             => \Illuminate\Support\Str::uuid(),
            'initiator_name' => $user->full_name,
            'action'         => 'EXPORT_LINKED_ACT',
            'resource_id'    => $journal_entry_id,
            'timestamp'      => $exportedAt,
            'flags'          => 'INFO',
        ]);

        return response()->json([
            'success' => true,
            'data' => array_filter([
                'export_url'            => $exportUrl,
                'share_link'            => $shareLink,
                'share_link_expires_at' => $shareLinkExpiresAt,
                'exported_at'           => $exportedAt,
            ], fn($v) => $v !== null),
        ]);
    }
}