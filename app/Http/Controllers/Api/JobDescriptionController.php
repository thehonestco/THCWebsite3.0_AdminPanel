<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobDescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class JobDescriptionController extends Controller
{
    /**
     * List Job Descriptions
     */
    public function index(Request $request)
    {
        $query = JobDescription::query()
            ->withCount([
                'positions as active_positions' => function ($q) {
                    $q->where('status', 'active');
                }
            ])
            ->orderByDesc('id');

        // optional filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(10)
        ]);
    }

    /**
     * Create Job Description
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'            => 'required|string|max:255',
            'status'           => 'required|in:draft,active,closed',
            'about_job'        => 'nullable|string',
            'key_skills'       => 'nullable|string',
            'responsibilities' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors()
            ], 422);
        }

        $jd = JobDescription::create([
            'title'            => $request->title,
            'status'           => $request->status,
            'about_job'        => $request->about_job,
            'key_skills'       => $request->key_skills,
            'responsibilities' => $request->responsibilities,
            'created_by'       => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Job description created successfully',
            'data'    => $jd
        ], 201);
    }

    /**
     * View single Job Description
     */
    public function show(int $id)
    {
        $jd = JobDescription::withCount([
            'positions as active_positions' => function ($q) {
                $q->where('status', 'active');
            }
        ])->find($id);

        if (!$jd) {
            return response()->json([
                'success' => false,
                'message' => 'Job description not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $jd
        ]);
    }

    /**
     * Update Job Description
     */
    public function update(Request $request, int $id)
    {
        $jd = JobDescription::find($id);

        if (!$jd) {
            return response()->json([
                'success' => false,
                'message' => 'Job description not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title'            => 'required|string|max:255',
            'status'           => 'required|in:draft,active,closed',
            'about_job'        => 'nullable|string',
            'key_skills'       => 'nullable|string',
            'responsibilities' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors()
            ], 422);
        }

        $jd->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Job description updated successfully',
            'data'    => $jd
        ]);
    }

    /**
     * Soft delete Job Description
     */
    public function destroy(int $id)
    {
        $jd = JobDescription::withTrashed()->where('id', $id)->first();

        if (!$jd) {
            return response()->json([
                'success' => false,
                'message' => 'Job description not found'
            ], 404);
        }

        if ($jd->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Job description already deleted'
            ], 409);
        }

        $jd->delete();

        return response()->json([
            'success' => true,
            'message' => 'Job description deleted successfully'
        ]);
    }
}
