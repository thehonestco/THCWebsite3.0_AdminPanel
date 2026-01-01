<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserDetail;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    /**
     * Create new user (permission based)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:100',
            'email'       => 'required|email|unique:users,email',
            'password'    => 'required|min:8',
            'role_id'     => 'required|exists:roles,id',

            // user_details fields
            'phone'       => 'nullable|string|max:20',
            'designation' => 'nullable|string|max:100',
            'department'  => 'nullable|string|max:100',
            'linkedin_url'=> 'nullable|url',
            'tags'        => 'nullable|string',

            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $data = $validator->validated();

            // 1️⃣ USER
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            // 2️⃣ USER DETAILS
            UserDetail::create([
                'user_id'      => $user->id,
                'phone'        => $data['phone'] ?? null,
                'designation'  => $data['designation'] ?? null,
                'department'   => $data['department'] ?? null,
                'linkedin_url' => $data['linkedin_url'] ?? null,
                'tags'         => $data['tags'] ?? null,
            ]);

            // 3️⃣ ROLE
            $user->roles()->sync([$data['role_id']]);

            // 4️⃣ PERMISSIONS
            if (!empty($data['permissions'])) {
                $user->directPermissions()->sync(
                    collect($data['permissions'])
                        ->mapWithKeys(fn ($id) => [$id => ['allowed' => true]])
                        ->toArray()
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create user'
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        // 🔹 STEP 1: Check user exists (including deleted)
        $user = User::withTrashed()->where('id', $id)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // 🔹 STEP 2: Prevent self delete
        if (auth()->id() === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete yourself'
            ], 403);
        }

        // 🔹 STEP 3: Already deleted
        if ($user->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'User already deleted'
            ], 409); // Conflict
        }

        // 🔹 STEP 4: Soft delete
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ], 200);
    }

    public function restore(int $id): JsonResponse
    {
        $user = User::withTrashed()->where('id', $id)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if (!$user->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'User is already active'
            ], 409); // Conflict
        }

        $user->restore();

        return response()->json([
            'success' => true,
            'message' => 'User restored successfully'
        ], 200);
    }

}
