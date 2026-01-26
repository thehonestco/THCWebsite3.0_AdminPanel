<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Note;
use App\Models\NoteAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PublicLeadController extends Controller
{
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            /* =====================
             | 1️⃣ Validation
             ===================== */
            $validated = $request->validate([
                'name'  => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',

                'form_type' => 'nullable|string|max:100',
                'source'    => 'nullable|string|max:255',

                'opportunity_description' => 'required|string',

                'files.*' => 'nullable|file|max:10240', // 10MB
            ]);

            /* =====================
             | 2️⃣ FIND OR CREATE LEAD
             ===================== */
            $lead = Lead::where('poc_email', $validated['email'])->first();

            if (!$lead) {
                $lead = Lead::create([
                    'lead_code' => 'L' . str_pad((Lead::max('id') ?? 0) + 1, 4, '0', STR_PAD_LEFT),

                    'company_name' => $validated['name'],
                    'poc_name'     => $validated['name'],
                    'poc_email'    => $validated['email'],
                    'poc_phone'    => $validated['phone'],

                    'source' => $validated['source'] ?? 'Website',
                    'stage'  => 'Fresh',
                ]);
            }

            /* =====================
             | 3️⃣ Create Opportunity
             ===================== */
            $opportunity = Opportunity::create([
                'lead_id'     => $lead->id,
                'title'       => ucfirst($validated['form_type'] ?? 'Website Inquiry'),
                'description' => $validated['opportunity_description'],
                'status'      => 'intro-call',
            ]);

            /* =====================
             | 4️⃣ Create Note
             ===================== */
            $note = Note::create([
                'opportunity_id' => $opportunity->id,
                'user_name'      => $validated['name'],
                'title'          => 'Website Form Submission',
                'content'        => $validated['opportunity_description'],
                'note_status'    => 'public',
            ]);

            /* =====================
             | FILE UPLOAD (FIXED)
             ===================== */
            // support both: file & files[]
            $uploadedFiles = [];

            if ($request->hasFile('files')) {
                $uploadedFiles = $request->file('files');
            } elseif ($request->hasFile('file')) {
                $uploadedFiles = [$request->file('file')];
            }

            foreach ($uploadedFiles as $file) {

                if (!$file->isValid()) {
                    continue;
                }

                $path = $file->store('notes', 'public');

                NoteAttachment::create([
                    'note_id'   => $note->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Thank you! We will contact you soon.',
                'data' => [
                    'lead_id'        => $lead->id,
                    'opportunity_id' => $opportunity->id,
                ]
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
