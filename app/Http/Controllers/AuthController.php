<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->email,
            $request->password
        );

        return response()->json([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->sendResetLink($request->email);

        return response()->json(['message' => 'Password reset link sent.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword($request->only(
            'token', 'email', 'password', 'password_confirmation'
        ));

        return response()->json(['message' => 'Password has been reset.']);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword($request->user(), $request->password);

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
