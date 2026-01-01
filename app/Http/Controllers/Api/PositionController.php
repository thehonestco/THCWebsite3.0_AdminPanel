<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PositionController extends Controller
{
    /**
     * List Positions
     */
    public function index(Request $request)
    {
        $query = Position::query()
            ->with([
                'jobDescription:id,title,about_job'
            ])
            ->withCount([
                'applications as active_applicants' => function ($q) {
                    $q->whereIn('stage', ['open','screening','interview','offer']);
                }
            ])
            ->orderByDesc('id');

        // Filters
        if ($request->filled('job_description_id')) {
            $query->where('job_description_id', $request->job_description_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        if ($request->filled('job_type')) {
            $query->where('job_type', $request->job_type);
        }

        if ($request->filled('work_mode')) {
            $query->where('work_mode', $request->work_mode);
        }

        $positions = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $positions->through(function ($p) {
                return [
                    'id' => $p->id,
                    'reference_code' => $p->reference_code,

                    // JD info
                    'title' => optional($p->jobDescription)->title,
                    'subtitle' => str(optional($p->jobDescription)->about_job)->limit(60),

                    'created_on' => $p->created_at->format('d/m/Y'),

                    // Card fields
                    'job_type' => $p->job_type,
                    'nature' => $p->work_mode,
                    'experience' => $p->experience_min && $p->experience_max
                        ? "{$p->experience_min}–{$p->experience_max} Years"
                        : null,

                    'salary' => $p->salary_min && $p->salary_max
                        ? "₹{$p->salary_min}–{$p->salary_max} LPA"
                        : null,

                    'stage' => ucfirst($p->status),
                    'applicants' => $p->active_applicants,
                ];
            })
        ]);
    }

    /**
     * Create Position
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'job_description_id' => 'required|exists:job_descriptions,id',
            'organization_name'  => 'required|string|max:255',
            'source'             => 'required|in:Referral,Inbound,Outbound',
            'location'           => 'nullable|string|max:255',
            'website_url'        => 'nullable|url|max:255',
            'linkedin_url'       => 'nullable|url|max:255',
            'tags'               => 'nullable|string',
            'contact_name'       => 'nullable|string|max:255',
            'contact_email'      => 'nullable|email|max:255',
            'contact_phone'      => 'nullable|string|max:20',
            'status'             => 'required|in:active,closed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors()
            ], 422);
        }

        $position = Position::create([
            ...$validator->validated(),
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Position created successfully',
            'data'    => $position
        ], 201);
    }

    /**
     * Show Position
     */
    public function show(int $id)
    {
        $position = Position::find($id);

        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $position
        ]);
    }

    /**
     * Update Position
     */
    public function update(Request $request, int $id)
    {
        $position = Position::find($id);

        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'organization_name' => 'required|string|max:255',
            'source'            => 'required|in:Referral,Inbound,Outbound',
            'location'          => 'nullable|string|max:255',
            'website_url'       => 'nullable|url|max:255',
            'linkedin_url'      => 'nullable|url|max:255',
            'tags'              => 'nullable|string',
            'contact_name'      => 'nullable|string|max:255',
            'contact_email'     => 'nullable|email|max:255',
            'contact_phone'     => 'nullable|string|max:20',
            'status'            => 'required|in:active,closed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors()
            ], 422);
        }

        $position->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Position updated successfully',
            'data'    => $position
        ]);
    }

    /**
     * Soft Delete Position
     */
    public function destroy(int $id)
    {
        $position = Position::withTrashed()->where('id', $id)->first();

        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found'
            ], 404);
        }

        if ($position->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Position already deleted'
            ], 409);
        }

        $position->delete();

        return response()->json([
            'success' => true,
            'message' => 'Position deleted successfully'
        ]);
    }
}
