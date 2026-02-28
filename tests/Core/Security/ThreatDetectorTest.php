<?php

declare(strict_types=1);

namespace BetterAuth\Tests\Security;

use BetterAuth\Core\Entities\SuspiciousActivity;
use BetterAuth\Core\Interfaces\DeviceInfoRepositoryInterface;
use BetterAuth\Core\Interfaces\SuspiciousActivityRepositoryInterface;
use BetterAuth\Providers\DeviceManagementProvider\DeviceFingerprintService;
use BetterAuth\Providers\DeviceManagementProvider\GeolocationService;
use BetterAuth\Providers\SecurityProvider\ThreatDetector;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ThreatDetectorTest extends TestCase
{
    private ThreatDetector $detector;

    protected function setUp(): void
    {
        $suspiciousRepo = $this->createMock(SuspiciousActivityRepositoryInterface::class);
        $deviceRepo = $this->createMock(DeviceInfoRepositoryInterface::class);
        $geoService = $this->createMock(GeolocationService::class);
        $fingerprintService = $this->createMock(DeviceFingerprintService::class);

        $this->detector = new ThreatDetector(
            $suspiciousRepo,
            $deviceRepo,
            $geoService,
            $fingerprintService,
        );
    }

    public function testDetectNewDevice(): void
    {
        $suspiciousRepo = $this->createMock(SuspiciousActivityRepositoryInterface::class);
        $deviceRepo = $this->createMock(DeviceInfoRepositoryInterface::class);
        $geoService = $this->createMock(GeolocationService::class);
        $fingerprintService = $this->createMock(DeviceFingerprintService::class);

        $fingerprintService->expects($this->once())
            ->method('generate')
            ->willReturn('fingerprint-123');

        $deviceRepo->expects($this->once())
            ->method('findByFingerprint')
            ->willReturn(null);

        $suspiciousRepo->expects($this->once())
            ->method('generateId')
            ->willReturn('activity-123');

        $geoService->expects($this->once())
            ->method('getLocation')
            ->willReturn('Unknown');

        $activity = new SuspiciousActivity(
            id: 'activity-123',
            userId: 'user-123',
            activityType: 'login',
            riskLevel: 'medium',
            ipAddress: '192.168.1.1',
            userAgent: 'New Browser',
            location: 'Unknown',
            detectedAt: (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            status: 'pending',
        );

        $suspiciousRepo->expects($this->once())
            ->method('create')
            ->willReturn($activity);

        $detector = new ThreatDetector(
            $suspiciousRepo,
            $deviceRepo,
            $geoService,
            $fingerprintService,
        );

        $result = $detector->detectSuspiciousActivity(
            userId: 'user-123',
            activityType: 'login',
            ipAddress: '192.168.1.1',
            userAgent: 'New Browser',
        );

        $this->assertInstanceOf(SuspiciousActivity::class, $result);
        $this->assertEquals('medium', $result->riskLevel);
    }
}
