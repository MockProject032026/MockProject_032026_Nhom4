<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    /**
     * GET /journal-entries/{id}/attachments
     * @param string $id
     * @return JsonResponse
     */
    public function index(string $id): JsonResponse
    {
        JournalEntry::findOrFail($id);
        $attachments = Attachment::where('journal_entry_id', $id)->get();

        return response()->json([
            'success' => true,
            'data' => $attachments->map(fn($a) => [
                'id' => $a->id,
                'file_name' => $a->file_name,
                'file_type' => $a->file_type,
                'file_path' => Storage::url($a->file_path),
                'uploaded_at' => $a->uploaded_at,
            ]),
            'meta' => null,
            'errors' => null,
        ]);
    }

    /**
     * POST /journal-entries/{id}/attachments
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $entry = JournalEntry::findOrFail($id);

        if ($entry->status === 'locked') {
            return response()->json(['success' => false, 'message' => 'Cannot upload to a locked entry.'], 403);
        }

        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB
        ]);

        $uploaded = [];
        foreach ($request->file('files') as $file) {
            $path = $file->store("attachments/{$id}/" . now()->format('Y/m'));
            $attachment = Attachment::create([
                'journal_entry_id' => $id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getClientOriginalExtension(),
            ]);
            $uploaded[] = [
                'id' => $attachment->id,
                'file_name' => $attachment->file_name,
                'file_type' => $attachment->file_type,
                'file_path' => Storage::url($path),
                'uploaded_at' => $attachment->uploaded_at,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Files uploaded successfully',
            'data' => $uploaded,
            'meta' => null,
            'errors' => null,
        ], 201);
    }

    /**
     * DELETE /journal-entries/{id}/attachments/{attachmentId}
     * @param string $id
     * @param string $attachmentId
     * @return JsonResponse
     */
    public function destroy(string $id, string $attachmentId): JsonResponse
    {
        $entry = JournalEntry::findOrFail($id);

        if ($entry->status === 'locked') {
            return response()->json(['success' => false, 'message' => 'Cannot delete from a locked entry.'], 403);
        }

        $attachment = Attachment::where('journal_entry_id', $id)
            ->findOrFail($attachmentId);

        Storage::delete($attachment->file_path);
        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attachment deleted successfully',
            'meta' => null,
            'errors' => null,
        ]);
    }
}