<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\TokenExtractor;

use Symfony\Component\HttpFoundation\Request;

interface TokenExtractorInterface
{
    public function extract(Request $request): ?string;
}
