<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\TokenExtractor;

use Symfony\Component\HttpFoundation\Request;

final readonly class ChainTokenExtractor implements TokenExtractorInterface
{
    /** @param iterable<TokenExtractorInterface> $extractors */
    public function __construct(private iterable $extractors)
    {
    }

    public function extract(Request $request): ?string
    {
        foreach ($this->extractors as $extractor) {
            $token = $extractor->extract($request);
            if ($token !== null) {
                return $token;
            }
        }

        return null;
    }
}
