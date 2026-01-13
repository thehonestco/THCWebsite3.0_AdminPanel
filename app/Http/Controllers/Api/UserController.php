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
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 10);
        $search  = $request->get('search');
        $status  = $request->get('status');
        // status: active | inactive | deleted

        $query = User::query()
            ->with([
                'roles:id,name',
                'detail:id,user_id,phone'
            ]);

        /* ======================
         |  Search
         ====================== */
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereHas('detail', function ($d) use ($search) {
                      $d->where('phone', 'like', "%{$search}%");
                  });
            });
        }

        /* ======================
         |  Status filter
         ====================== */
        if ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        } elseif ($status === 'active') {
            $query->where('is_active', true);
        }

        $users = $query
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        /* ======================
         |  Transform for UI
         ====================== */
        $users->getCollection()->transform(function ($user) {
            return [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'number' => $user->detail->phone ?? null,

                'role' => $user->roles->first()->name ?? '-',
                'type' => $user->roles->first()->name ?? '-',

                'status' => $user->trashed()
                    ? 'Deleted'
                    : ($user->is_active ? 'Active' : 'Inactive'),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Users list fetched successfully',
            'data'    => $users
        ]);
    }

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

    public function show(int $id): JsonResponse
    {
        $user = User::with([
            'roles:id,name',
            'detail',
            'directPermissions:id,name'
        ])
        ->withTrashed()
        ->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'User fetched successfully',
            'data' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,

                'status' => $user->trashed()
                    ? 'Deleted'
                    : ($user->is_active ? 'Active' : 'Inactive'),

                'role_id' => $user->roles->first()->id ?? null,

                'details' => [
                    'phone'        => $user->detail->phone ?? null,
                    'designation'  => $user->detail->designation ?? null,
                    'department'   => $user->detail->department ?? null,
                    'linkedin_url' => $user->detail->linkedin_url ?? null,
                    'tags'         => $user->detail->tags ?? null,
                ],

                'permissions' => $user->directPermissions->map(fn ($p) => [
                    'id'      => $p->id,
                    'name'    => $p->name,
                    'allowed' => (bool) $p->pivot->allowed
                ]),
            ]
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::with(['detail'])->withTrashed()->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:100',
            'email'       => 'required|email|unique:users,email,' . $user->id,
            'password'    => 'nullable|min:8',
            'role_id'     => 'required|exists:roles,id',

            // user_details
            'phone'       => 'nullable|string|max:20',
            'designation' => 'nullable|string|max:100',
            'department'  => 'nullable|string|max:100',
            'linkedin_url'=> 'nullable|url',
            'tags'        => 'nullable|string',

            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',

            'is_active'   => 'nullable|boolean',
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
            $user->update([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'is_active' => $data['is_active'] ?? $user->is_active,
            ]);

            if (!empty($data['password'])) {
                $user->update([
                    'password' => Hash::make($data['password'])
                ]);
            }

            // 2️⃣ USER DETAILS
            UserDetail::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'phone'        => $data['phone'] ?? null,
                    'designation'  => $data['designation'] ?? null,
                    'department'   => $data['department'] ?? null,
                    'linkedin_url' => $data['linkedin_url'] ?? null,
                    'tags'         => $data['tags'] ?? null,
                ]
            );

            // 3️⃣ ROLE
            $user->roles()->sync([$data['role_id']]);

            // 4️⃣ PERMISSIONS
            if (isset($data['permissions'])) {
                $user->directPermissions()->sync(
                    collect($data['permissions'])
                        ->mapWithKeys(fn ($id) => [$id => ['allowed' => true]])
                        ->toArray()
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully'
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user'
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
