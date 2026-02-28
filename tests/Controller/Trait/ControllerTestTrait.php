<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Controller\Trait;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Helper trait to set up a minimal Symfony container for controller unit tests.
 * Required because AbstractController::json() depends on container services.
 */
trait ControllerTestTrait
{
    /**
     * Configure a minimal container so AbstractController::json() works without a full Symfony kernel.
     */
    private function setUpControllerContainer(object $controller): void
    {
        $serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()]
        );

        $container = new Container();
        $container->set('serializer', $serializer);

        /** @phpstan-ignore-next-line */
        $controller->setContainer($container);
    }
}
