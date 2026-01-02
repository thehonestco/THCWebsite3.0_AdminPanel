<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\Opportunity;
use Illuminate\Http\Request;

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
            'data' => $opportunity->notes()->latest()->get()
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
            'user_name'   => 'required|string|max:255',
            'title'       => 'nullable|string|max:255',
            'content'     => 'required|string',
            'note_status' => 'nullable|string|max:100',
        ]);

        $note = $opportunity->notes()->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Note created successfully',
            'data' => $note
        ], 201);
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
            'user_name'   => 'sometimes|required|string|max:255',
            'title'       => 'nullable|string|max:255',
            'content'     => 'sometimes|required|string',
            'note_status' => 'nullable|string|max:100',
        ]);

        $note->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Note updated successfully',
            'data' => $note
        ]);
    }

    /**
     * DELETE /api/notes/{id}
     */
    public function destroy($id)
    {
        $note = Note::find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found'
            ], 404);
        }

        $note->delete();

        return response()->json([
            'success' => true,
            'message' => 'Note deleted successfully'
        ]);
    }
}
