<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Automatic ID type converter for BetterAuth.
 *
 * Detects whether your User entity uses UUID (string) or INT auto-increment,
 * and provides seamless conversion methods.
 *
 * This allows BetterAuth core (which works with strings) to work transparently
 * with both UUID and INT-based User entities.
 */
class UserIdConverter
{
    private string $idType;
    private string $userClass;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        string $userClass = 'App\Entity\User'
    ) {
        $this->userClass = $userClass;
        $this->idType = $this->detectIdType();
    }

    /**
     * Detect the ID type of the User entity.
     *
     * @return string 'integer' or 'string'
     */
    private function detectIdType(): string
    {
        try {
            $metadata = $this->entityManager->getClassMetadata($this->userClass);
            $idFieldNames = $metadata->getIdentifierFieldNames();

            if (empty($idFieldNames)) {
                throw new \RuntimeException('User entity has no identifier field');
            }

            $idFieldName = $idFieldNames[0];
            $idFieldType = $metadata->getTypeOfField($idFieldName);

            // Map Doctrine types to our internal types
            return match ($idFieldType) {
                'integer', 'bigint', 'smallint' => 'integer',
                'string', 'guid', 'uuid' => 'string',
                default => throw new \RuntimeException(sprintf(
                    'Unsupported ID type "%s" for User entity. Use "integer" or "string".',
                    $idFieldType
                )),
            };
        } catch (\Exception $e) {
            // Default to string (UUID) if detection fails
            return 'string';
        }
    }

    /**
     * Check if User entity uses INT IDs.
     */
    public function usesIntId(): bool
    {
        return $this->idType === 'integer';
    }

    /**
     * Check if User entity uses UUID/string IDs.
     */
    public function usesStringId(): bool
    {
        return $this->idType === 'string';
    }

    /**
     * Convert from database ID to BetterAuth format (always string).
     *
     * @param int|string $id Database ID
     * @return string ID as string for BetterAuth
     */
    public function toAuthId(int|string $id): string
    {
        return (string) $id;
    }

    /**
     * Convert from BetterAuth format (string) to database ID.
     *
     * @param string $authId ID from BetterAuth (string)
     * @return int|string Database ID (int if entity uses int, string otherwise)
     */
    public function toDatabaseId(string $authId): int|string
    {
        if ($this->usesIntId()) {
            if (!is_numeric($authId)) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid user ID "%s". Expected numeric value for INT-based User entity.',
                    $authId
                ));
            }

            return (int) $authId;
        }

        return $authId;
    }

    /**
     * Generate a new ID value.
     *
     * For INT: returns null (database auto-generates)
     * For UUID: generates a new UUID v7 (time-ordered, better for database indexing)
     *
     * @return string|null
     */
    public function generateId(): ?string
    {
        if ($this->usesIntId()) {
            // Auto-increment, database handles this
            return null;
        }

        // Generate UUID v7 (time-ordered for better database performance)
        return $this->generateUuidV7();
    }

    /**
     * Generate a UUID v7 (time-ordered).
     *
     * Uses Symfony's Uuid component if available, otherwise falls back to manual generation.
     */
    private function generateUuidV7(): string
    {
        // Use Symfony's Uuid component if available (preferred)
        if (class_exists(\Symfony\Component\Uid\Uuid::class)) {
            return (string) \Symfony\Component\Uid\Uuid::v7();
        }

        // Fallback: Manual UUID v7 generation (time-ordered)
        // UUID v7: timestamp (48 bits) + random (74 bits)
        $timestamp = (int) (microtime(true) * 1000); // milliseconds since epoch

        $data = pack('J', $timestamp << 16); // 48-bit timestamp in first 6 bytes
        $data = substr($data, 0, 6) . random_bytes(10); // Add 10 random bytes

        // Set version (7) and variant bits
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x70); // Version 7
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // Variant 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Get the detected ID type.
     *
     * @return string 'integer' or 'string'
     */
    public function getIdType(): string
    {
        return $this->idType;
    }
}
