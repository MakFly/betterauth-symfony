<?php

declare(strict_types=1);

namespace Demo\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class ApiRateLimiter
{
    public function __construct(private RateLimiterFactory $ipLimiter, private RateLimiterFactory $identityLimiter)
    {
    }

    public function accepts(Request $request, ?string $identity = null): bool
    {
        $ip = $request->getClientIp() ?? 'unknown';
        if (!$this->ipLimiter->create($ip)->consume()->isAccepted()) {
            return false;
        }
        if ($identity === null || $identity === '') {
            return true;
        }

        return $this->identityLimiter->create(hash('sha256', strtolower($identity)))->consume()->isAccepted();
    }
}
