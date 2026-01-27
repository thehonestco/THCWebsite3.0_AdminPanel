<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PositionController extends Controller
{
    /**
     * List Positions
     */
    public function index()
    {
        $positions = Position::with('jobDescription:id,title')
            ->withCount('applications')
            ->orderByDesc('id')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $positions
        ]);
    }

    /**
     * Create Position (with optional applicants)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'position_name'      => 'required|string|max:255',
            'job_description_id' => 'required|exists:job_descriptions,id',
            'job_type'           => 'required|in:Full Time,Part Time,Contract',
            'work_mode'          => 'required|in:Onsite,Remote,Hybrid',

            'experience_min'     => 'nullable|integer|min:0',
            'experience_max'     => 'nullable|integer|gte:experience_min',

            'salary_min'         => 'nullable|integer|min:0',
            'salary_max'         => 'nullable|integer|gte:salary_min',

            'status'             => 'required|in:open,closed',

            // Existing applicants only
            'applicant_ids'      => 'nullable|array',
            'applicant_ids.*'    => 'exists:applicants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            /** @var Position $position */
            $position = Position::create([
                ...$request->only([
                    'position_name',
                    'job_description_id',
                    'job_type',
                    'work_mode',
                    'experience_min',
                    'experience_max',
                    'salary_min',
                    'salary_max',
                    'status',
                ]),
                'created_by' => auth()->id(),
            ]);

            // ✅ Apply existing applicants (ENUM SAFE)
            if ($request->filled('applicant_ids')) {
                foreach ($request->applicant_ids as $applicantId) {
                    $position->applications()->firstOrCreate(
                        [
                            'applicant_id' => $applicantId,
                        ],
                        [
                            'stage' => 'fresh', // ✅ ENUM safe
                            'created_by' => auth()->id(),
                        ]
                    );
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Position created successfully',
                'data' => $position->load('applications.applicant')
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage() // 👈 helpful in dev
            ], 500);
        }
    }

    /**
     * Show Position
     */
    public function show(int $id)
    {
        $position = Position::with([
            'jobDescription',
            'applications.applicant'
        ])->find($id);

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
            'position_name' => 'required|string|max:255',
            'job_type'      => 'required|in:Full Time,Part Time,Contract',
            'work_mode'     => 'required|in:Onsite,Remote,Hybrid',
            'status'        => 'required|in:open,closed',

            'experience_min' => 'nullable|integer|min:0',
            'experience_max' => 'nullable|integer|gte:experience_min',
            'salary_min'     => 'nullable|integer|min:0',
            'salary_max'     => 'nullable|integer|gte:salary_min',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $position->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Position updated successfully',
            'data' => $position
        ]);
    }

    /**
     * Soft Delete Position
     */
    public function destroy(int $id)
    {
        $position = Position::find($id);

        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found'
            ], 404);
        }

        $position->delete();

        return response()->json([
            'success' => true,
            'message' => 'Position deleted successfully'
        ]);
    }

    /**
     * Add existing applicants to a position
     */
    public function addApplicants(Request $request, int $id)
    {
        $position = Position::find($id);

        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'applicant_ids'   => 'required|array',
            'applicant_ids.*' => 'exists:applicants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            foreach ($request->applicant_ids as $applicantId) {
                $position->applications()->firstOrCreate(
                    [
                        'applicant_id' => $applicantId,
                    ],
                    [
                        'stage' => 'fresh', // ✅ ENUM SAFE
                        'created_by' => auth()->id(),
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Applicants added successfully',
                'data' => $position->load('applications.applicant')
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update applicant details for a position
     */
    public function updateApplicant(
        Request $request,
        int $positionId,
        int $applicationId
    ) {
        $application = Application::withTrashed()
            ->where('id', $applicationId)
            ->where('position_id', $positionId)
            ->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found for this position'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'experience_years'   => 'nullable|numeric|min:0|max:50',
            'current_ctc'        => 'nullable|numeric|min:0',
            'expected_ctc'       => 'nullable|numeric|min:0',
            'notice_period_days' => 'nullable|integer|min:0|max:365',

            'stage' => 'nullable|in:' . implode(',', [
                Application::STAGE_FRESH,
                Application::STAGE_SCREENING,
                Application::STAGE_HR_ROUND,
                Application::STAGE_TECH_ROUND,
                Application::STAGE_FINAL_ROUND,
                Application::STAGE_OFFER_SENT,
                Application::STAGE_REJECTED,
                Application::STAGE_DROPPED,
            ]),

            'comment' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // auto touch recruiter activity
        if (array_key_exists('stage', $data) || array_key_exists('comment', $data)) {
            $data['last_contact_at'] = now();
        }

        $application->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Applicant updated successfully',
            'data' => $application
        ]);
    }

    /**
     * Remove applicant from a position
     */
    public function removeApplicant(int $positionId, int $applicationId)
    {
        $application = Application::where('id', $applicationId)
            ->where('position_id', $positionId)
            ->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found'
            ], 404);
        }

        $application->delete(); // soft delete

        return response()->json([
            'success' => true,
            'message' => 'Applicant removed from position'
        ]);
    }
}
