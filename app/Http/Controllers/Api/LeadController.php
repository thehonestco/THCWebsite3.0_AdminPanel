<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadController extends Controller
{
    /**
     * GET /api/leads
     */
    public function index(Request $request)
    {
        $leads = Lead::with('opportunities.notes')
            ->where('is_converted', false)
            ->latest()
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $leads
        ]);
    }

    public function clients(Request $request)
    {
        $clients = Lead::with('opportunities.notes')
            ->where('is_converted', true)
            ->latest()
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $clients
        ]);
    }

    /**
     * POST /api/leads
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name'      => 'required|string|max:255',
            'company_website'   => 'nullable|url',
            'company_linkedin'  => 'nullable|url',
            'tagline'           => 'nullable|string|max:255',
            'tags'              => 'nullable|string',
            'city'              => 'nullable|string|max:100',
            'country'           => 'nullable|string|max:100',

            // POC
            'poc_name'          => 'required|string|max:255',
            'poc_email'         => 'nullable|email',
            'poc_phone'         => 'nullable|string|max:20',
            'poc_linkedin'      => 'nullable|url',

            'source'            => 'nullable|string|max:255',
            'stage'             => 'nullable|string|max:100',
        ]);

        // Auto lead code (L012)
        $lastId = Lead::max('id') + 1;
        $validated['lead_code'] = 'L' . str_pad($lastId, 3, '0', STR_PAD_LEFT);
        $validated['stage'] ??= 'Requirement';

        $lead = Lead::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Lead created successfully',
            'data' => $lead
        ], 201);
    }

    /**
     * GET /api/leads/{id}
     */
    public function show($id)
    {
        $lead = Lead::with('opportunities.notes')->find($id);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $lead
        ]);
    }

    /**
     * PUT /api/leads/{id}
     */
    public function update(Request $request, $id)
    {
        $lead = Lead::find($id);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found'
            ], 404);
        }

        $validated = $request->validate([
            'company_name'     => 'sometimes|required|string|max:255',
            'company_website'  => 'nullable|url',
            'company_linkedin' => 'nullable|url',
            'tagline'          => 'nullable|string|max:255',
            'tags'             => 'nullable|string',
            'city'             => 'nullable|string|max:100',
            'country'          => 'nullable|string|max:100',

            // POC
            'poc_name'         => 'sometimes|required|string|max:255',
            'poc_email'        => 'nullable|email',
            'poc_phone'        => 'nullable|string|max:20',
            'poc_linkedin'     => 'nullable|url',

            'source'           => 'nullable|string|max:255',
            'stage'            => 'nullable|string|max:100',
        ]);

        $lead->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Lead updated successfully',
            'data' => $lead
        ]);
    }

    /**
     * DELETE /api/leads/{id}
     */
    public function destroy($id)
    {
        $lead = Lead::find($id);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found'
            ], 404);
        }

        $lead->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lead deleted successfully'
        ]);
    }

    /**
     * POST /api/leads/bulk-upload
     */
    public function bulkUpload(Request $request)
    {
        $request->validate([
            'leads' => 'required|array|min:1',
        ]);

        $created = 0;
        $skipped = 0;

        DB::beginTransaction();

        try {
            foreach ($request->leads as $row) {

                if (empty($row['organization_name']) || empty($row['poc_name'])) {
                    $skipped++;
                    continue;
                }

                $lastId = Lead::max('id') + 1;
                $leadCode = 'L' . str_pad($lastId, 3, '0', STR_PAD_LEFT);

                Lead::create([
                    'lead_code'         => $leadCode,
                    'company_name'      => $row['organization_name'],
                    'company_website'   => $row['company_website'] ?? null,
                    'company_linkedin'  => $row['company_linkedin'] ?? null,
                    'city'              => $row['city'] ?? null,
                    'country'           => $row['country'] ?? null,

                    'tagline' => collect([
                        $row['tag_1'] ?? null,
                        $row['tag_2'] ?? null,
                        $row['tag_3'] ?? null,
                        $row['tag_4'] ?? null,
                        $row['tag_5'] ?? null,
                    ])->filter()->implode(', '),

                    'tags' => collect([
                        $row['tag_1'] ?? null,
                        $row['tag_2'] ?? null,
                        $row['tag_3'] ?? null,
                        $row['tag_4'] ?? null,
                        $row['tag_5'] ?? null,
                    ])->filter()->implode(', '),

                    'poc_name'     => $row['poc_name'],
                    'poc_email'    => $row['poc_email'] ?? null,
                    'poc_phone'    => $row['poc_number'] ?? null,
                    'poc_linkedin' => $row['poc_linkedin'] ?? null,

                    'source' => $row['source'] ?? null,
                    'stage'  => 'Requirement',
                ]);

                $created++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bulk leads uploaded successfully',
                'summary' => [
                    'created' => $created,
                    'skipped' => $skipped,
                ]
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Bulk upload failed'
            ], 500);
        }
    }

    public function convert(int $id)
    {
        $lead = Lead::find($id);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found'
            ], 404);
        }

        if ($lead->is_converted) {
            return response()->json([
                'success' => false,
                'message' => 'Lead already converted'
            ], 409);
        }

        $lead->update([
            'is_converted' => true,
            'converted_at' => now(),
            'stage'        => 'Converted'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lead converted successfully',
            'data' => [
                'lead_id' => $lead->id,
                'stage'   => 'Converted'
            ]
        ]);
    }

}
