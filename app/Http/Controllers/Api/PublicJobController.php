<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ReoonService;

class PublicJobController extends Controller
{
    /**
     * 1️⃣ Public Job Listings
     * GET /api/public/jobs
     */
    public function index()
    {
        $jobs = Position::query()
            ->where('status', 'open')
            ->with('jobDescription:id,title')
            ->orderByDesc('id')
            ->get()
            ->map(function (Position $job) {
                return [
                    'id' => $job->id,
                    'title' => $job->position_name,
                    'job_description_title' => $job->jobDescription?->title,

                    'job_type' => ucfirst(str_replace('_', ' ', $job->job_type)),
                    'work_mode' => ucfirst($job->work_mode),

                    'experience' => "{$job->experience_min}-{$job->experience_max} yrs",

                    'salary' => ($job->salary_min && $job->salary_max)
                        ? "{$job->salary_min}-{$job->salary_max} LPA"
                        : 'Not disclosed',

                    'city' => $job->city,
                    'country' => $job->country,

                    // ✅ JSON skills (safe default)
                    'skills' => $job->skills ?? [],

                    'posted_at' => $job->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $jobs,
        ]);
    }

    /**
     * 2️⃣ Public Job Detail Page
     * GET /api/public/jobs/{id}
     */
    public function show(int $id)
    {
        $job = Position::with('jobDescription')
            ->where('status', 'open')
            ->find($id);

        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found or no longer available',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $job->id,
                'title' => $job->position_name,

                /* JOB DESCRIPTION */
                'job_description' => [
                    'title' => $job->jobDescription?->title,
                    'about_job' => $job->jobDescription?->about_job,
                    'responsibilities' => $job->jobDescription?->responsibilities,
                    'key_skills' => $job->jobDescription?->key_skills,
                    'interview_process' => $job->jobDescription?->interview_process,
                ],

                /* POSITION META */
                'job_type' => ucfirst(str_replace('_', ' ', $job->job_type)),
                'work_mode' => ucfirst($job->work_mode),

                'experience' => "{$job->experience_min}-{$job->experience_max} years",

                'salary' => ($job->salary_min && $job->salary_max)
                    ? "{$job->salary_min}-{$job->salary_max} LPA"
                    : 'Not disclosed',

                'city' => $job->city,
                'country' => $job->country,

                // ✅ JSON skills for detail page
                'skills' => $job->skills ?? [],

                'status' => 'Open',
                'posted_at' => $job->created_at->toDateString(),
            ],
        ]);
    }

    /**
     * 3️⃣ Apply for Job
     * POST /api/public/jobs/{id}/apply
     */
    public function apply(Request $request, int $positionId)
    {
        /* 1️⃣ Validate Position */
        $position = Position::where('status', 'open')->find($positionId);

        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Job position not available',
            ], 404);
        }

        /* 2️⃣ Basic Validation */
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',

            'experience_years' => 'required|numeric|min:0',
            'current_ctc'      => 'nullable|numeric|min:0',
            'expected_ctc'     => 'nullable|numeric|min:0',
            'notice_period_days' => 'nullable|integer|min:0',
        ]);

        DB::beginTransaction();

        try {
            /* 2️⃣.5️⃣ EMAIL VERIFICATION (REOON) */
            $applicant = Applicant::where('email', $validated['email'])->first();

            if (!$applicant || !$applicant->email_verified) {
                $emailCheck = app(ReoonService::class)->verify($validated['email']);

                // ❌ allow ONLY valid emails
                if ($emailCheck['status'] !== 'safe') {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Please provide a valid email address',
                        'email_status' => $emailCheck['status'],
                    ], 422);
                }
            }

            /* 3️⃣ Applicant (email unique) */
            $isNewApplicant = false;

            if (!$applicant) {
                $applicant = Applicant::create([
                    'name'   => $validated['name'],
                    'email'  => $validated['email'],
                    'phone'  => $validated['phone'],
                    'status' => 'active',

                    // 🔐 store verification result
                    'email_verified' => true,
                    'email_verification_status' => 'valid',
                ]);
                $isNewApplicant = true;
            } else {
                // 🔁 update verification if not already verified
                if (!$applicant->email_verified) {
                    $applicant->update([
                        'email_verified' => true,
                        'email_verification_status' => 'valid',
                    ]);
                }
            }

            /* 4️⃣ Resume validation */
            if ($isNewApplicant && !$request->hasFile('resume')) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Resume (PDF) is required',
                ], 422);
            }

            if ($request->hasFile('resume')) {
                $request->validate([
                    'resume' => 'required|file|mimes:pdf|max:10240',
                ]);
            }

            /* 5️⃣ Prevent duplicate application */
            $alreadyApplied = Application::where([
                'position_id'  => $position->id,
                'applicant_id' => $applicant->id,
            ])->exists();

            if ($alreadyApplied) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'You have already applied for this position',
                ], 409);
            }

            /* 6️⃣ Create Application */
            $application = Application::create([
                'position_id'   => $position->id,
                'applicant_id'  => $applicant->id,

                'experience_years'   => $validated['experience_years'],
                'current_ctc'        => $validated['current_ctc'] ?? null,
                'expected_ctc'       => $validated['expected_ctc'] ?? null,
                'notice_period_days' => $validated['notice_period_days'] ?? null,

                'stage'      => Application::STAGE_FRESH,
                'created_by' => 1, // SYSTEM / PUBLIC
            ]);

            /* 7️⃣ Resume Upload */
            if ($request->hasFile('resume')) {

                $resume = $request->file('resume');

                $fileName = time() . '_' . preg_replace('/\s+/', '_', $resume->getClientOriginalName());

                $path = $resume->storeAs(
                    'resumes',
                    $fileName,
                    'public'
                );

                $application->update([
                    'resume_path' => $path,   // ✅ proper field
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Application submitted successfully',
                'data' => [
                    'application_id' => $application->id,
                    'applicant_id'   => $applicant->id,
                ],
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
