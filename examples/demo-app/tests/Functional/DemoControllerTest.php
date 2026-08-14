<?php

declare(strict_types=1);

namespace Demo\Tests\Functional;

use BetterAuth\Symfony\Feature\OneTimeTokenService;
use BetterAuth\Symfony\Token\RefreshTokenRecord;
use Demo\Storage\DoctrineOneTimeTokenStore;
use Demo\Storage\DoctrineRefreshTokenStore;
use Demo\Storage\DoctrineTotpStore;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DemoControllerTest extends WebTestCase
{
    private string $testIp;

    protected function setUp(): void
    {
        $this->testIp = sprintf('198.18.%d.%d', random_int(1, 254), random_int(1, 254));
    }
    public function testRegistrationValidationUsesProblemJson(): void
    {
        $client = self::createClient();
        $this->json($client, 'POST', '/api/register', ['email' => 'not-an-email', 'password' => 'short']);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type', 'application/problem+json');
        self::assertSame(422, $this->response($client)['status']);
    }

    public function testLoginMeRefreshReplayAndTargetedAndFamilyRevocation(): void
    {
        $client = self::createClient();
        $session = $this->registerAndLogin($client);
        $connection = self::getContainer()->get(Connection::class);

        $this->json($client, 'GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
        $this->json($client, 'GET', '/api/me', [], 'not-a-paseto');
        self::assertResponseStatusCodeSame(401);
        self::assertSame($session['user_id'], $this->me($client, $session['access_token'])['user_id']);
        $this->json($client, 'GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
        $storedHash = $connection->fetchOne('SELECT token_hash FROM demo_refresh_token WHERE user_identifier = ? ORDER BY rowid DESC LIMIT 1', [$session['user_id']]);
        self::assertSame(hash('sha256', $session['refresh_token']), $storedHash);
        self::assertNotSame($session['refresh_token'], $storedHash);

        $this->json($client, 'POST', '/api/refresh', ['refresh_token' => $session['refresh_token']]);
        self::assertResponseStatusCodeSame(200);
        $rotated = $this->response($client);
        $rotatedRefresh = $this->string($rotated, 'refresh_token');

        $this->json($client, 'POST', '/api/refresh', ['refresh_token' => $session['refresh_token']]);
        self::assertResponseStatusCodeSame(401);
        self::assertStringContainsString('refresh_replayed', $this->string($this->response($client), 'type'));

        $this->json($client, 'POST', '/api/refresh', ['refresh_token' => $rotatedRefresh]);
        self::assertResponseStatusCodeSame(401);

        $fresh = $this->login($client, $session['email'], $session['password']);
        $this->json($client, 'POST', '/api/logout', ['refresh_token' => $fresh['refresh_token']]);
        self::assertResponseStatusCodeSame(204);
        $this->json($client, 'POST', '/api/refresh', ['refresh_token' => $fresh['refresh_token']]);
        self::assertResponseStatusCodeSame(401);

        $family = $this->login($client, $session['email'], $session['password']);
        $this->json($client, 'POST', '/api/logout/all', [], $family['access_token']);
        self::assertResponseStatusCodeSame(204);
        $this->json($client, 'POST', '/api/refresh', ['refresh_token' => $family['refresh_token']]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testTotpNeedsPasswordAndPendingConfirmationBeforeActivation(): void
    {
        $client = self::createClient();
        $session = $this->registerAndLogin($client);
        $this->json($client, 'POST', '/api/totp/enroll', ['password' => 'wrong-password'], $session['access_token']);
        self::assertResponseStatusCodeSame(401);
        self::assertFalse((bool) self::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM demo_totp_seed WHERE user_identifier = ?', [$session['user_id']]));

        $this->json($client, 'POST', '/api/totp/enroll', ['password' => $session['password']], $session['access_token']);
        self::assertResponseStatusCodeSame(201);
        $enrollment = $this->response($client);
        $seed = $this->string($enrollment, 'secret');
        $ciphertext = self::getContainer()->get(Connection::class)->fetchOne('SELECT ciphertext FROM demo_totp_seed WHERE user_identifier = ?', [$session['user_id']]);
        self::assertIsString($ciphertext);
        self::assertSame('', $ciphertext);
        self::assertSame('enc:v1:', substr((string) self::getContainer()->get(Connection::class)->fetchOne('SELECT pending_ciphertext FROM demo_totp_seed WHERE user_identifier = ?', [$session['user_id']]), 0, 7));

        $this->json($client, 'POST', '/api/totp/confirm', ['code' => '000000'], $session['access_token']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('', self::getContainer()->get(Connection::class)->fetchOne('SELECT ciphertext FROM demo_totp_seed WHERE user_identifier = ?', [$session['user_id']]));
        $this->json($client, 'POST', '/api/totp/confirm', ['code' => $this->totpCode($seed)], $session['access_token']);
        self::assertResponseStatusCodeSame(200);
        $ciphertext = self::getContainer()->get(Connection::class)->fetchOne('SELECT ciphertext FROM demo_totp_seed WHERE user_identifier = ?', [$session['user_id']]);
        self::assertIsString($ciphertext);
        self::assertStringStartsWith('enc:v1:', $ciphertext);
        self::assertNotSame($seed, $ciphertext);

        $this->json($client, 'POST', '/api/totp/verify', ['code' => $this->totpCode($seed)], $session['access_token']);
        self::assertResponseStatusCodeSame(200);
        self::assertTrue($this->response($client)['verified'] === true);

        $this->json($client, 'POST', '/api/totp/enroll', ['password' => 'wrong-password'], $session['access_token']);
        self::assertResponseStatusCodeSame(401);
        self::assertSame($ciphertext, self::getContainer()->get(Connection::class)->fetchOne('SELECT ciphertext FROM demo_totp_seed WHERE user_identifier = ?', [$session['user_id']]));

        $this->json($client, 'POST', '/api/totp/enroll', ['password' => $session['password']], $session['access_token']);
        self::assertResponseStatusCodeSame(201);
        $replacement = $this->string($this->response($client), 'secret');
        $this->json($client, 'POST', '/api/totp/confirm', ['code' => '000000'], $session['access_token']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame($ciphertext, self::getContainer()->get(Connection::class)->fetchOne('SELECT ciphertext FROM demo_totp_seed WHERE user_identifier = ?', [$session['user_id']]));
        $this->json($client, 'POST', '/api/totp/confirm', ['code' => $this->totpCode($replacement)], $session['access_token']);
        self::assertResponseStatusCodeSame(200);
        self::assertNotSame($ciphertext, self::getContainer()->get(Connection::class)->fetchOne('SELECT ciphertext FROM demo_totp_seed WHERE user_identifier = ?', [$session['user_id']]));
    }

    public function testOneTimeFlowsAreAtomicAndGenericOnIssue(): void
    {
        $client = self::createClient();
        $session = $this->registerAndLogin($client);
        $this->json($client, 'POST', '/api/magic-link', ['email' => $session['email']]);
        self::assertResponseStatusCodeSame(200);
        $known = $this->response($client);
        $this->json($client, 'POST', '/api/magic-link', ['email' => 'missing@example.test']);
        self::assertSame($known, $this->response($client));
        $this->json($client, 'POST', '/api/password-reset', ['email' => $session['email']]);
        $resetKnown = $this->response($client);
        $this->json($client, 'POST', '/api/password-reset', ['email' => 'missing@example.test']);
        self::assertSame($resetKnown, $this->response($client));

        /** @var OneTimeTokenService $magic */
        $magic = self::getContainer()->get('better_auth.feature.magic_link');
        $magicToken = $magic->issue($session['user_id']);
        $stored = self::getContainer()->get(Connection::class)->fetchOne('SELECT token_hash FROM demo_one_time_token WHERE purpose = ? AND user_identifier = ? ORDER BY rowid DESC LIMIT 1', ['magic-link', $session['user_id']]);
        self::assertSame(hash('sha256', $magicToken), $stored);
        self::assertNotSame($magicToken, $stored);
        $this->json($client, 'POST', '/api/magic-link/consume', ['token' => $magicToken]);
        self::assertResponseStatusCodeSame(200);
        $this->json($client, 'POST', '/api/magic-link/consume', ['token' => $magicToken]);
        self::assertResponseStatusCodeSame(400);

        /** @var OneTimeTokenService $reset */
        $reset = self::getContainer()->get('better_auth.feature.email_reset');
        $resetToken = $reset->issue($session['user_id']);
        $beforeReset = $this->login($client, $session['email'], $session['password']);
        $newPassword = 'Changed-password-123';
        $this->json($client, 'POST', '/api/password-reset/consume', ['token' => $resetToken, 'password' => 'short']);
        self::assertResponseStatusCodeSame(400);
        $this->json($client, 'POST', '/api/password-reset/consume', ['token' => $resetToken, 'password' => $newPassword]);
        self::assertResponseStatusCodeSame(200);
        $this->json($client, 'POST', '/api/refresh', ['refresh_token' => $beforeReset['refresh_token']]);
        self::assertResponseStatusCodeSame(401);
        $this->json($client, 'POST', '/api/password-reset/consume', ['token' => $resetToken, 'password' => $newPassword]);
        self::assertResponseStatusCodeSame(400);
        $changed = $this->login($client, $session['email'], $newPassword);
        self::assertNotSame('', $changed['access_token']);

        $this->json($client, 'POST', '/api/guest');
        self::assertResponseStatusCodeSame(201);
        $guestToken = $this->string($this->response($client), 'guest_token');
        $this->json($client, 'POST', '/api/guest/consume', ['token' => $guestToken]);
        self::assertResponseStatusCodeSame(200);
        $this->json($client, 'POST', '/api/guest/consume', ['token' => $guestToken]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testExpiredAndPurposeMismatchedTokensCannotBeConsumed(): void
    {
        $client = self::createClient();
        $session = $this->registerAndLogin($client);
        $expiredRefresh = 'expired-'.bin2hex(random_bytes(20));
        self::getContainer()->get(DoctrineRefreshTokenStore::class)->store(new RefreshTokenRecord(hash('sha256', $expiredRefresh), $session['user_id'], new \DateTimeImmutable('-1 minute')));
        $this->json($client, 'POST', '/api/refresh', ['refresh_token' => $expiredRefresh]);
        self::assertResponseStatusCodeSame(401);

        $expiredMagic = 'expired-'.bin2hex(random_bytes(20));
        self::getContainer()->get(DoctrineOneTimeTokenStore::class)->store('magic-link', hash('sha256', $expiredMagic), $session['user_id'], new \DateTimeImmutable('-1 minute'));
        $this->json($client, 'POST', '/api/magic-link/consume', ['token' => $expiredMagic]);
        self::assertResponseStatusCodeSame(400);

        /** @var OneTimeTokenService $magic */
        $magic = self::getContainer()->get('better_auth.feature.magic_link');
        $magicToken = $magic->issue($session['user_id']);
        $this->json($client, 'POST', '/api/password-reset/consume', ['token' => $magicToken, 'password' => 'Changed-password-123']);
        self::assertResponseStatusCodeSame(400);
        $this->json($client, 'POST', '/api/magic-link/consume', ['token' => $magicToken]);
        self::assertResponseStatusCodeSame(200);
    }

    public function testDeviceMonitoringAndTenantAllowDenyArePersisted(): void
    {
        $client = self::createClient();
        $session = $this->registerAndLogin($client);
        $this->json($client, 'POST', '/api/devices', [], $session['access_token'], ['HTTP_USER_AGENT' => 'DemoBrowser/1.0']);
        self::assertResponseStatusCodeSame(201);
        $fingerprint = $this->string($this->response($client), 'fingerprint');
        self::assertSame($fingerprint, self::getContainer()->get(Connection::class)->fetchOne('SELECT fingerprint FROM demo_device WHERE user_identifier = ? ORDER BY id DESC LIMIT 1', [$session['user_id']]));

        $this->json($client, 'POST', '/api/monitoring', ['type' => 'login_success', 'severity' => 'info'], $session['access_token']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('login_success', self::getContainer()->get(Connection::class)->fetchOne('SELECT type FROM demo_security_event WHERE user_identifier = ? ORDER BY id DESC LIMIT 1', [$session['user_id']]));

        $this->json($client, 'GET', '/api/tenant/demo', [], $session['access_token']);
        self::assertResponseStatusCodeSame(200);
        self::assertTrue($this->response($client)['allowed'] === true);
        $this->json($client, 'GET', '/api/tenant/other', [], $session['access_token']);
        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('tenant_denied', $this->string($this->response($client), 'type'));
    }

    public function testPublicAuthenticationEndpointsRateLimitByIpAndIdentity(): void
    {
        $client = self::createClient();
        for ($attempt = 0; $attempt < 11; ++$attempt) {
            $this->json($client, 'POST', '/api/login', ['email' => 'rate-limit@example.test', 'password' => 'wrong-password']);
        }
        self::assertResponseStatusCodeSame(429);
        self::assertStringContainsString('rate_limited', $this->string($this->response($client), 'type'));
    }

    public function testPublicAuthenticationEndpointsRateLimitByIpAcrossIdentities(): void
    {
        $client = self::createClient();
        for ($attempt = 0; $attempt < 21; ++$attempt) {
            $this->json($client, 'POST', '/api/login', ['email' => sprintf('rate-%d@example.test', $attempt), 'password' => 'wrong-password']);
        }
        self::assertResponseStatusCodeSame(429);
        self::assertStringContainsString('rate_limited', $this->string($this->response($client), 'type'));
    }

    public function testTotpRoutesRateLimitByAuthenticatedAccountAndIp(): void
    {
        $client = self::createClient();
        $session = $this->registerAndLogin($client);
        for ($attempt = 0; $attempt < 11; ++$attempt) {
            $this->json($client, 'POST', '/api/totp/verify', ['code' => '000000'], $session['access_token']);
        }
        self::assertResponseStatusCodeSame(429);
        self::assertStringContainsString('rate_limited', $this->string($this->response($client), 'type'));
    }

    public function testPendingTotpCompareAndSwapNeverPromotesAReplacement(): void
    {
        $client = self::createClient();
        $session = $this->registerAndLogin($client);
        $store = self::getContainer()->get(DoctrineTotpStore::class);
        $store->save($session['user_id'], 'enc:v1:active');
        $store->savePending($session['user_id'], 'enc:v1:pending-a', new \DateTimeImmutable('+10 minutes'));
        $snapshot = $store->snapshotPending($session['user_id'], new \DateTimeImmutable());
        self::assertNotNull($snapshot);
        self::assertSame('enc:v1:pending-a', $snapshot->ciphertext);

        $store->savePending($session['user_id'], 'enc:v1:pending-b', new \DateTimeImmutable('+10 minutes'));
        self::assertFalse($store->activatePending($session['user_id'], $snapshot->ciphertext, new \DateTimeImmutable()));
        self::assertSame('enc:v1:active', $store->load($session['user_id']));
        self::assertSame('enc:v1:pending-b', $store->snapshotPending($session['user_id'], new \DateTimeImmutable())?->ciphertext);
    }

    /** @return array{access_token: string, email: string, password: string, refresh_token: string, user_id: string} */
    private function registerAndLogin(KernelBrowser $client): array
    {
        $email = 'user-'.bin2hex(random_bytes(6)).'@example.test';
        $password = 'Correct-horse-battery-'.bin2hex(random_bytes(4));
        $this->json($client, 'POST', '/api/register', ['email' => $email, 'password' => $password]);
        self::assertResponseStatusCodeSame(202);
        $registered = $this->response($client);
        $this->json($client, 'POST', '/api/register', ['email' => $email, 'password' => $password]);
        self::assertSame($registered, $this->response($client));
        $tokens = $this->login($client, $email, $password);
        $me = $this->me($client, $tokens['access_token']);

        return $tokens + ['email' => $email, 'password' => $password, 'user_id' => $this->string($me, 'user_id')];
    }

    /** @return array{access_token: string, refresh_token: string} */
    private function login(KernelBrowser $client, string $email, string $password): array
    {
        $this->json($client, 'POST', '/api/login', ['email' => $email, 'password' => $password]);
        self::assertResponseStatusCodeSame(200);
        $response = $this->response($client);

        return ['access_token' => $this->string($response, 'access_token'), 'refresh_token' => $this->string($response, 'refresh_token')];
    }

    /** @return array<string, mixed> */
    private function me(KernelBrowser $client, string $accessToken): array
    {
        $this->json($client, 'GET', '/api/me', [], $accessToken);
        self::assertResponseStatusCodeSame(200);

        return $this->response($client);
    }

    /** @param array<string, mixed> $payload @param array<string, string> $server */
    private function json(KernelBrowser $client, string $method, string $uri, array $payload = [], ?string $accessToken = null, array $server = []): void
    {
        $server['CONTENT_TYPE'] = 'application/json';
        $server += ['REMOTE_ADDR' => $this->testIp];
        if ($accessToken !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$accessToken;
        }
        $client->request($method, $uri, server: $server, content: json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function response(KernelBrowser $client): array
    {
        $response = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($response)) {
            throw new \LogicException('Expected a JSON object.');
        }

        return $response;
    }

    /** @param array<string, mixed> $payload */
    private function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \LogicException(sprintf('Expected non-empty string at %s.', $key));
        }

        return $value;
    }

    private function totpCode(string $base32Secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($base32Secret) as $character) {
            $index = strpos($alphabet, $character);
            if ($index === false) {
                throw new \LogicException('Unexpected TOTP seed.');
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }
        $secret = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $secret .= chr(bindec($chunk));
            }
        }
        $counter = intdiv(time(), 30);
        $hash = hash_hmac('sha256', pack('N2', intdiv($counter, 4294967296), $counter % 4294967296), $secret, true);
        $offset = ord($hash[-1]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
