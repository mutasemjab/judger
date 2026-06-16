<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends BaseApiController
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => $request->user_type,
            'phone' => $request->phone,
        ]);

        $freePlan = \App\Models\SubscriptionPlan::where('name', 'free')->first();
        if ($freePlan) {
            \App\Models\UserSubscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $freePlan->id,
                'status' => 'active',
                'starts_at' => now(),
            ]);
        }

        $userRole = \App\Models\Role::where('slug', 'user')->first();
        if ($userRole) {
            $user->roles()->attach($userRole->id);
        }

        $token = JWTAuth::fromUser($user);

        return $this->created([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Registration successful.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!$token = auth('api')->attempt($credentials)) {
            return $this->error('Invalid credentials.', 401);
        }

        $user = auth('api')->user();

        if (!$user->isActive()) {
            auth('api')->logout();
            return $this->error('Your account is ' . $user->account_status->value . '.', 403);
        }

        $user->update(['last_login_at' => now()]);

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ], 'Login successful.');
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();
        return $this->success(null, 'Logged out successfully.');
    }

    public function refresh(): JsonResponse
    {
        try {
            $token = auth('api')->refresh();
            return $this->success([
                'token' => $token,
                'token_type' => 'Bearer',
            ], 'Token refreshed.');
        } catch (\Throwable) {
            return $this->error('Could not refresh token.', 401);
        }
    }

    public function me(): JsonResponse
    {
        return $this->success(new UserResource(auth('api')->user()));
    }

    public function updateMe(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'language' => 'nullable|string|max:10',
            'theme' => 'nullable|in:light,dark,system',
            'notification_preferences' => 'nullable|array',
            'biometric_enabled' => 'nullable|boolean',
        ]);

        $user->update($validated);

        return $this->success(new UserResource($user->fresh()), 'Profile updated.');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth('api')->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->error('Current password is incorrect.', 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return $this->success(null, 'Password changed successfully.');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::broker()->sendResetLink(
            $request->only('email')
        );

        if (in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER], true)) {
            return $this->success(null, 'If this email exists, a reset link has been sent.');
        }

        return $this->error(__($status), 422);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error(__($status), 422);
        }

        return $this->success(null, 'Password reset successful.');
    }
}
