<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApplicantController extends Controller
{
    /**
     * List Applicants
     */
    public function index(Request $request)
    {
        $query = Applicant::query()
            ->with([
                'applications' => function ($q) {
                    $q->with('position:id,reference_code,organization_name')
                      ->latest();
                }
            ])
            ->withCount([
                'applications as active_applications' => function ($q) {
                    $q->whereIn('stage', [
                        'open', 'screening', 'interview', 'offer'
                    ]);
                }
            ])
            ->orderByDesc('id');

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }

        $applicants = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $applicants->through(function ($a) {

                $latestApp = $a->applications->first();

                return [
                    'id' => $a->id,
                    'name' => $a->name,
                    'phone' => $a->phone,
                    'email' => $a->email,

                    // ✅ count
                    'active_applications' => $a->active_applications,

                    // ✅ list of positions applied
                    'positions_applied' => $a->applications->map(function ($app) {
                        return [
                            'application_id' => $app->id,
                            'position_id' => $app->position?->id,
                            'reference_code' => $app->position?->reference_code,
                            'organization' => $app->position?->organization_name,
                            'stage' => $app->stage,
                        ];
                    }),
                ];
            })
        ]);
    }

    /**
     * Create Applicant
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'phone'        => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:255',
            'linkedin_url' => 'nullable|url|max:255',
            'skills'       => 'nullable|string',
            'status'       => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors()
            ], 422);
        }

        $applicant = Applicant::create([
            ...$validator->validated(),
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Applicant created successfully',
            'data' => $applicant
        ], 201);
    }

    /**
     * View Applicant
     */
    public function show(int $id)
    {
        $applicant = Applicant::with('applications.position')->find($id);

        if (!$applicant) {
            return response()->json([
                'success' => false,
                'message' => 'Applicant not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $applicant
        ]);
    }

    /**
     * Update Applicant
     */
    public function update(Request $request, int $id)
    {
        $applicant = Applicant::find($id);

        if (!$applicant) {
            return response()->json([
                'success' => false,
                'message' => 'Applicant not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'phone'        => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:255',
            'linkedin_url' => 'nullable|url|max:255',
            'skills'       => 'nullable|string',
            'status'       => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors()
            ], 422);
        }

        $applicant->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Applicant updated successfully',
            'data' => $applicant
        ]);
    }

    /**
     * Soft Delete Applicant
     */
    public function destroy(int $id)
    {
        $applicant = Applicant::withTrashed()->find($id);

        if (!$applicant) {
            return response()->json([
                'success' => false,
                'message' => 'Applicant not found'
            ], 404);
        }

        if ($applicant->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Applicant already deleted'
            ], 409);
        }

        $applicant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Applicant deleted successfully'
        ]);
    }
}
