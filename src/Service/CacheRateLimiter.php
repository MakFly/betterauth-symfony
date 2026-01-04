<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Service;

use BetterAuth\Core\Interfaces\RateLimiterInterface;
use Psr\Cache\CacheItemPoolInterface;

final class CacheRateLimiter implements RateLimiterInterface
{
    private const CACHE_PREFIX = 'better_auth.rate_limit.';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $data = $this->getData($key);
        if ($data === null) {
            return false;
        }

        if ($data['reset'] <= time()) {
            $this->clear($key);
            return false;
        }

        return $data['attempts'] >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds): int
    {
        $now = time();
        $data = $this->getData($key);

        if ($data === null || $data['reset'] <= $now) {
            $data = [
                'attempts' => 0,
                'reset' => $now + $decaySeconds,
            ];
        }

        $data['attempts']++;

        $ttl = max(1, $data['reset'] - $now);
        $this->saveData($key, $data, $ttl);

        return $data['attempts'];
    }

    public function attempts(string $key): int
    {
        $data = $this->getData($key);
        if ($data === null) {
            return 0;
        }

        if ($data['reset'] <= time()) {
            $this->clear($key);
            return 0;
        }

        return $data['attempts'];
    }

    public function clear(string $key): bool
    {
        return $this->cache->deleteItem($this->getCacheKey($key));
    }

    public function availableIn(string $key): int
    {
        $data = $this->getData($key);
        if ($data === null) {
            return 0;
        }

        $availableIn = $data['reset'] - time();
        if ($availableIn <= 0) {
            $this->clear($key);
            return 0;
        }

        return $availableIn;
    }

    private function getData(string $key): ?array
    {
        $item = $this->cache->getItem($this->getCacheKey($key));
        if (!$item->isHit()) {
            return null;
        }

        $data = $item->get();
        if (!is_array($data) || !isset($data['attempts'], $data['reset'])) {
            return null;
        }

        return $data;
    }

    private function saveData(string $key, array $data, int $ttl): void
    {
        $item = $this->cache->getItem($this->getCacheKey($key));
        $item->set($data);
        $item->expiresAfter($ttl);
        $this->cache->save($item);
    }

    private function getCacheKey(string $key): string
    {
        return self::CACHE_PREFIX . hash('sha256', $key);
    }
}
