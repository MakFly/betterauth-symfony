<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Core;

use BetterAuth\Symfony\Core\Exceptions\InvalidTokenException;
use BetterAuth\Symfony\Core\Exceptions\TokenExpiredException;
use BetterAuth\Symfony\Core\Interfaces\TokenSignerInterface;
use ParagonIE\Paseto\Builder;
use ParagonIE\Paseto\Exception\PasetoException;
use ParagonIE\Paseto\Keys\SymmetricKey;
use ParagonIE\Paseto\Parser;
use ParagonIE\Paseto\Protocol\Version4;
use ParagonIE\Paseto\ProtocolCollection;
use ParagonIE\Paseto\Rules\IssuedBy;
use ParagonIE\Paseto\Rules\NotExpired;

/**
 * Token service using official Paseto V4 library (paragonie/paseto).
 *
 * Paseto V4 provides:
 * - XChaCha20-Poly1305 encryption (256-bit)
 * - Ed25519 signatures
 * - Built-in expiration handling
 * - Cryptographically secure and audited implementation
 *
 * This service is final to ensure consistent token security behavior.
 */
final class TokenService implements TokenSignerInterface
{
    private const ISSUER = 'betterauth';

    private SymmetricKey $key;
    private readonly string $issuer;

    public function __construct(
        string $secretKey,
        string $issuer = self::ISSUER,
        private readonly string $userIdClaim = 'sub',
        private readonly int $maxTokenLength = 8192,
        private readonly int $maxJsonLength = 4096,
        private readonly int $maxClaimCount = 32,
        private readonly int $maxClaimDepth = 4,
    ) {
        if (strlen($secretKey) < 32) {
            throw new \InvalidArgumentException('Secret key must be at least 32 characters');
        }

        $this->issuer = $issuer;

        foreach ([$maxTokenLength, $maxJsonLength, $maxClaimCount, $maxClaimDepth] as $limit) {
            if ($limit < 1) {
                throw new \InvalidArgumentException('PASETO parser limits must be positive.');
            }
        }

        // Create symmetric key from secret (32 bytes for V4) using HKDF for proper key derivation
        $keyMaterial = \BetterAuth\Symfony\Core\Utils\Crypto::deriveKey($secretKey, 32, 'betterauth-token-key');
        $this->key = new SymmetricKey($keyMaterial);
    }

    /**
     * Sign a payload and create a Paseto V4 local token.
     */
    public function sign(array $payload, int $expiresIn): string
    {
        $now = new \DateTimeImmutable();
        $expiration = $now->modify("+{$expiresIn} seconds");

        $builder = Builder::getLocal($this->key, new Version4());

        // Set standard claims
        $builder
            ->setIssuedAt($now)
            ->setExpiration($expiration)
            ->setIssuer($this->issuer);

        // Set subject (user ID)
        if (isset($payload['sub']) && (is_string($payload['sub']) || is_int($payload['sub']))) {
            $builder->setSubject((string) $payload['sub']);
        }

        // Set token type
        if (isset($payload['type'])) {
            $builder->setClaims(['type' => $payload['type']]);
        }

        // Set additional data
        if (isset($payload['data']) && is_array($payload['data'])) {
            $builder->setClaims(['data' => $payload['data']]);
        }

        // Add any other custom claims
        foreach ($payload as $key => $value) {
            if (!in_array($key, ['sub', 'type', 'data', 'iat', 'exp', 'iss'], true)) {
                $builder->setClaims([$key => $value]);
            }
        }

        return $builder->toString();
    }

    /**
     * Create a PASETO v4.local access token for an application user identifier.
     *
     * @param array<string, mixed> $claims
     */
    public function createAccessToken(string $subject, array $claims, int $expiresIn): string
    {
        return $this->sign([
            'sub' => $subject,
            $this->userIdClaim => $subject,
            'type' => 'access',
        ] + $claims, $expiresIn);
    }

    /**
     * Parse and validate a PASETO v4.local access token.
     *
     * @return array<string, mixed>
     */
    public function parseAccessToken(string $token): array
    {
        $claims = $this->verify($token);

        if (($claims['type'] ?? null) !== 'access') {
            throw new InvalidTokenException('The PASETO is not an access token.');
        }
        if (!isset($claims[$this->userIdClaim]) || !is_string($claims[$this->userIdClaim]) || $claims[$this->userIdClaim] === '') {
            throw new InvalidTokenException(sprintf('The PASETO does not contain the "%s" claim.', $this->userIdClaim));
        }

        return $claims;
    }

    /**
     * Verify and decode a Paseto V4 token.
     *
     * @throws InvalidTokenException If token is invalid
     * @throws TokenExpiredException If token has expired
     */
    public function verify(string $token): array
    {
        $this->assertTokenLength($token);

        try {
            $parser = $this->newParser()
                ->addRule(new NotExpired())
                ->addRule(new IssuedBy($this->issuer));

            $parsedToken = $parser->parse($token);
            $claims = $this->normaliseClaims($parsedToken->getClaims());
            if ($claims === null) {
                throw new InvalidTokenException('The PASETO claims are malformed.');
            }

            return [
                'sub' => $claims['sub'] ?? '',
                'iat' => $this->timestamp($claims['iat'] ?? null, time()),
                'exp' => $this->timestamp($claims['exp'] ?? null, time()),
                'type' => $claims['type'] ?? 'access',
                'data' => $claims['data'] ?? null,
            ] + $claims;

        } catch (TokenExpiredException|InvalidTokenException $e) {
            throw $e;
        } catch (PasetoException $e) {
            // Check if it's an expiration error
            if (str_contains($e->getMessage(), 'expired') || str_contains($e->getMessage(), 'Expir')) {
                throw new TokenExpiredException('Token has expired');
            }

            throw new InvalidTokenException('The PASETO is invalid.');
        } catch (\Throwable $e) {
            throw new InvalidTokenException('The PASETO is invalid.');
        }
    }

    /**
     * Decode a token without verification (for inspection only).
     * WARNING: Do not trust the data from this method for authentication!
     */
    public function decode(string $token): ?array
    {
        if (strlen($token) > $this->maxTokenLength) {
            return null;
        }

        try {
            // Parse without validation rules to just decode
            $parser = $this->newParser();
            $parsedToken = $parser->parse($token);
            $claims = $this->normaliseClaims($parsedToken->getClaims());
            if ($claims === null) {
                return null;
            }

            return [
                'sub' => $claims['sub'] ?? '',
                'iat' => $this->timestamp($claims['iat'] ?? null, null),
                'exp' => $this->timestamp($claims['exp'] ?? null, null),
                'type' => $claims['type'] ?? 'access',
                'data' => $claims['data'] ?? null,
            ] + $claims;

        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Check if a token is expired without full verification.
     */
    public function isExpired(string $token): bool
    {
        $payload = $this->decode($token);
        if ($payload === null) {
            return true;
        }

        return isset($payload['exp']) && $payload['exp'] < time();
    }

    /** @return array<string, mixed>|null */
    private function normaliseClaims(mixed $claims): ?array
    {
        if (!is_array($claims)) {
            return null;
        }

        $normalised = [];
        foreach ($claims as $key => $value) {
            if (is_string($key)) {
                $normalised[$key] = $value;
            }
        }

        return $normalised;
    }

    private function timestamp(mixed $value, ?int $fallback): ?int
    {
        if (!is_string($value)) {
            return $fallback;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? $fallback : $timestamp;
    }

    private function assertTokenLength(string $token): void
    {
        if ($token === '' || strlen($token) > $this->maxTokenLength) {
            throw new InvalidTokenException('The PASETO length is invalid.');
        }
    }

    private function newParser(): Parser
    {
        return Parser::getLocal($this->key, ProtocolCollection::v4())
            ->setMaxJsonLength($this->parserLimit($this->maxJsonLength))
            ->setMaxClaimCount($this->parserLimit($this->maxClaimCount))
            ->setMaxClaimDepth($this->claimDepthLimit($this->maxClaimDepth));
    }

    /** @return int<1, max> */
    private function parserLimit(int $limit): int
    {
        if ($limit < 1) {
            throw new \LogicException('Parser limits must be positive.');
        }

        return $limit;
    }

    /** @return int<1, 2147483647> */
    private function claimDepthLimit(int $limit): int
    {
        return min($this->parserLimit($limit), 2147483647);
    }
}
