<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Validate credentials, check status, issue Sanctum token.
     *
     * @return array{token: string, user: User}
     * @throws ValidationException
     */
    public function login(string $email, string $password): array
    {
        $user = User::with('role.permissions', 'customer')
            ->where('email', $email)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status === UserStatus::Suspended) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been suspended.'],
            ]);
        }

        if ($user->status === UserStatus::Inactive) {
            throw ValidationException::withMessages([
                'email' => ['Your account is inactive.'],
            ]);
        }

        // Mark invited → active on first successful login
        if ($user->status === UserStatus::Invited) {
            $user->update(['status' => UserStatus::Active]);
        }

        if ($user->isClientUser() && !$user->customer?->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your company account has been deactivated. Please contact support.'],
            ]);
        }

        // TODO: Add gate: do not allow users whos customers are not onboarded

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('api')->plainTextToken;

        return compact('token', 'user');
    }

    /**
     * Revoke the current token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Send password reset link via Laravel's broker.
     *
     * @throws ValidationException
     */
    public function sendResetLink(string $email): void
    {
        $status = Password::sendResetLink(['email' => $email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    /**
     * Reset password using broker token.
     *
     * @throws ValidationException
     */
    public function resetPassword(array $data): void
    {
        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])
                    ->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => [__($status)],
            ]);
        }
    }

    /**
     * Change authenticated user's own password.
     */
    public function changePassword(User $user, string $newPassword): void
    {
        $user->update(['password' => Hash::make($newPassword)]);

        // Revoke all other tokens — force re-login on other devices
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
    }
}
