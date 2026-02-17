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
     * Create Position
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'position_name'      => 'required|string|max:255',
            'job_description_id' => 'required|exists:job_descriptions,id',
            'job_type'           => 'required|in:Full Time,Contract,Internship',
            'work_mode'          => 'required|in:Onsite,Remote,Hybrid',

            'city'    => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',

            'skills'   => 'nullable|array',
            'skills.*' => 'string|max:50',

            'experience_min' => 'nullable|integer|min:0',
            'experience_max' => 'nullable|integer|gte:experience_min',
            'salary_min'     => 'nullable|integer|min:0',
            'salary_max'     => 'nullable|integer|gte:salary_min',

            'status' => 'required|in:open,closed',

            'applicant_ids'   => 'nullable|array',
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
            $position = Position::create([
                ...$validator->validated(),
                'skills' => array_values(array_unique($request->skills ?? [])),
                'created_by' => auth()->id(),
            ]);

            if ($request->filled('applicant_ids')) {
                foreach ($request->applicant_ids as $applicantId) {
                    $position->applications()->firstOrCreate(
                        ['applicant_id' => $applicantId],
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
                'message' => 'Position created successfully',
                'data' => $position->load('applications.applicant')
            ], 201);

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
            'job_type'      => 'required|in:Full Time,Contract,Internship',
            'work_mode'     => 'required|in:Onsite,Remote,Hybrid',
            'status'        => 'required|in:open,closed',

            'city'    => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',

            'skills'   => 'nullable|array',
            'skills.*' => 'string|max:50',

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

        DB::beginTransaction();

        try {
            $position->update([
                ...$validator->validated(),
                'skills' => array_values(
                    array_unique($request->skills ?? $position->skills ?? [])
                ),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Position updated successfully',
                'data' => $position
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
     * Add existing applicants
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

        DB::transaction(function () use ($request, $position) {
            foreach ($request->applicant_ids as $applicantId) {
                $position->applications()->firstOrCreate(
                    ['applicant_id' => $applicantId],
                    [
                        'stage' => 'fresh',
                        'created_by' => auth()->id(),
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Applicants added successfully',
            'data' => $position->load('applications.applicant')
        ]);
    }

    /**
     * Update applicant
     */
    public function updateApplicant(Request $request, int $positionId, int $applicationId)
    {
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
            'stage'              => 'nullable|string',
            'comment'            => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        if (isset($data['stage']) || isset($data['comment'])) {
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
     * Remove applicant
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

        $application->delete();

        return response()->json([
            'success' => true,
            'message' => 'Applicant removed from position'
        ]);
    }
}
