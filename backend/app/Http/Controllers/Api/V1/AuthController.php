<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\LoginAction;
use App\Actions\Auth\RegisterAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginAction $loginAction,
        private readonly RegisterAction $registerAction,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->loginAction->handle(
            $request->validated('email'),
            $request->validated('password'),
        );

        return (new UserResource($result['user']))->response()->setStatusCode(200);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(null, 204);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->registerAction->handle($request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function user(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->loadMissing(['settings', 'roles']);

        $data = (new UserResource($user))->toArray($request);

        $impersonatorId = $request->session()->get('impersonator_id');
        if ($impersonatorId !== null) {
            $data['impersonating'] = true;
            $data['impersonator_id'] = $impersonatorId;
        }

        return response()->json(['data' => $data]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        return response()->json(['message' => __($status)]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)]);
                $user->save();
                event(new PasswordReset($user));
            },
        );

        return response()->json(['message' => __($status)]);
    }

    public function verifyEmail(EmailVerificationRequest $request): JsonResponse
    {
        $request->fulfill();

        return response()->json(['message' => 'Email verified successfully.']);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent.']);
    }

    public function setPassword(SetPasswordRequest $request): JsonResponse
    {
        $user = User::where('invite_token', $request->validated('token'))
            ->whereNotNull('invite_token')
            ->first();

        if ($user === null) {
            return response()->json(['message' => 'Invalid or expired token.'], 422);
        }

        $user->forceFill([
            'password'          => Hash::make($request->validated('password')),
            'invite_token'      => null,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);
        $user->save();

        Auth::login($user);

        return response()->json(['message' => 'Password set successfully.']);
    }

    public function registrationStatus(): JsonResponse
    {
        return response()->json([
            'registration_open' => config('auth.registration_open', false),
        ]);
    }
}
