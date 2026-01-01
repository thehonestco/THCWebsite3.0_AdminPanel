<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'=>'required|email',
            'password'=>'required'
        ]);

        if (!Auth::attempt([
            'email' => $request->email,
            'password' => $request->password,
            'deleted_at' => null
        ])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = $request->user();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id'=>$user->id,
                'name'=>$user->name,
                'email'=>$user->email,
                'roles'=>$user->roles->pluck('name'),
            ]
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user'=>$request->user(),
            'roles'=>$request->user()->roles->pluck('name')
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message'=>'Logged out']);
    }
}
