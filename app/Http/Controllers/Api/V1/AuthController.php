<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserType;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\SocialLoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\Auth\SocialIdentityVerifier;
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
        if ($response = $this->emailPasswordAuthDisabledResponse()) {
            return $response;
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => $request->user_type,
            'phone' => $request->phone,
        ]);

        $this->bootstrapNewUser($user);

        $token = JWTAuth::fromUser($user);

        return $this->created([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Registration successful.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if ($response = $this->emailPasswordAuthDisabledResponse()) {
            return $response;
        }

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

    public function socialLogin(SocialLoginRequest $request, SocialIdentityVerifier $verifier): JsonResponse
    {
        try {
            $identity = $verifier->verify($request->provider, $request->id_token);
        } catch (\Throwable $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        $providerColumn = $identity['provider'] . '_id';
        $user = User::query()
            ->where($providerColumn, $identity['provider_id'])
            ->first();

        if (! $user && $identity['email']) {
            $user = User::query()->where('email', $identity['email'])->first();
        }

        if ($user && $user->{$providerColumn} && $user->{$providerColumn} !== $identity['provider_id']) {
            return $this->error('This email is already linked to a different social account.', 409);
        }

        $isNewUser = false;

        if (! $user) {
            if (! $identity['email']) {
                return $this->error('The social account did not provide an email address.', 422);
            }

            $user = User::create([
                'name' => $this->socialName($identity, $request->name),
                'email' => $identity['email'],
                'password' => Hash::make(Str::random(64)),
                'user_type' => $request->user_type ?: UserType::Individual->value,
                'phone' => $request->phone,
                'avatar' => $identity['avatar'],
                'email_verified_at' => $identity['email_verified'] ? now() : null,
                'auth_provider' => $identity['provider'],
                $providerColumn => $identity['provider_id'],
            ]);

            $this->bootstrapNewUser($user);
            $isNewUser = true;
        } else {
            $updates = [
                'auth_provider' => $identity['provider'],
                $providerColumn => $identity['provider_id'],
            ];

            if (! $user->email_verified_at && $identity['email_verified']) {
                $updates['email_verified_at'] = now();
            }

            if (! $user->avatar && $identity['avatar']) {
                $updates['avatar'] = $identity['avatar'];
            }

            if ((! $user->name || str_starts_with($user->name, 'Apple User')) && $request->name) {
                $updates['name'] = $request->name;
            }

            $user->forceFill($updates)->save();
        }

        if (! $user->fresh()->isActive()) {
            return $this->error('Your account is ' . $user->fresh()->account_status->value . '.', 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = JWTAuth::fromUser($user->fresh());

        return $this->success([
            'user' => new UserResource($user->fresh()),
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'provider' => $identity['provider'],
            'is_new_user' => $isNewUser,
        ], $isNewUser ? 'Account created with social login.' : 'Login successful.');
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
        if ($response = $this->emailPasswordAuthDisabledResponse()) {
            return $response;
        }

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
        if ($response = $this->emailPasswordAuthDisabledResponse()) {
            return $response;
        }

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
        if ($response = $this->emailPasswordAuthDisabledResponse()) {
            return $response;
        }

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

    private function bootstrapNewUser(User $user): void
    {
        $freePlan = \App\Models\SubscriptionPlan::where('name', 'free')->first();
        if ($freePlan) {
            \App\Models\UserSubscription::firstOrCreate([
                'user_id' => $user->id,
                'subscription_plan_id' => $freePlan->id,
            ], [
                'status' => 'active',
                'starts_at' => now(),
            ]);
        }

        $userRole = \App\Models\Role::where('slug', 'user')->first();
        if ($userRole && ! $user->roles()->whereKey($userRole->id)->exists()) {
            $user->roles()->attach($userRole->id);
        }
    }

    private function socialName(array $identity, ?string $requestName): string
    {
        $name = trim((string) ($identity['name'] ?: $requestName));

        if ($name !== '') {
            return $name;
        }

        if ($identity['email']) {
            return Str::of($identity['email'])->before('@')->replace(['.', '_', '-'], ' ')->title()->toString();
        }

        return $identity['provider'] === 'apple' ? 'Apple User' : 'Social User';
    }

    private function emailPasswordAuthDisabledResponse(): ?JsonResponse
    {
        if (config('social_auth.email_password_enabled')) {
            return null;
        }

        return $this->error('Email/password authentication is disabled. Use Google or Apple social login.', 410);
    }
}
