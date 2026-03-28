<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateUserAction;
use App\Actions\LoginUserAction;
use App\Dtos\AccessItem;
use App\Dtos\LoginUserItem;
use App\Dtos\NewUserItem;
use App\Enums\AccessType;
use App\Events\UserAccessEvent;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResendVerificationRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Requests\Api\V1\VerifyEmailRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Throwable;

final class AuthController extends ApiController
{
    public function register(RegisterRequest $request, CreateUserAction $action): JsonResponse
    {
        $token = '';
        $item = NewUserItem::from($request);

        try {
            $user = $action->handle($item, $token);
        } catch (Throwable $e) {
            return $this->error(
                $e->getMessage(),
                $e->getCode(),
            );
        }

        return $this->created([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'User registered successfully. Please check your email to verify your account.');
    }

    public function login(LoginRequest $request, LoginUserAction $action): JsonResponse
    {
        $item = LoginUserItem::from($request)
            ->withClientInfo(
                $request->ip() ?? '0.0.0.0',
                $request->userAgent() ?? 'N/A',
            );

        $token = '';
        $user = $action->handle($item, $token);
        if (blank($user)) {
            return $this->unauthorized($action->getError());
        }

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Login successful');
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()->delete();

        event(new UserAccessEvent(new AccessItem(
            userId: $user->id,
            type: AccessType::LOGOUT,
            ipAddress: $request->ip() ?? '0.0.0.0',
            agent: $request->userAgent() ?? 'N/A',
            loginAt: now(),
        )));

        return $this->success(message: 'Logged out successfully');
    }

    public function me(Request $request): JsonResponse
    {
        $request->validate([
            'full' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        if ($request->boolean('full') && filled($user)) {
            $user->load('workspace.members');
        }

        return $this->success($user);
    }

    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->success(message: 'Email already verified');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $this->success(message: 'Email verified successfully');
    }

    public function resendVerificationEmail(ResendVerificationRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->email)->first();

        if (! $user) {
            return $this->notFound('User not found');
        }

        if ($user->hasVerifiedEmail()) {
            return $this->error('Email already verified', 400);
        }

        $user->sendEmailVerificationNotification();

        return $this->success(message: 'Verification email sent successfully');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return $this->success(message: 'Password reset link sent to your email');
        }

        return $this->error('Unable to send reset link', 500);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            static function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(message: 'Password reset successfully');
        }

        return $this->error(
            match ($status) {
                Password::INVALID_TOKEN => 'Invalid or expired reset token',
                Password::INVALID_USER => 'User not found',
                default => 'Unable to reset password',
            },
            400
        );
    }
}
