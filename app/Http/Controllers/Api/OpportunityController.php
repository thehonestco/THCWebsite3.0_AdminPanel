<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Opportunity;
use Illuminate\Http\Request;
use App\Services\LeadStatusService;

class OpportunityController extends Controller
{
    /**
     * GET /api/leads/{leadId}/opportunities
     */
    public function index($leadId)
    {
        $lead = Lead::find($leadId);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $lead->opportunities()->with('notes')->get()
        ]);
    }

    /**
     * POST /api/leads/{leadId}/opportunities
     */
    public function store(Request $request, $leadId)
    {
        $lead = Lead::find($leadId);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found'
            ], 404);
        }

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount'      => 'nullable|numeric|min:0',
            'owner_name'  => 'nullable|string|max:255',
            'stage'       => 'nullable|string|max:100',
            'status'      => 'nullable|string|max:100',
        ]);

        $validated['owner_name'] = $request->user()->name;        
        $opportunity = $lead->opportunities()->create($validated);
        LeadStatusService::update($lead->id);

        return response()->json([
            'success' => true,
            'message' => 'Opportunity created successfully',
            'data' => $opportunity
        ], 201);
    }

    /**
     * GET /api/opportunities/{id}
     */
    public function show($id)
    {
        $opportunity = Opportunity::with('notes')->find($id);

        if (!$opportunity) {
            return response()->json([
                'success' => false,
                'message' => 'Opportunity not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $opportunity
        ]);
    }

    /**
     * PUT /api/opportunities/{id}
     */
    public function update(Request $request, $id)
    {
        $opportunity = Opportunity::find($id);

        if (!$opportunity) {
            return response()->json([
                'success' => false,
                'message' => 'Opportunity not found'
            ], 404);
        }

        $validated = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'amount'      => 'nullable|numeric|min:0',
            'owner_name'  => 'nullable|string|max:255',
            'stage'       => 'nullable|string|max:100',
            'status'      => 'nullable|string|max:100',
        ]);

        $validated['owner_name'] = $request->user()->name;
        $opportunity->update($validated);
        LeadStatusService::update($opportunity->lead_id);

        return response()->json([
            'success' => true,
            'message' => 'Opportunity updated successfully',
            'data' => $opportunity
        ]);
    }

    /**
     * DELETE /api/opportunities/{id}
     */
    public function destroy($id)
    {
        $opportunity = Opportunity::find($id);

        if (!$opportunity) {
            return response()->json([
                'success' => false,
                'message' => 'Opportunity not found'
            ], 404);
        }

        $leadId = $opportunity->lead_id;
        $opportunity->delete();

        LeadStatusService::update($leadId);

        return response()->json([
            'success' => true,
            'message' => 'Opportunity deleted successfully'
        ]);
    }
}
