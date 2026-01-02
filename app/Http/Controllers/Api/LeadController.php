<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    /**
     * GET /api/leads
     */
    public function index()
    {
        $leads = Lead::with('opportunities.notes')
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $leads
        ]);
    }

    /**
     * POST /api/leads
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_code'     => 'required|string|unique:leads,lead_code',
            'company_name'  => 'required|string|max:255',
            'tagline'       => 'nullable|string|max:255',
            'name'          => 'required|string|max:255',
            'email'         => 'nullable|email',
            'phone'         => 'nullable|string|max:20',
            'source'        => 'nullable|string|max:255',
            'stage'         => 'nullable|string|max:100',
        ]);

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
            'company_name' => 'sometimes|required|string|max:255',
            'tagline'      => 'nullable|string|max:255',
            'name'         => 'sometimes|required|string|max:255',
            'email'        => 'nullable|email',
            'phone'        => 'nullable|string|max:20',
            'source'       => 'nullable|string|max:255',
            'stage'        => 'nullable|string|max:100',
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
}
