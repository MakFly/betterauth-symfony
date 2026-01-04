<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\License;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * License validation service with dynamic grace period.
 *
 * Grace period is based on activation count:
 * - 1st activation: 24 hours (generous for new customers)
 * - 2nd activation: 12 hours (reduced for multi-server)
 * - 3rd+ activation: 1 hour (minimal for potential abuse)
 *
 * This approach:
 * - Rewards legitimate single-server users
 * - Detects potential license sharing faster
 * - Provides telemetry on activation distribution
 */
final class LicenseValidator
{
    /**
     * Grace periods in seconds based on activation count.
     */
    private const GRACE_PERIODS = [
        1 => 86400,    // 24 hours for 1st activation
        2 => 43200,    // 12 hours for 2nd activation
        3 => 3600,     // 1 hour for 3rd+ activation
    ];

    /**
     * Default grace period for 3+ activations.
     */
    private const DEFAULT_GRACE_PERIOD = 3600; // 1 hour

    /**
     * Cache TTL for valid licenses.
     */
    private const CACHE_TTL = 3600; // 1 hour

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly ?string $licenseKey,
        private readonly ?CacheItemPoolInterface $cache,
        private readonly ?HttpClientInterface $httpClient,
        private readonly string $validationUrl = 'https://api.betterauth.dev/v1/license/validate',
        private readonly bool $offlineMode = false,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Validate the current license.
     */
    public function validate(?string $fingerprint = null, ?string $domain = null): LicenseInfo
    {
        // No license key = free tier
        if (empty($this->licenseKey)) {
            $this->logger->debug('No license key configured, using free tier');

            return LicenseInfo::free();
        }

        // Check cache first
        $cacheKey = $this->getCacheKey($fingerprint);
        $cachedInfo = $this->getCachedLicenseInfo($cacheKey);

        if ($cachedInfo !== null) {
            $this->logger->debug('License info retrieved from cache', [
                'tier' => $cachedInfo->tier,
                'valid' => $cachedInfo->isValid,
            ]);

            return $cachedInfo;
        }

        // Try online validation
        $licenseInfo = $this->validateOnline($fingerprint, $domain);

        if ($licenseInfo !== null) {
            $this->cacheLicenseInfo($cacheKey, $licenseInfo);

            return $licenseInfo;
        }

        // Online validation failed - check grace period
        return $this->handleOfflineValidation($cacheKey, $fingerprint);
    }

    /**
     * Get the grace period based on activation count.
     */
    public function getGracePeriod(int $activationCount): int
    {
        if ($activationCount <= 0) {
            return self::GRACE_PERIODS[1];
        }

        return self::GRACE_PERIODS[$activationCount] ?? self::DEFAULT_GRACE_PERIOD;
    }

    /**
     * Check if currently in grace period.
     */
    public function isInGracePeriod(?string $fingerprint = null): bool
    {
        $cacheKey = $this->getGracePeriodCacheKey($fingerprint);
        $item = $this->cache?->getItem($cacheKey);

        if ($item === null || !$item->isHit()) {
            return false;
        }

        $gracePeriodData = $item->get();

        if (!is_array($gracePeriodData)) {
            return false;
        }

        $startedAt = $gracePeriodData['started_at'] ?? 0;
        $duration = $gracePeriodData['duration'] ?? self::DEFAULT_GRACE_PERIOD;

        return (time() - $startedAt) < $duration;
    }

    /**
     * Get remaining grace period time in seconds.
     */
    public function getRemainingGracePeriod(?string $fingerprint = null): int
    {
        $cacheKey = $this->getGracePeriodCacheKey($fingerprint);
        $item = $this->cache?->getItem($cacheKey);

        if ($item === null || !$item->isHit()) {
            return 0;
        }

        $gracePeriodData = $item->get();

        if (!is_array($gracePeriodData)) {
            return 0;
        }

        $startedAt = $gracePeriodData['started_at'] ?? 0;
        $duration = $gracePeriodData['duration'] ?? self::DEFAULT_GRACE_PERIOD;
        $elapsed = time() - $startedAt;

        return max(0, $duration - $elapsed);
    }

    /**
     * Validate license online.
     */
    private function validateOnline(?string $fingerprint, ?string $domain): ?LicenseInfo
    {
        if ($this->httpClient === null || $this->offlineMode) {
            $this->logger->debug('Online validation skipped', [
                'has_client' => $this->httpClient !== null,
                'offline_mode' => $this->offlineMode,
            ]);

            return null;
        }

        try {
            $response = $this->httpClient->request('POST', $this->validationUrl, [
                'json' => [
                    'license_key' => $this->licenseKey,
                    'fingerprint' => $fingerprint ?? $this->generateFingerprint(),
                    'domain' => $domain ?? $this->getCurrentDomain(),
                ],
                'timeout' => 3, // Fast timeout to not block
            ]);

            $data = $response->toArray();

            if (!isset($data['valid'])) {
                throw new \RuntimeException('Invalid response from license server');
            }

            $licenseInfo = new LicenseInfo(
                tier: $data['tier'] ?? LicenseInfo::TIER_FREE,
                features: $data['features'] ?? [],
                expiresAt: isset($data['expires_at'])
                    ? new \DateTimeImmutable($data['expires_at'])
                    : null,
                activationCount: $data['activation_count'] ?? 1,
                maxActivations: $data['max_activations'] ?? 3,
                isValid: $data['valid'],
                reason: $data['reason'] ?? null,
            );

            $this->logger->info('License validated online', [
                'tier' => $licenseInfo->tier,
                'valid' => $licenseInfo->isValid,
                'activation_count' => $licenseInfo->activationCount,
            ]);

            // Clear grace period on successful validation
            $this->clearGracePeriod($fingerprint);

            return $licenseInfo;
        } catch (\Throwable $e) {
            $this->logger->warning('Online license validation failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Handle offline validation with grace period.
     */
    private function handleOfflineValidation(string $cacheKey, ?string $fingerprint): LicenseInfo
    {
        // Check if we have a previous valid license cached
        $lastKnownInfo = $this->getLastKnownLicenseInfo($fingerprint);

        if ($lastKnownInfo !== null && $this->isInGracePeriod($fingerprint)) {
            $remaining = $this->getRemainingGracePeriod($fingerprint);

            $this->logger->info('Using grace period for license validation', [
                'remaining_seconds' => $remaining,
                'tier' => $lastKnownInfo->tier,
            ]);

            return $lastKnownInfo;
        }

        // Start new grace period if we have a last known license
        if ($lastKnownInfo !== null) {
            $gracePeriod = $this->getGracePeriod($lastKnownInfo->activationCount);
            $this->startGracePeriod($fingerprint, $gracePeriod, $lastKnownInfo->activationCount);

            $this->logger->info('Started grace period', [
                'duration_seconds' => $gracePeriod,
                'activation_count' => $lastKnownInfo->activationCount,
            ]);

            return $lastKnownInfo;
        }

        // No previous license info, fallback to free tier
        $this->logger->warning('No license info available, falling back to free tier');

        return LicenseInfo::free();
    }

    /**
     * Start grace period.
     */
    private function startGracePeriod(?string $fingerprint, int $duration, int $activationCount): void
    {
        if ($this->cache === null) {
            return;
        }

        $cacheKey = $this->getGracePeriodCacheKey($fingerprint);
        $item = $this->cache->getItem($cacheKey);
        $item->set([
            'started_at' => time(),
            'duration' => $duration,
            'activation_count' => $activationCount,
        ]);
        $item->expiresAfter($duration);
        $this->cache->save($item);
    }

    /**
     * Clear grace period.
     */
    private function clearGracePeriod(?string $fingerprint): void
    {
        if ($this->cache === null) {
            return;
        }

        $cacheKey = $this->getGracePeriodCacheKey($fingerprint);
        $this->cache->deleteItem($cacheKey);
    }

    /**
     * Get cached license info.
     */
    private function getCachedLicenseInfo(string $cacheKey): ?LicenseInfo
    {
        if ($this->cache === null) {
            return null;
        }

        $item = $this->cache->getItem($cacheKey);

        if (!$item->isHit()) {
            return null;
        }

        $data = $item->get();

        if (!is_array($data)) {
            return null;
        }

        return $this->arrayToLicenseInfo($data);
    }

    /**
     * Cache license info.
     */
    private function cacheLicenseInfo(string $cacheKey, LicenseInfo $info): void
    {
        if ($this->cache === null) {
            return;
        }

        $item = $this->cache->getItem($cacheKey);
        $item->set($info->toArray());
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        // Also store as last known info
        $lastKnownKey = $this->getLastKnownCacheKey(null);
        $lastKnownItem = $this->cache->getItem($lastKnownKey);
        $lastKnownItem->set($info->toArray());
        $lastKnownItem->expiresAfter(86400 * 30); // 30 days
        $this->cache->save($lastKnownItem);
    }

    /**
     * Get last known license info.
     */
    private function getLastKnownLicenseInfo(?string $fingerprint): ?LicenseInfo
    {
        if ($this->cache === null) {
            return null;
        }

        $cacheKey = $this->getLastKnownCacheKey($fingerprint);
        $item = $this->cache->getItem($cacheKey);

        if (!$item->isHit()) {
            return null;
        }

        $data = $item->get();

        if (!is_array($data)) {
            return null;
        }

        return $this->arrayToLicenseInfo($data);
    }

    /**
     * Convert array to LicenseInfo.
     *
     * @param array<string, mixed> $data
     */
    private function arrayToLicenseInfo(array $data): LicenseInfo
    {
        return new LicenseInfo(
            tier: $data['tier'] ?? LicenseInfo::TIER_FREE,
            features: $data['features'] ?? [],
            expiresAt: isset($data['expires_at'])
                ? new \DateTimeImmutable($data['expires_at'])
                : null,
            activationCount: $data['activation_count'] ?? 1,
            maxActivations: $data['max_activations'] ?? 3,
            isValid: $data['is_valid'] ?? false,
            reason: $data['reason'] ?? null,
        );
    }

    /**
     * Get cache key for license info.
     */
    private function getCacheKey(?string $fingerprint): string
    {
        $hash = hash('sha256', ($this->licenseKey ?? '') . ($fingerprint ?? ''));

        return 'betterauth_license_' . $hash;
    }

    /**
     * Get cache key for grace period.
     */
    private function getGracePeriodCacheKey(?string $fingerprint): string
    {
        $hash = hash('sha256', ($this->licenseKey ?? '') . ($fingerprint ?? ''));

        return 'betterauth_grace_period_' . $hash;
    }

    /**
     * Get cache key for last known license.
     */
    private function getLastKnownCacheKey(?string $fingerprint): string
    {
        $hash = hash('sha256', ($this->licenseKey ?? '') . ($fingerprint ?? ''));

        return 'betterauth_last_known_license_' . $hash;
    }

    /**
     * Generate server fingerprint.
     */
    private function generateFingerprint(): string
    {
        $parts = [
            php_uname('n'), // hostname
            php_uname('s'), // OS
            $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
        ];

        return hash('sha256', implode('|', $parts));
    }

    /**
     * Get current domain.
     */
    private function getCurrentDomain(): string
    {
        return $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    }
}
