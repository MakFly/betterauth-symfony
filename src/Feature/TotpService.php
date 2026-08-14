<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature;

use BetterAuth\Symfony\Core\Utils\Crypto;
use BetterAuth\Symfony\Feature\Port\TotpStoreInterface;

final readonly class TotpService
{
    public function __construct(private TotpStoreInterface $store, private string $secret, private string $issuer = 'BetterAuth')
    {
        if (strlen($this->secret) < 32) {
            throw new \InvalidArgumentException('The BetterAuth secret must be at least 32 bytes for TOTP encryption.');
        }
    }

    /** @return array{secret: string, provisioning_uri: string} */
    public function enroll(string $userIdentifier, string $label): array
    {
        $secret = $this->base32Encode(random_bytes(20));
        $this->store->save($userIdentifier, Crypto::encrypt($secret, $this->encryptionKey()));

        return [
            'secret' => $secret,
            'provisioning_uri' => sprintf('otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA256&digits=6&period=30', rawurlencode($this->issuer), rawurlencode($label), $secret, rawurlencode($this->issuer)),
        ];
    }

    public function verify(string $userIdentifier, string $code, int $window = 1): bool
    {
        if (preg_match('/^[0-9]{6}$/D', $code) !== 1 || $window < 0 || $window > 2) {
            return false;
        }
        $ciphertext = $this->store->load($userIdentifier);
        if ($ciphertext === null) {
            return false;
        }
        try {
            $secret = Crypto::decrypt($ciphertext, $this->encryptionKey(), true);
        } catch (\Exception) {
            return false;
        }

        $counter = intdiv(time(), 30);
        for ($offset = -$window; $offset <= $window; ++$offset) {
            if (hash_equals($this->code($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    private function code(string $secret, int $counter): string
    {
        $binarySecret = $this->base32Decode($secret);
        $hash = hash_hmac('sha256', pack('N2', intdiv($counter, 4294967296), $counter % 4294967296), $binarySecret, true);
        $offset = ord($hash[-1]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function encryptionKey(): string
    {
        return Crypto::deriveKey($this->secret, 32, 'betterauth-symfony/totp-seed/v1');
    }

    private function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= substr($alphabet, $this->binaryValue(str_pad($chunk, 5, '0')), 1);
        }

        return $encoded;
    }

    private function base32Decode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(strtoupper($value)) as $character) {
            $index = strpos($alphabet, $character);
            if ($index === false) {
                throw new \InvalidArgumentException('Invalid TOTP secret.');
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }
        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $byte = $this->binaryValue($chunk);
                if ($byte < 0 || $byte > 255) {
                    throw new \LogicException('Invalid TOTP byte.');
                }
                $decoded .= chr($byte);
            }
        }

        return $decoded;
    }

    private function binaryValue(string $bits): int
    {
        $value = 0;
        foreach (str_split($bits) as $bit) {
            $value = ($value << 1) | ($bit === '1' ? 1 : 0);
        }

        return $value;
    }
}
