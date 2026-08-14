<?php

declare(strict_types=1);

namespace Demo\Security;

use BetterAuth\Symfony\Feature\TotpService;
use Demo\Storage\DoctrineTotpStore;
use Demo\Storage\PendingTotpStore;
use Demo\Storage\SnapshotTotpStore;

final readonly class DemoTotpEnrollmentService
{
    public function __construct(private DoctrineTotpStore $store, private string $secret)
    {
    }

    /** @return array{secret: string, provisioning_uri: string} */
    public function begin(string $userIdentifier, string $label): array
    {
        return $this->pending()->enroll($userIdentifier, $label);
    }

    public function confirm(string $userIdentifier, string $code): bool
    {
        $now = new \DateTimeImmutable();
        $snapshot = $this->store->snapshotPending($userIdentifier, $now);
        if ($snapshot === null || !$this->snapshot($snapshot->ciphertext)->verify($userIdentifier, $code)) {
            return false;
        }

        return $this->store->activatePending($userIdentifier, $snapshot->ciphertext, $now);
    }

    private function pending(): TotpService
    {
        return new TotpService(new PendingTotpStore($this->store), $this->secret);
    }

    private function snapshot(string $ciphertext): TotpService
    {
        return new TotpService(new SnapshotTotpStore($ciphertext), $this->secret);
    }
}
