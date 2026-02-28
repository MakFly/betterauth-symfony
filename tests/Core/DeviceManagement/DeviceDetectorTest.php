<?php

declare(strict_types=1);

namespace BetterAuth\Tests\DeviceManagement;

use BetterAuth\Providers\DeviceManagementProvider\DeviceDetector;
use PHPUnit\Framework\TestCase;

class DeviceDetectorTest extends TestCase
{
    private DeviceDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new DeviceDetector();
    }

    public function testDetectChrome(): void
    {
        $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        $result = $this->detector->detect($userAgent);

        $this->assertEquals('desktop', $result['device_type']);
        $this->assertEquals('Chrome', $result['browser']);
        $this->assertEquals('macOS', $result['os']);
    }

    public function testDetectMobile(): void
    {
        $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1';

        $result = $this->detector->detect($userAgent);

        $this->assertEquals('mobile', $result['device_type']);
        $this->assertEquals('iOS', $result['os']);
    }
}
