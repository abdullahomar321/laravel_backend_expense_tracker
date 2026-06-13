<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
   public function signup(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|min:6',
        'confirm_password' => 'required|same:password'
    ]);

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'balance' => 0
    ]);

    $token = $user->createToken('mobile_token')->plainTextToken;

    return response()->json([
        'message' => 'User registered successfully',
        'user' => $user,
        'token' => $token
    ], 201);
}
    public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required'
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json([
            'message' => 'Invalid Credentials'
        ], 401);
    }

    $token = $user->createToken('mobile_token')->plainTextToken;

    return response()->json([
        'message' => 'Login Successful',
        'user' => $user,
        'token' => $token
    ]);
}
    public function logout(Request $request){
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'message'=>'Logged Out Successfully',
        ]);
    }

    public function changePassword(Request $request)
{
    $request->validate([
        'old_password' => 'required',
        'new_password' => 'required|min:6|confirmed'
    ]);

    $user = $request->user();

    if (!Hash::check($request->old_password, $user->password)) {
        return response()->json([
            'message' => 'Current password is incorrect'
        ], 400);
    }

    $user->password = Hash::make($request->new_password);
    $user->save();

    return response()->json([
        'message' => 'Password changed successfully'
    ]);
}
public function forgotPassword(Request $request)
{
    $request->validate([
        'email' => 'required|email'
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json([
            'message' => 'Email not found'
        ], 404);
    }

    return response()->json([
        'message' => 'Email verified'
    ]);
}




}