<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class SocialIdentityVerifier
{
    public function verify(string $provider, string $idToken): array
    {
        $config = config("social_auth.providers.{$provider}");

        if (! is_array($config)) {
            throw new InvalidArgumentException('Unsupported social login provider.');
        }

        $clientIds = array_values($config['client_ids'] ?? []);
        if ($clientIds === []) {
            throw new RuntimeException("No {$provider} social login client IDs are configured.");
        }

        $claims = $this->decodeIdToken($provider, $idToken, (string) $config['jwks_uri']);

        $this->assertIssuer($claims, (array) ($config['issuer'] ?? []));
        $this->assertAudience($claims['aud'] ?? null, $clientIds);

        $providerId = trim((string) ($claims['sub'] ?? ''));
        if ($providerId === '') {
            throw new RuntimeException('The social identity token is missing a subject.');
        }

        $email = isset($claims['email']) ? trim((string) $claims['email']) : null;
        if ($email !== null && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('The social identity token contains an invalid email.');
        }

        return [
            'provider' => $provider,
            'provider_id' => $providerId,
            'email' => $email !== '' ? $email : null,
            'email_verified' => $this->truthy($claims['email_verified'] ?? false),
            'name' => isset($claims['name']) ? trim((string) $claims['name']) : null,
            'avatar' => isset($claims['picture']) ? trim((string) $claims['picture']) : null,
            'claims' => $claims,
        ];
    }

    private function decodeIdToken(string $provider, string $idToken, string $jwksUri): array
    {
        try {
            return $this->decodeWithJwks($provider, $idToken, $jwksUri);
        } catch (Throwable $firstException) {
            Cache::forget($this->cacheKey($provider));

            try {
                return $this->decodeWithJwks($provider, $idToken, $jwksUri);
            } catch (Throwable) {
                throw new RuntimeException('The social identity token could not be verified.', previous: $firstException);
            }
        }
    }

    private function decodeWithJwks(string $provider, string $idToken, string $jwksUri): array
    {
        JWT::$leeway = 60;

        $decoded = JWT::decode($idToken, JWK::parseKeySet($this->jwks($provider, $jwksUri)));
        $claims = json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($claims)) {
            throw new RuntimeException('The social identity token payload is invalid.');
        }

        return $claims;
    }

    private function jwks(string $provider, string $jwksUri): array
    {
        return Cache::remember($this->cacheKey($provider), now()->addHours(6), function () use ($jwksUri): array {
            $response = Http::timeout(10)->acceptJson()->get($jwksUri);

            if ($response->failed()) {
                throw new RuntimeException('Could not load social provider public keys.');
            }

            $keys = $response->json();

            if (! is_array($keys) || ! is_array($keys['keys'] ?? null)) {
                throw new RuntimeException('Social provider public keys response is invalid.');
            }

            return $keys;
        });
    }

    private function assertIssuer(array $claims, array $issuers): void
    {
        if (! in_array((string) ($claims['iss'] ?? ''), $issuers, true)) {
            throw new RuntimeException('The social identity token issuer is invalid.');
        }
    }

    private function assertAudience(mixed $audience, array $clientIds): void
    {
        $audiences = is_array($audience) ? $audience : [(string) $audience];

        if (array_intersect($audiences, $clientIds) === []) {
            throw new RuntimeException('The social identity token audience is invalid.');
        }
    }

    private function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    private function cacheKey(string $provider): string
    {
        return "social_auth_jwks_{$provider}";
    }
}
