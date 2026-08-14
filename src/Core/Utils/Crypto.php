<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Core\Utils;

use Exception;

/**
 * Cryptographic utilities for secure random generation.
 *
 * This utility class is final to ensure consistent cryptographic behavior.
 */
final class Crypto
{
    /**
     * Generate a cryptographically secure random string.
     *
     * @param int $length The length of the string
     *
     * @return string The random string (hex encoded)
     *
     * @throws Exception
     */
    public static function randomString(int $length = 32): string
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Length must be positive.');
        }

        return bin2hex(random_bytes($length));
    }

    /**
     * Generate a cryptographically secure random token.
     *
     * @param int $bytes Number of random bytes
     *
     * @return string The random token (base64url encoded)
     *
     * @throws Exception
     */
    public static function randomToken(int $bytes = 32): string
    {
        if ($bytes < 1) {
            throw new \InvalidArgumentException('Length must be positive.');
        }

        return self::base64UrlEncode(random_bytes($bytes));
    }

    /**
     * Generate a secure random integer.
     *
     * @param int $min Minimum value
     * @param int $max Maximum value
     *
     * @return int The random integer
     *
     * @throws Exception
     */
    public static function randomInt(int $min, int $max): int
    {
        return random_int($min, $max);
    }

    /**
     * Base64url encode a string.
     *
     * @param string $data The data to encode
     *
     * @return string The base64url encoded string
     */
    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64url decode a string.
     *
     * @param string $data The data to decode
     *
     * @return string The decoded string
     */
    public static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Hash a value using a secure algorithm.
     *
     * @param string $value The value to hash
     * @param string $algorithm The hash algorithm (default: sha256)
     *
     * @return string The hashed value
     */
    public static function hash(string $value, string $algorithm = 'sha256'): string
    {
        return hash($algorithm, $value);
    }

    /**
     * Constant-time string comparison.
     *
     * @param string $known The known string
     * @param string $user The user-supplied string
     *
     * @return bool True if strings are equal, false otherwise
     */
    public static function timingSafeEquals(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /**
     * Derive an encryption key from a secret using HKDF.
     *
     * Uses a zero-length salt (RFC 5869 §3.1) with domain-separation via the $info parameter.
     *
     * @param string $secret The master secret
     * @param int $length Output key length in bytes
     * @param string $info Context/application-specific info for domain separation
     */
    public static function deriveKey(string $secret, int $length = 32, string $info = 'betterauth-token-key'): string
    {
        if ($length < 0) {
            throw new \InvalidArgumentException('Length cannot be negative.');
        }

        return hash_hkdf('sha256', $secret, $length, $info);
    }

    /**
     * Encrypt a value with AES-256-GCM (authenticated encryption).
     *
     * Output format: "enc:v1:" . base64( nonce(12) || ciphertext || tag(16) ).
     *
     * @param string $plaintext The value to encrypt
     * @param string $key Raw 32-byte key (derive it with deriveKey())
     *
     * @throws Exception
     */
    public static function encrypt(string $plaintext, string $key): string
    {
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);

        if ($ciphertext === false) {
            throw new Exception('Encryption failed');
        }

        return 'enc:v1:' . base64_encode($nonce . $ciphertext . $tag);
    }

    /**
     * Decrypt a value produced by encrypt().
     *
     * Values without the "enc:v1:" prefix are returned unchanged, allowing
     * transparent migration of data stored before encryption was enabled.
     *
     * @param string $value The stored value
     * @param string $key Raw 32-byte key
     * @param bool $strict When true, reject values without the "enc:v1:" prefix
     *                      instead of passing them through — use once migration to
     *                      encrypted-at-rest is complete to prevent downgrade (SEC-31).
     *
     * @throws Exception
     */
    public static function decrypt(string $value, string $key, bool $strict = false): string
    {
        if (!str_starts_with($value, 'enc:v1:')) {
            if ($strict) {
                throw new Exception('Expected encrypted value but got plaintext (strict mode)');
            }

            return $value; // legacy plaintext, not yet encrypted
        }

        $raw = base64_decode(substr($value, 7), true);
        if ($raw === false || strlen($raw) < 28) {
            throw new Exception('Invalid ciphertext');
        }

        $nonce = substr($raw, 0, 12);
        $tag = substr($raw, -16);
        $ciphertext = substr($raw, 12, -16);

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($plaintext === false) {
            throw new Exception('Decryption failed');
        }

        return $plaintext;
    }
}
