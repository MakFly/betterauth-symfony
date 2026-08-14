<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Token;

use BetterAuth\Symfony\Core\TokenService;
use BetterAuth\Symfony\Core\Utils\Crypto;

final class RefreshTokenManager
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly RefreshTokenStoreInterface $store,
        private readonly int $accessTtl,
        private readonly int $refreshTtl,
        private readonly bool $enabled = true,
    ) {
    }

    /** @param array<string, mixed> $claims */
    public function issue(string $userIdentifier, array $claims = []): TokenPair
    {
        $accessToken = $this->tokens->createAccessToken($userIdentifier, $claims, $this->accessTtl);

        if (!$this->enabled) {
            return new TokenPair($accessToken, null, $this->accessTtl);
        }

        $rawToken = Crypto::randomToken(32);
        $this->store->store(new RefreshTokenRecord(
            $this->hash($rawToken),
            $userIdentifier,
            new \DateTimeImmutable(sprintf('+%d seconds', $this->refreshTtl)),
        ));

        return new TokenPair($accessToken, $rawToken, $this->accessTtl);
    }

    /** @param array<string, mixed> $claims */
    public function rotate(string $rawToken, array $claims = []): RefreshRotationOutcome
    {
        if (!$this->enabled) {
            return RefreshRotationOutcome::invalid();
        }

        $replacement = Crypto::randomToken(32);
        $currentHash = $this->hash($rawToken);
        $result = $this->store->rotate(
            $currentHash,
            $this->hash($replacement),
            new \DateTimeImmutable(sprintf('+%d seconds', $this->refreshTtl)),
            new \DateTimeImmutable(),
        );

        if ($result->status === RefreshRotationStatus::Replayed) {
            if ($result->record !== null) {
                $this->store->revokeForUser($result->record->userIdentifier);
            }

            return RefreshRotationOutcome::replayed();
        }
        if ($result->status !== RefreshRotationStatus::Rotated || $result->record === null) {
            return RefreshRotationOutcome::invalid();
        }
        $record = $result->record;

        return RefreshRotationOutcome::rotated(new TokenPair(
            $this->tokens->createAccessToken($record->userIdentifier, $claims, $this->accessTtl),
            $replacement,
            $this->accessTtl,
        ));
    }

    public function revoke(string $rawToken): bool
    {
        return $this->store->revoke($this->hash($rawToken));
    }

    public function revokeAll(string $userIdentifier): void
    {
        $this->store->revokeForUser($userIdentifier);
    }

    private function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
