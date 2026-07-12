<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Position;
use App\Services\ReoonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PublicJobController extends Controller
{
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
                    'skills' => $job->skills ?? [],
                    'posted_at' => $job->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $jobs,
        ]);
    }

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
                'job_description' => [
                    'title' => $job->jobDescription?->title,
                    'about_job' => $job->jobDescription?->about_job,
                    'responsibilities' => $job->jobDescription?->responsibilities,
                    'key_skills' => $job->jobDescription?->key_skills,
                    'interview_process' => $job->jobDescription?->interview_process,
                ],
                'job_type' => ucfirst(str_replace('_', ' ', $job->job_type)),
                'work_mode' => ucfirst($job->work_mode),
                'experience' => "{$job->experience_min}-{$job->experience_max} years",
                'salary' => ($job->salary_min && $job->salary_max)
                    ? "{$job->salary_min}-{$job->salary_max} LPA"
                    : 'Not disclosed',
                'city' => $job->city,
                'country' => $job->country,
                'skills' => $job->skills ?? [],
                'status' => 'Open',
                'posted_at' => $job->created_at->toDateString(),
            ],
        ]);
    }

    public function apply(Request $request, int $positionId)
    {
        $position = Position::where('status', 'open')->find($positionId);

        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Job position not available',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'experience_years' => 'required|numeric|min:0',
            'current_ctc' => 'nullable|numeric|min:0',
            'expected_ctc' => 'nullable|numeric|min:0',
            'notice_period_days' => 'nullable|integer|min:0',
        ]);

        DB::beginTransaction();

        try {
            $applicant = Applicant::where('email', $validated['email'])->first();

            if (!$applicant || !$applicant->email_verified) {
                $emailCheck = app(ReoonService::class)->verify($validated['email']);

                if ($emailCheck['status'] !== 'safe') {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Please provide a valid email address',
                        'email_status' => $emailCheck['status'],
                    ], 422);
                }
            }

            $isNewApplicant = false;

            if (!$applicant) {
                $applicant = Applicant::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'status' => 'active',
                    'email_verified' => true,
                    'email_verification_status' => 'valid',
                ]);
                $isNewApplicant = true;
            } elseif (!$applicant->email_verified) {
                $applicant->update([
                    'email_verified' => true,
                    'email_verification_status' => 'valid',
                ]);
            }

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

            $alreadyApplied = Application::where([
                'position_id' => $position->id,
                'applicant_id' => $applicant->id,
            ])->exists();

            if ($alreadyApplied) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'You have already applied for this position',
                ], 409);
            }

            $application = Application::create([
                'position_id' => $position->id,
                'applicant_id' => $applicant->id,
                'experience_years' => $validated['experience_years'],
                'current_ctc' => $validated['current_ctc'] ?? null,
                'expected_ctc' => $validated['expected_ctc'] ?? null,
                'notice_period_days' => $validated['notice_period_days'] ?? null,
                'stage' => Application::STAGE_FRESH,
                'created_by' => 1,
            ]);

            if ($request->hasFile('resume')) {
                $resume = $request->file('resume');
                $fileName = time() . '_' . preg_replace('/\s+/', '_', $resume->getClientOriginalName());

                $path = Storage::disk('s3')->putFileAs(
                    'resumes',
                    $resume,
                    $fileName
                );

                if (!$path) {
                    throw new \Exception('Resume upload failed to S3');
                }

                $application->update([
                    'resume_path' => Storage::disk('s3')->url($path),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Application submitted successfully',
                'data' => [
                    'application_id' => $application->id,
                    'applicant_id' => $applicant->id,
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
