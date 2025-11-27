<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\TokenExtractor;

use Symfony\Component\HttpFoundation\Request;

/**
 * Chains multiple token extractors.
 * Similar to Lexik's ChainTokenExtractor.
 *
 * Tries each extractor in order until one returns a token.
 * Useful for supporting multiple token sources (header, cookie, query param).
 *
 * Example:
 *   $chain = new ChainTokenExtractor([
 *       new AuthorizationHeaderTokenExtractor(),
 *       new CookieTokenExtractor(),
 *   ]);
 */
final class ChainTokenExtractor implements TokenExtractorInterface
{
    /** @var TokenExtractorInterface[] */
    private array $extractors;

    /**
     * @param TokenExtractorInterface[] $extractors
     */
    public function __construct(array $extractors = [])
    {
        $this->extractors = $extractors;
    }

    /**
     * Add an extractor to the chain.
     */
    public function addExtractor(TokenExtractorInterface $extractor): self
    {
        $this->extractors[] = $extractor;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * Get all registered extractors.
     *
     * @return TokenExtractorInterface[]
     */
    public function getExtractors(): array
    {
        return $this->extractors;
    }

    /**
     * Remove all extractors.
     */
    public function clearExtractors(): self
    {
        $this->extractors = [];

        return $this;
    }
}

