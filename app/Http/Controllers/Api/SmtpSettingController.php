<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SmtpSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SmtpSettingController extends Controller
{
    /**
     * List SMTPs
     */
    public function index()
    {
        $smtps = SmtpSetting::with('creator:id,name')
            ->orderByDesc('id')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $smtps
        ]);
    }

    /**
     * Create SMTP
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'host'        => 'required|string|max:255',
            'port'        => 'required|integer',
            'username'    => 'nullable|string|max:255',
            'password'    => 'nullable|string|max:255',
            'encryption'  => 'nullable|in:ssl,tls',
            'from_email'  => 'required|email',
            'from_name'   => 'nullable|string|max:255',
            'is_active'   => 'boolean',
            'is_default'  => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            if ($request->boolean('is_default')) {
                SmtpSetting::where('is_default', true)->update([
                    'is_default' => false
                ]);
            }

            $smtp = SmtpSetting::create([
                ...$validator->validated(),
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'SMTP created successfully',
                'data' => $smtp
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
     * Show SMTP
     */
    public function show(int $id)
    {
        $smtp = SmtpSetting::with('creator:id,name')->find($id);

        if (!$smtp) {
            return response()->json([
                'success' => false,
                'message' => 'SMTP not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $smtp
        ]);
    }

    /**
     * Update SMTP
     */
    public function update(Request $request, int $id)
    {
        $smtp = SmtpSetting::find($id);

        if (!$smtp) {
            return response()->json([
                'success' => false,
                'message' => 'SMTP not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'host'        => 'required|string|max:255',
            'port'        => 'required|integer',
            'username'    => 'nullable|string|max:255',
            'password'    => 'nullable|string|max:255',
            'encryption'  => 'nullable|in:ssl,tls',
            'from_email'  => 'required|email',
            'from_name'   => 'nullable|string|max:255',
            'is_active'   => 'boolean',
            'is_default'  => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            if ($request->boolean('is_default')) {
                SmtpSetting::where('is_default', true)
                    ->where('id', '!=', $smtp->id)
                    ->update(['is_default' => false]);
            }

            $smtp->update($validator->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'SMTP updated successfully',
                'data' => $smtp
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
     * Delete SMTP
     */
    public function destroy(int $id)
    {
        $smtp = SmtpSetting::find($id);

        if (!$smtp) {
            return response()->json([
                'success' => false,
                'message' => 'SMTP not found'
            ], 404);
        }

        $smtp->delete();

        return response()->json([
            'success' => true,
            'message' => 'SMTP deleted successfully'
        ]);
    }
}
