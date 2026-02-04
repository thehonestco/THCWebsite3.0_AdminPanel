<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\NoteAttachment;
use App\Models\Opportunity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NoteController extends Controller
{
    /**
     * GET /api/opportunities/{opportunityId}/notes
     */
    public function index($opportunityId)
    {
        $opportunity = Opportunity::find($opportunityId);

        if (!$opportunity) {
            return response()->json([
                'success' => false,
                'message' => 'Opportunity not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $opportunity->notes()
                ->with('attachments')
                ->latest()
                ->get()
        ]);
    }

    /**
     * POST /api/opportunities/{opportunityId}/notes
     */
    public function store(Request $request, $opportunityId)
    {
        $opportunity = Opportunity::find($opportunityId);

        if (!$opportunity) {
            return response()->json([
                'success' => false,
                'message' => 'Opportunity not found'
            ], 404);
        }

        $validated = $request->validate([
            'content' => 'required|string|min:1',
            'opportunity_stage' => 'required|string|max:100',

            'attachments'   => 'nullable|array',
            'attachments.*' => 'file|max:10240',
        ]);

        DB::beginTransaction();

        try {
            /* 1️⃣ Create Note */
            $note = $opportunity->notes()->create([
                'comment' => $validated['content'], // ✅ FIXED
                'created_by' => auth()->check()
                    ? auth()->user()->name
                    : 'System', // ✅ FIXED
            ]);

            /* 2️⃣ Update Opportunity Stage */
            $opportunity->update([
                'stage' => $validated['opportunity_stage'],
            ]);

            /* 3️⃣ Attachments */
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if (!$file->isValid()) {
                        continue;
                    }

                    $path = $file->store('notes', 'public');

                    $note->attachments()->create([
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Note created successfully',
                'data' => $note->load('attachments')
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }


    /**
     * GET /api/notes/{id}
     */
    public function show($id)
    {
        $note = Note::with('attachments')->find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $note
        ]);
    }

    /**
     * PUT /api/notes/{id}
     * Update ONLY comment (files untouched)
     */
    public function update(Request $request, $id)
    {
        $note = Note::find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found'
            ], 404);
        }

        $validated = $request->validate([
            'comment' => 'required|string',
        ]);

        $note->update([
            'comment' => $validated['comment'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Comment updated successfully',
            'data' => $note->load('attachments')
        ]);
    }

    /**
     * DELETE /api/notes/{id}
     * Deletes note + attachments
     */
    public function destroy($id)
    {
        $note = Note::with('attachments')->find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found'
            ], 404);
        }

        // delete files
        foreach ($note->attachments as $attachment) {
            \Storage::disk('public')->delete($attachment->file_path);
            $attachment->delete();
        }

        $note->delete();

        return response()->json([
            'success' => true,
            'message' => 'Note deleted successfully'
        ]);
    }
}
