<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function csrfToken(Request $request): JsonResponse
    {
        $request->session()->regenerateToken();

        return new JsonResponse(['csrf_token' => csrf_token()]);
    }

    public function user(Request $request): JsonResponse
    {
        if (! Auth::check()) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        return new JsonResponse(['data' => $this->serializeUser($request->user())]);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return new JsonResponse(['message' => 'Please enter a valid email and password.', 'errors' => $validator->errors()], 422);
        }

        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return new JsonResponse(['message' => 'The provided credentials do not match our records.'], 422);
        }

        $request->session()->regenerate();

        return new JsonResponse([
            'message' => 'Logged in.',
            'data' => [
                'user' => $this->serializeUser($request->user()),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return new JsonResponse([
            'message' => 'Logged out.',
            'csrf_token' => csrf_token(),
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
