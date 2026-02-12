<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ApplicantController extends Controller
{
    /**
     * List Applicants (Frontend list page)
     */
    public function index(Request $request)
    {
        $query = Applicant::query()
            ->with([
                'applications' => function ($q) {
                    $q->with('position')
                      ->latest();
                }
            ])
            ->withCount([
                'applications as active_applications' => function ($q) {
                    $q->whereIn('stage', [
                        'fresh',
                        'screening',
                        'hr_round',
                        'tech_round',
                        'final_round',
                        'offer_sent', 
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

                $latestApplication = $a->applications->first();

                return [
                    'id' => $a->id,
                    'name' => $a->name,
                    'email' => $a->email,
                    'phone' => $a->phone,
                    'linkedin_url' => $a->linkedin_url,
                    'status' => $a->status,
                    'skills' => $a->skills ? explode(',', $a->skills) : [],

                    'active_applications' => $a->active_applications,
                    'current_stage' => $latestApplication?->stage,

                    'positions_applied' => $a->applications->map(function ($app) {
                        return [
                            'application_id' => $app->id,
                            'position_id' => $app->position?->id,
                            'position_name' => $app->position?->position_name,
                            'job_type' => $app->position?->job_type,
                            'work_mode' => $app->position?->work_mode,
                            'stage' => $app->stage,
                        ];
                    }),
                ];
            })
        ]);
    }

    /**
     * Create Applicant + Apply to Multiple Positions
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'phone'        => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255|unique:applicants,email',
            'linkedin_url' => 'nullable|url|max:255',
            'skills'       => 'nullable|string',
            'status'       => 'required|in:active,inactive',

            // Apply positions
            'position_ids'   => 'nullable|array',
            'position_ids.*' => 'exists:positions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $applicant = Applicant::create([
                ...$validator->validated(),
                'created_by' => auth()->id(),
            ]);

            if ($request->filled('position_ids')) {
                foreach ($request->position_ids as $positionId) {
                    Application::firstOrCreate(
                        [
                            'position_id' => $positionId,
                            'applicant_id' => $applicant->id,
                        ],
                        [
                            'stage' => 'fresh',
                            'created_by' => auth()->id(),
                        ]
                    );
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Applicant created successfully',
                'data' => $applicant->load('applications.position')
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Applicant Detail Page
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
            'data' => [
                'id' => $applicant->id,
                'name' => $applicant->name,
                'email' => $applicant->email,
                'phone' => $applicant->phone,
                'skills' => $applicant->skills ? explode(',', $applicant->skills) : [],
                'active_applications' => $applicant->applications->whereIn('stage', [
                                                                    'fresh',
                                                                    'screening',
                                                                    'hr_round',
                                                                    'tech_round',
                                                                    'final_round',
                                                                    'offer_sent',
                                                                ])->count(),

                'applications' => $applicant->applications->map(function ($app) {
                    return [
                        'application_id' => $app->id,
                        'position_id' => $app->position_id,
                        'position_name' => $app->position?->position_name,
                        'job_type' => $app->position?->job_type,
                        'work_mode' => $app->position?->work_mode,
                        'experience_range' => $app->position
                            ? "{$app->position->experience_min}-{$app->position->experience_max} yrs"
                            : null,
                        'salary_range' => $app->position
                            ? "{$app->position->salary_min}-{$app->position->salary_max} LPA"
                            : null,
                        'job_stage' => $app->position?->status,
                        'applicant_stage' => $app->stage,
                    ];
                }),
            ]
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
            'email' => 'nullable|email|max:255|unique:applicants,email,' . $id,
            'linkedin_url' => 'nullable|url|max:255',
            'skills'       => 'nullable|string',
            'status'       => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
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
     * Add Positions to Existing Applicant
     */
    public function addPositions(Request $request, int $id)
    {
        $applicant = Applicant::find($id);

        if (!$applicant) {
            return response()->json([
                'success' => false,
                'message' => 'Applicant not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'position_ids'   => 'required|array',
            'position_ids.*' => 'exists:positions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->position_ids as $positionId) {
            $applicant->applications()->firstOrCreate(
                ['position_id' => $positionId],
                [
                    'stage' => 'fresh',
                    'created_by' => auth()->id(),
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Positions added successfully'
        ]);
    }

    /**
     * Soft Delete Applicant
     */
    public function destroy(int $id)
    {
        $applicant = Applicant::find($id);

        if (!$applicant) {
            return response()->json([
                'success' => false,
                'message' => 'Applicant not found'
            ], 404);
        }

        $applicant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Applicant deleted successfully'
        ]);
    }
}
