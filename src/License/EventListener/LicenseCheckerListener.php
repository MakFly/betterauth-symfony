<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\License\EventListener;

use BetterAuth\Symfony\License\Attribute\RequiresLicense;
use BetterAuth\Symfony\License\FeatureGate;
use BetterAuth\Symfony\License\LicenseFeatureException;
use BetterAuth\Symfony\License\LicenseInfo;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event listener that checks license requirements on controllers.
 *
 * Intercepts requests to controllers marked with #[RequiresLicense]
 * and returns a 402 Payment Required response if license is insufficient.
 */
final class LicenseCheckerListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly FeatureGate $featureGate,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 10],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();

        // Handle array callables
        if (is_array($controller)) {
            $controllerClass = $controller[0];
            $methodName = $controller[1];
        } elseif (is_object($controller) && method_exists($controller, '__invoke')) {
            $controllerClass = $controller;
            $methodName = '__invoke';
        } else {
            return;
        }

        $reflectionClass = new \ReflectionClass($controllerClass);
        $reflectionMethod = $reflectionClass->getMethod($methodName);

        // Check class-level attribute first
        $classAttributes = $reflectionClass->getAttributes(RequiresLicense::class);
        $methodAttributes = $reflectionMethod->getAttributes(RequiresLicense::class);

        $attributes = array_merge($classAttributes, $methodAttributes);

        if (empty($attributes)) {
            return;
        }

        // Check each license requirement
        foreach ($attributes as $attribute) {
            /** @var RequiresLicense $requirement */
            $requirement = $attribute->newInstance();

            try {
                if ($requirement->feature !== null) {
                    $this->featureGate->requireFeature($requirement->feature);
                } else {
                    $this->checkTier($requirement->tier);
                }
            } catch (LicenseFeatureException $e) {
                $event->setController(function () use ($e, $requirement): JsonResponse {
                    return new JsonResponse([
                        'error' => 'license_required',
                        'message' => $e->getMessage(),
                        'required_tier' => $e->getRequiredTier(),
                        'current_tier' => $e->currentTier,
                        'feature' => $requirement->feature,
                        'upgrade_url' => $e->getUpgradeUrl(),
                    ], 402);
                });

                return;
            }
        }
    }

    /**
     * Check if current license meets tier requirement.
     *
     * @throws LicenseFeatureException
     */
    private function checkTier(string $requiredTier): void
    {
        $licenseInfo = $this->featureGate->getLicenseInfo();

        $tierOrder = [
            LicenseInfo::TIER_FREE => 0,
            LicenseInfo::TIER_PRO => 1,
            LicenseInfo::TIER_ENTERPRISE => 2,
        ];

        $currentTierLevel = $tierOrder[$licenseInfo->tier] ?? 0;
        $requiredTierLevel = $tierOrder[$requiredTier] ?? 1;

        if ($currentTierLevel < $requiredTierLevel) {
            throw new LicenseFeatureException(
                sprintf('This feature requires a %s license', $requiredTier),
                $requiredTier,
                $licenseInfo->tier
            );
        }
    }
}
