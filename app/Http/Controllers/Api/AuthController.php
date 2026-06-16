<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
    'ic_no' => 'required|string|max:20|unique:lsank_users,ic_no',
    'name' => 'required|string|max:255',
    'email' => 'required|email|unique:lsank_users,email',
    'phone' => 'nullable|string|max:30',
    'password' => 'required|string|min:6',
]);

        $user = LsankUser::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'ic_no' => $request->ic_no,
            'user_type' => 'Pengguna',
            'status' => 'active',
        ]);

        $token = $user->createToken('lsank3_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = LsankUser::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active.',
            ], 403);
        }

        $token = $user->createToken('lsank3_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ]);
    }

    public function updatePhone(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:30',
        ]);

        $user = $request->user();
        $user->phone = $request->phone;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Phone number updated successfully',
            'user' => $user,
        ]);
    }

    public function updateAdminProfile(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:30',
        ]);

        $user = $request->user();
        $user->phone = $request->phone;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Admin profile updated successfully',
            'user' => $user,
        ]);
    }

    public function users(Request $request)
    {
        $users = LsankUser::query()
            ->select(
                'user_id',
                'name',
                'email',
                'phone',
                'user_type',
                'status',
                'created_at'
            )
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function createUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:lsank_users,email',
            'phone' => 'nullable|string|max:30',
            'ic_no' => 'required|string|max:20|unique:lsank_users,ic_no',
            'user_type' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $user = LsankUser::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'ic_no' => $request->ic_no,
            'password' => Hash::make($request->password),
            'user_type' => $request->user_type,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berjaya ditambah',
            'data' => $user,
        ], 201);
    }
}
