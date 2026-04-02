<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\Signer;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SignerVerificationController extends Controller
{
    /**
     * GET /journal-entries/{id}/signer-verification
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $entry = JournalEntry::findOrFail($id);
        $signer = $entry->signers()->first();

        $complianceChecklist = $this->buildComplianceChecklist($signer);

        return response()->json([
            'success' => true,
            'message' => 'Fetched successfully',
            'data' => [
                'verification_status' => $this->getVerificationStatus($complianceChecklist),
                'current_step' => 1,
                'verification_method' => $entry->verification_method ?? 'physical_presence',
                'signer_info' => [
                    'full_legal_name' => $signer?->full_name ?? '',
                    'residential_address' => $signer?->address ?? '',
                ],
                'id_details' => [
                    'id_type' => $signer?->id_type ?? '',
                    'issuing_authority' => $signer?->id_issuing_authority ?? '',
                    'id_number' => $signer?->id_number ?? '',
                    'expiration_date' => $signer?->id_expiration_date ?? '',
                ],
                'notary_confirmation' => [
                    'identification_method' => $entry->identification_method ?? 'government_issued_id',
                    'notary_fee' => $entry->notarial_fee,
                    'document_description' => $entry->document_description ?? '',
                    'venue_state' => $entry->venue_state ?? '',
                    'venue_county' => $entry->venue_county ?? '',
                ],
                'compliance_checklist' => $complianceChecklist,
            ],
            'meta' => null,
            'errors' => null,
        ]);
    }

    /**
     * PUT /journal-entries/{id}/signer-verification
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $entry = JournalEntry::findOrFail($id);

        if ($entry->status === 'locked') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update a locked entry.',
                'errors' => null,
            ], 403);
        }

        $step = $request->input('step');

        DB::beginTransaction();
        try {
            match ($step) {
                1 => $this->updateStep1($request, $entry),
                2 => $this->updateStep2($request, $entry),
                3 => $this->updateStep3($request, $entry),
                default => throw new \InvalidArgumentException('Invalid step.'),
            };

            $this->writeAuditLog($entry->id, 'VERIFICATION_UPDATED', "Step {$step} updated");

            DB::commit();

            $signer = $entry->signers()->first();
            $checklist = $this->buildComplianceChecklist($signer);

            return response()->json([
                'success' => true,
                'message' => 'Step updated successfully',
                'data' => [
                    'status' => $entry->fresh()->status,
                    'next_step' => $step + 1,
                    'compliance_checklist' => $checklist,
                ],
                'meta' => null,
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /journal-entries/{id}/signer-verification/compliance
     * @param string $id
     * @return JsonResponse
     */
    public function compliance(string $id): JsonResponse
    {
        $entry = JournalEntry::findOrFail($id);
        $signer = $entry->signers()->first();
        $checklist = $this->buildComplianceChecklist($signer);

        $canProceed = collect($checklist)->every(fn($item) => $item['status'] === 'valid');

        return response()->json([
            'success' => true,
            'data' => [
                'can_proceed' => $canProceed,
                'compliance_checklist' => $checklist,
            ],
            'meta' => null,
            'errors' => null,
        ]);
    }

    /**
     * POST /journal-entries/{id}/finalize
     * @param string $id
     * @return JsonResponse
     */
    public function finalize(string $id): JsonResponse
    {
        $entry = JournalEntry::findOrFail($id);
        $signer = $entry->signers()->first();
        $checklist = $this->buildComplianceChecklist($signer);

        // Kiểm tra tất cả checklist phải valid
        $invalidItems = collect($checklist)
            ->filter(fn($item) => $item['status'] !== 'valid')
            ->keys()
            ->toArray();

        if (!empty($invalidItems)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot finalize: compliance checklist has invalid items.',
                'errors' => ['compliance' => $invalidItems],
            ], 422);
        }

        $entry->update(['status' => 'locked']);
        $this->writeAuditLog($entry->id, 'ENTRY_FINALIZED', 'Entry locked');

        return response()->json([
            'success' => true,
            'message' => 'Entry finalized and locked',
            'data' => [
                'id' => $entry->id,
                'status' => 'locked',
                'locked_at' => now()->toIso8601String(),
            ],
            'meta' => null,
            'errors' => null,
        ]);
    }

    // ─── Private Helpers ───────────────────────────────────────────

    private function updateStep1(Request $request, JournalEntry $entry): void
    {
        $validated = $request->validate([
            'full_legal_name' => 'required|string|max:255',
            'residential_address' => 'nullable|string|max:500',
        ]);

        $signer = $entry->signers()->firstOrNew(['journal_entry_id' => $entry->id]);
        $signer->full_name = trim($validated['full_legal_name']);
        $signer->address = $validated['residential_address'] ?? null;
        $signer->save();
    }

    private function updateStep2(Request $request, JournalEntry $entry): void
    {
        $validated = $request->validate([
            'id_type' => 'required|in:drivers_license,passport,state_id,military_id',
            'issuing_authority' => 'nullable|string|max:255',
            'id_number' => 'required|string|max:100',
            'expiration_date' => 'required|date|after:today',
            'customer_notes' => 'nullable|string',
        ]);

        $signer = $entry->signers()->firstOrNew(['journal_entry_id' => $entry->id]);
        $signer->fill([
            'id_type' => $validated['id_type'],
            'id_issuing_authority' => $validated['issuing_authority'] ?? null,
            'id_number' => $validated['id_number'],
            'id_expiration_date' => $validated['expiration_date'],
            'customer_notes' => $validated['customer_notes'] ?? null,
        ])->save();
    }

    private function updateStep3(Request $request, JournalEntry $entry): void
    {
        $validated = $request->validate([
            'identification_method' => 'required|string',
            'notary_fee' => 'required|numeric|min:0',
            'document_description' => 'nullable|string',
            'venue_state' => 'required|string|max:50',
            'venue_county' => 'required|string|max:100',
            'verification_method' => 'required|in:physical_presence,RON',
        ]);

        $entry->update([
            'identification_method' => $validated['identification_method'],
            'notarial_fee' => $validated['notary_fee'],
            'document_description' => $validated['document_description'] ?? null,
            'venue_state' => $validated['venue_state'],
            'venue_county' => $validated['venue_county'],
            'verification_method' => $validated['verification_method'],
            'status' => 'pending_finalize',
        ]);
    }

    private function buildComplianceChecklist(?Signer $signer): array
    {
        return [
            'id_type_selected' => [
                'status' => $signer?->id_type ? 'valid' : 'missing',
                'message' => $signer?->id_type ? 'ID type is selected' : 'ID type not selected',
            ],
            'name_matching' => [
                'status' => $signer?->full_name ? 'valid' : 'missing',
                'message' => $signer?->full_name ? 'Name is entered' : 'Full legal name not entered',
            ],
            'expiration_status' => [
                'status' => $signer?->id_expiration_date
                    ? (now()->lt($signer->id_expiration_date) ? 'valid' : 'invalid')
                    : 'missing',
                'message' => $signer?->id_expiration_date
                    ? (now()->lt($signer->id_expiration_date) ? 'ID is valid' : 'ID has expired')
                    : 'Expiration date not entered',
            ],
        ];
    }

    private function getVerificationStatus(array $checklist): string
    {
        foreach ($checklist as $item) {
            if ($item['status'] === 'invalid') return 'invalid';
            if ($item['status'] === 'missing') return 'incomplete';
        }
        return 'valid';
    }

    private function writeAuditLog(string $resourceId, string $action, string $detail): void
    {
        AuditLog::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $resourceId,
            'action' => $action,
            'initiator_name' => auth()->user()?->full_name ?? 'System',
            'change_details_after' => $detail,
        ]);
    }
}