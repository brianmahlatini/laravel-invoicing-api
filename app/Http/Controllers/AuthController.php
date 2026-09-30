<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:254', 'unique:users,email'],
            'password' => ['required', Password::min(12)],
        ]);
        $user = User::create($data);

        return response()->json(['user' => $user->only('id', 'name', 'email'), 'token' => $this->issueToken($user)], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $data['email'])->first();
        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        return response()->json(['user' => $user->only('id', 'name', 'email'), 'token' => $this->issueToken($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete(); // revoke this device's token only

        return response()->json(null, 204);
    }

    private function issueToken(User $user): string
    {
        return $user->createToken('api', ['*'], now()->addDays(7))->plainTextToken; // tokens expire
    }
}
