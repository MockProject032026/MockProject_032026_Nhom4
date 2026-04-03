<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Throwable;

class EntryDetailController extends Controller
{
    /**
     * =========================================================
     * Chức năng: Lấy chi tiết journal entry
     * Màn hình sử dụng: SC_004 - Journal Entry Details
     * Method: GET
     * Endpoint: /api/v1/journal-entries/{id}
     *
     * Mô tả:
     * - Lấy thông tin chi tiết của 1 journal entry theo id
     * - Lấy thông tin người tạo record (notary)
     * - Lấy signer đầu tiên của journal entry
     * - Lấy thông tin biometric hash của signer
     * - Trả dữ liệu đúng format frontend mong muốn
     * =========================================================
     */
    public function show(string $id): JsonResponse
    {
        try {
            /**
             * Lấy journal entry kèm signer và biometric.
             * Không eager load "notary" vì model JournalEntry hiện chưa có relation đó.
             */
            $journal = JournalEntry::query()
                ->with([
                    'signers.biometricData'
                ])
                ->find($id);

            /**
             * Nếu không tìm thấy journal entry thì trả về 404.
             */
            if (!$journal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Journal entry not found.',
                    'data' => null,
                    'errors' => [
                        'id' => ['Journal entry not found.']
                    ]
                ], 404);
            }

            /**
             * Lấy user/notary tạo journal từ notary_id.
             * Làm thủ công bằng model User vì bạn yêu cầu giữ nguyên model hiện tại.
             */
            $notary = null;
            if (!empty($journal->notary_id)) {
                $notary = User::query()->find($journal->notary_id);
            }

            /**
             * Lấy signer đầu tiên để hiển thị ở màn detail.
             */
            $signer = $journal->signers->first();

            /**
             * Vì Signer model đang dùng hasOne(biometricData),
             * nên lấy trực tiếp object biometricData, không dùng first().
             */
            $biometric = $signer?->biometricData;

            /**
             * Format ngày giờ an toàn.
             */
            $signedOn = $this->formatDateTime($journal->execution_date);
            $idExpiry = $this->formatDate($signer?->id_expiration_date);

            return response()->json([
                'success' => true,
                'message' => 'Fetched successfully.',
                'data' => [
                    'id' => $journal->id,
                    'journal_no' => $this->buildJournalNo($journal->id, $journal->execution_date),
                    'status' => strtoupper((string) $journal->status),

                    'created_by' => [
                        'name' => $notary?->full_name,
                        'role' => 'Notary Public',
                    ],

                    'signed_on' => $signedOn,

                    'entry_data' => [
                        'date_time' => $signedOn,
                        'act_type' => $journal->act_type ?? null,
                        'venue_state' => $this->buildVenueState(
                            $journal->venue_county ?? null,
                            $journal->venue_state ?? null
                        ),
                        'notarial_fee' => (float) ($journal->notarial_fee ?? 0),
                        'currency' => 'USD',
                        'source' => [
                            'date_time' => 'AUTO_POPULATED',
                            'act_type' => 'MANUAL_ENTRY',
                            'venue_state' => 'AUTO_POPULATED_GPS',
                            'notarial_fee' => 'MANUAL_ENTRY',
                        ],
                    ],

                    'signer' => [
                        'name' => $signer?->full_name,
                        'id_type' => $signer?->id_type,
                        'id_expiry' => $idExpiry,
                        'verification_status' => $this->resolveVerificationStatus($journal->verification_method),
                        'verification_method' => $this->resolveVerificationMethod($journal->verification_method),
                    ],

                    'biometric' => [
                        'match_hash' => $biometric?->biometric_match_hash,
                    ],
                ],
                'errors' => null,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
                'data' => null,
                'errors' => [
                    'exception' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * =========================================================
     * Chức năng: Tạo mã journal number để hiển thị
     *
     * Mô tả:
     * - Vì DB hiện chưa có cột journal_no riêng
     * - Tạm build theo format: JE-{YEAR}-{4 ký tự đầu của ID}
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

    /**
     * =========================================================
     * Chức năng: Gộp county và state thành 1 chuỗi
     *
     * Ví dụ:
     * - County 1 + California => County 1, California
     * =========================================================
     */
    private function buildVenueState(?string $county, ?string $state): ?string
    {
        $value = trim(($county ?? '') . ', ' . ($state ?? ''), ', ');
        return $value !== '' ? $value : null;
    }

    /**
     * =========================================================
     * Chức năng: Format datetime về chuẩn ISO string
     * =========================================================
     */
    private function formatDateTime($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * =========================================================
     * Chức năng: Format date về yyyy-mm-dd
     * =========================================================
     */
    private function formatDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    /**
     * =========================================================
     * Chức năng: Chuẩn hóa verification status cho frontend
     * =========================================================
     */
    private function resolveVerificationStatus(?string $verificationMethod): string
    {
        $method = strtolower((string) $verificationMethod);

        return match ($method) {
            'knowledge-based auth',
            'kba',
            'passport',
            'driver_license',
            'government id' => 'VERIFIED',
            default => 'PENDING',
        };
    }

    /**
     * =========================================================
     * Chức năng: Chuẩn hóa tên verification method dễ đọc hơn
     * =========================================================
     */
    private function resolveVerificationMethod(?string $verificationMethod): ?string
    {
        $method = strtolower((string) $verificationMethod);

        return match ($method) {
            'kba' => 'Knowledge-Based Auth',
            'driver_license' => 'Driver License',
            default => $verificationMethod,
        };
    }
}
