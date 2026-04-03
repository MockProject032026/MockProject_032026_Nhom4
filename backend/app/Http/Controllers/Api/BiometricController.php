<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BiometricData;
use App\Models\JournalEntry;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BiometricController extends Controller
{
    /**
     * GET /journal-entries/{id}/biometrics
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $entry = JournalEntry::with('signers.biometricData')->findOrFail($id);
        $signer = $entry->signers()->first();
        $bio = $signer?->biometricData;

        return response()->json([
            'success' => true,
            'message' => 'Fetched successfully',
            'data' => [
                'id'                   => $bio?->id,
                'signer_id' => $signer?->id,
                'signature_image' => $bio?->signature_image,
                'thumbprint_image' => $bio?->thumbprint_image,
                'biometric_match_hash' => $bio?->biometric_match_hash,
                'metadata' => [
                    'capture_device_id' => $bio?->capture_device_id,
                    'capture_location' => $bio?->capture_location,
                ],
                'thumbprint_waived' => (bool) $entry->thumbprint_waived,
            ],
            'meta' => null,
            'errors' => null,
        ]);
    }

    /**
     * POST /journal-entries/{id}/biometrics
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $entry = JournalEntry::find($id);
        if (!$entry) {
            return response()->json(['success' => false, 'message' => 'Journal entry not found.'], 404);
        }
        if ($entry->status === 'locked') {
            return response()->json(['success' => false, 'message' => 'Cannot update a locked entry.'], 403);
        }

        $validated = $request->validate([
            'signer_id'         => 'required|string',
            'signature_image'   => 'required|string',
            'thumbprint_image'  => 'nullable|string',
            'capture_device_id' => 'nullable|string|max:100',
            'capture_location'  => 'nullable|string|max:255',
            'thumbprint_waived' => 'boolean',
        ]);

        if (!($validated['thumbprint_waived'] ?? false) && empty($validated['thumbprint_image'])) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => ['thumbprint_image' => ['Thumbprint is required when not waived.']],
            ], 422);
        }

        $bio = BiometricData::updateOrCreate(
            ['signer_id' => $validated['signer_id']],
            [
                'id'                   => (string) Str::uuid(),
                'signature_image'      => $validated['signature_image'],
                'thumbprint_image'     => $validated['thumbprint_image'] ?? null,
                'biometric_match_hash' => hash('sha256', $validated['signature_image']),
                'capture_device_id'    => $validated['capture_device_id'] ?? null,
                'capture_location'     => $validated['capture_location'] ?? null,
            ]
        );

        AuditLog::create([
            'id'                   => (string) Str::uuid(),
            'resource_id'          => $id,
            'action'               => 'BIOMETRIC_SAVED',
            'initiator_name'       => 'System',
            'change_details_after' => 'Biometric data saved',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Biometric data saved successfully',
            'data'    => [
                'id'                   => $bio->id,
                'signature_image'      => $bio->signature_image,
                'thumbprint_image'     => $bio->thumbprint_image,
                'biometric_match_hash' => $bio->biometric_match_hash,
                'captured_at'          => now()->toIso8601String(),
            ],
            'meta'   => null,
            'errors' => null,
        ], 201);
    }

    /**
     * GET /journal-entries/{id}/biometrics/metadata
     * @param string $id
     * @return JsonResponse
     */
    public function metadata(string $id): JsonResponse
    {
        $entry = JournalEntry::with('signers.biometricData')->findOrFail($id);
        $bio = $entry->signers()->first()?->biometricData;

        return response()->json([
            'success' => true,
            'data' => [
                'capture_device_id' => $bio?->capture_device_id,
                'capture_location' => $bio ? $this->maskLocation($bio->capture_location) : null,
                'ip_address' => request()->ip(),
            ],
            'meta' => null,
            'errors' => null,
        ]);
    }

    // ─── Private Helpers ───────────────────────────────────────────

    private function maskLocation(?string $location): ?string
    {
        if (!$location) return null;
        $parts = explode(',', $location);
        return trim($parts[0]) . ' (masked)';
    }

    public function notaryBiometrics(string $notaryId): JsonResponse
    {
        $bio = \App\Models\NotaryBiometric::where('notary_id', $notaryId)->first();

        if (!$bio) {
            return response()->json([
                'success' => false,
                'message' => 'Notary biometrics not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'notary_id'              => $bio->notary_id,
                'digital_signature_path' => $bio->digital_signature_path,
                'thumbprint_scan_path'   => $bio->thumbprint_scan_path,
                'updated_at'             => $bio->updated_at,
            ],
            'meta'   => null,
            'errors' => null,
        ]);
    }

    public function destroy(string $id, string $biometricId): JsonResponse
    {
        $entry = JournalEntry::find($id);
        if (!$entry) {
            return response()->json(['success' => false, 'message' => 'Journal entry not found.'], 404);
        }
        if ($entry->status === 'locked') {
            return response()->json(['success' => false, 'message' => 'Cannot delete from a locked entry.'], 403);
        }

        $bio = BiometricData::find($biometricId);
        if (!$bio) {
            return response()->json(['success' => false, 'message' => 'Biometric data not found.'], 404);
        }

        $bio->delete();

        return response()->json([
            'success' => true,
            'message' => 'Biometric data deleted successfully',
            'meta'    => null,
            'errors'  => null,
        ]);
    }

    public function storeNotaryBiometrics(Request $request, string $notaryId): JsonResponse
    {
        $validated = $request->validate([
            'digital_signature_path' => 'required|string',
            'thumbprint_scan_path'   => 'nullable|string',
        ]);

        $bio = \App\Models\NotaryBiometric::updateOrCreate(
            ['notary_id' => $notaryId],
            [
                'digital_signature_path' => $validated['digital_signature_path'],
                'thumbprint_scan_path'   => $validated['thumbprint_scan_path'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Notary biometrics saved successfully',
            'data'    => [
                'id'                     => $bio->id,
                'notary_id'              => $bio->notary_id,
                'digital_signature_path' => $bio->digital_signature_path,
                'thumbprint_scan_path'   => $bio->thumbprint_scan_path,
            ],
            'meta'   => null,
            'errors' => null,
        ], 201);
    }

    public function destroyNotaryBiometrics(string $notaryId): JsonResponse
    {
        $bio = \App\Models\NotaryBiometric::where('notary_id', $notaryId)->first();

        if (!$bio) {
            return response()->json([
                'success' => false,
                'message' => 'Notary biometrics not found.',
            ], 404);
        }

        $bio->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notary biometrics deleted successfully',
            'meta'    => null,
            'errors'  => null,
        ]);
    }

    public function consentStatement(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'text'           => 'By providing my signature and thumbprint, I hereby consent to the notarization of the described document.',
                'version'        => 'v1.0',
                'effective_date' => '2026-01-01',
            ],
            'meta'   => null,
            'errors' => null,
        ]);
    }

}