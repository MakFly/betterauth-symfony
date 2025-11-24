# Guide de mise en place : Passkey, OIDC et GuestSessionProvider

## 1. Passkey (WebAuthn)

### Configuration

Le PasskeyProvider nécessite deux paramètres :
- `rpId` : L'identifiant du Relying Party (généralement votre domaine)
- `rpName` : Le nom affiché dans l'authentificateur

### Étape 1 : Configuration dans `services.yaml`

```yaml
# config/services.yaml
services:
    BetterAuth\Providers\PasskeyProvider\PasskeyProvider:
        arguments:
            $userRepository: '@BetterAuth\Core\Interfaces\UserRepositoryInterface'
            $passkeyStorage: '@BetterAuth\Core\Interfaces\PasskeyStorageInterface'
            $sessionService: '@BetterAuth\Core\SessionService'
            $rpId: '%env(PASSKEY_RP_ID)%'  # Ex: 'example.com' ou 'localhost' pour dev
            $rpName: '%env(PASSKEY_RP_NAME)%'  # Ex: 'My Application'
        public: true
```

### Étape 2 : Variables d'environnement

```env
# .env
PASSKEY_RP_ID=localhost  # En dev, utilisez 'localhost'. En prod, votre domaine sans https://
PASSKEY_RP_NAME=My Application
```

**Important** : 
- En production, `rpId` doit correspondre exactement au domaine de votre application
- WebAuthn nécessite HTTPS (sauf pour localhost)
- Le `rpId` ne doit pas inclure le protocole (pas de `https://`)

### Étape 3 : Créer l'entité PasskeyData (si pas déjà fait)

```bash
php bin/console better-auth:install
# Sélectionnez "Passkey" lors de l'installation
```

Ou créez manuellement :

```php
// src/Entity/PasskeyData.php
<?php

namespace App\Entity;

use BetterAuth\Core\Entities\PasskeyData as BasePasskeyData;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'passkey_data')]
class PasskeyData extends BasePasskeyData
{
    // L'entité de base contient déjà tous les champs nécessaires
}
```

### Étape 4 : Créer le Repository Doctrine

**Note** : Le repository Doctrine pour Passkey n'existe pas encore. Vous devez le créer :

```php
// src/Storage/Doctrine/DoctrinePasskeyRepository.php
<?php

namespace App\Storage\Doctrine;

use App\Entity\PasskeyData;
use BetterAuth\Core\Interfaces\PasskeyStorageInterface;
use BetterAuth\Symfony\Service\UserIdConverter;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrinePasskeyRepository implements PasskeyStorageInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserIdConverter $idConverter,
        private readonly string $passkeyClass = PasskeyData::class
    ) {
    }

    public function store(string $userId, string $credentialId, string $publicKey, array $metadata): bool
    {
        $passkey = $this->entityManager->getRepository($this->passkeyClass)
            ->findOneBy(['credentialId' => $credentialId]);

        if ($passkey === null) {
            $passkey = new ($this->passkeyClass)();
            $passkey->setUserId($this->idConverter->toDatabaseId($userId));
            $passkey->setCredentialId($credentialId);
        }

        $passkey->setPublicKey($publicKey);
        $passkey->setMetadata($metadata);

        if ($passkey->getId() === null) {
            $this->entityManager->persist($passkey);
        }
        $this->entityManager->flush();

        return true;
    }

    public function findByCredentialId(string $credentialId): ?array
    {
        $passkey = $this->entityManager->getRepository($this->passkeyClass)
            ->findOneBy(['credentialId' => $credentialId]);

        if ($passkey === null) {
            return null;
        }

        return [
            'user_id' => $this->idConverter->fromDatabaseId($passkey->getUserId()),
            'credential_id' => $passkey->getCredentialId(),
            'public_key' => $passkey->getPublicKey(),
            'metadata' => $passkey->getMetadata(),
        ];
    }

    public function findByUserId(string $userId): array
    {
        $passkeys = $this->entityManager->getRepository($this->passkeyClass)
            ->findBy(['userId' => $this->idConverter->toDatabaseId($userId)]);

        return array_map(fn ($p) => [
            'user_id' => $this->idConverter->fromDatabaseId($p->getUserId()),
            'credential_id' => $p->getCredentialId(),
            'public_key' => $p->getPublicKey(),
            'metadata' => $p->getMetadata(),
        ], $passkeys);
    }

    public function update(string $credentialId, array $data): bool
    {
        $passkey = $this->entityManager->getRepository($this->passkeyClass)
            ->findOneBy(['credentialId' => $credentialId]);

        if ($passkey === null) {
            return false;
        }

        if (isset($data['metadata'])) {
            $passkey->setMetadata($data['metadata']);
        }

        $this->entityManager->flush();
        return true;
    }

    public function delete(string $credentialId): bool
    {
        $passkey = $this->entityManager->getRepository($this->passkeyClass)
            ->findOneBy(['credentialId' => $credentialId]);

        if ($passkey === null) {
            return false;
        }

        $this->entityManager->remove($passkey);
        $this->entityManager->flush();
        return true;
    }
}
```

### Étape 5 : Configurer le service dans `services.yaml`

```yaml
# config/services.yaml
services:
    App\Storage\Doctrine\DoctrinePasskeyRepository:
        arguments:
            $entityManager: '@Doctrine\ORM\EntityManagerInterface'
            $idConverter: '@BetterAuth\Symfony\Service\UserIdConverter'
            $passkeyClass: 'App\Entity\PasskeyData'
        public: true

    BetterAuth\Core\Interfaces\PasskeyStorageInterface:
        alias: App\Storage\Doctrine\DoctrinePasskeyRepository
```

### Étape 6 : Créer le Controller

```php
// src/Controller/PasskeyController.php
<?php

namespace App\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Providers\PasskeyProvider\PasskeyProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/passkey', name: 'auth_passkey_')]
class PasskeyController extends AbstractController
{
    public function __construct(
        private readonly AuthManager $authManager,
        private readonly PasskeyProvider $passkeyProvider
    ) {
    }

    #[Route('/register/options', name: 'register_options', methods: ['POST'])]
    public function getRegistrationOptions(Request $request): JsonResponse
    {
        try {
            $token = $this->getBearerToken($request);
            if (!$token) {
                return $this->json(['error' => 'No token provided'], 401);
            }

            $user = $this->authManager->getCurrentUser($token);
            if (!$user) {
                return $this->json(['error' => 'Invalid token'], 401);
            }

            $options = $this->passkeyProvider->generateRegistrationOptions($user);
            
            // Stocker le challenge en session ou cache pour vérification
            // En production, utilisez un cache Redis ou similaire
            $request->getSession()->set('passkey_challenge', $options['challenge']);

            return $this->json($options);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        try {
            $token = $this->getBearerToken($request);
            if (!$token) {
                return $this->json(['error' => 'No token provided'], 401);
            }

            $user = $this->authManager->getCurrentUser($token);
            if (!$user) {
                return $this->json(['error' => 'Invalid token'], 401);
            }

            $data = json_decode($request->getContent(), true);
            $credential = $data['credential'] ?? null;
            $challenge = $request->getSession()->get('passkey_challenge');

            if (!$credential || !$challenge) {
                return $this->json(['error' => 'Invalid request'], 400);
            }

            $success = $this->passkeyProvider->verifyRegistration($user, $credential, $challenge);
            
            if ($success) {
                $request->getSession()->remove('passkey_challenge');
                return $this->json(['message' => 'Passkey registered successfully']);
            }

            return $this->json(['error' => 'Registration failed'], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/authenticate/options', name: 'authenticate_options', methods: ['POST'])]
    public function getAuthenticationOptions(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $userId = $data['userId'] ?? null;

            $options = $this->passkeyProvider->generateAuthenticationOptions($userId);
            
            // Stocker le challenge
            $request->getSession()->set('passkey_challenge', $options['challenge']);

            return $this->json($options);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/authenticate', name: 'authenticate', methods: ['POST'])]
    public function authenticate(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $assertion = $data['assertion'] ?? null;
            $challenge = $request->getSession()->get('passkey_challenge');

            if (!$assertion || !$challenge) {
                return $this->json(['error' => 'Invalid request'], 400);
            }

            $result = $this->passkeyProvider->verifyAuthentication(
                $assertion,
                $challenge,
                $request->getClientIp() ?? '127.0.0.1',
                $request->headers->get('User-Agent') ?? 'Unknown'
            );

            $request->getSession()->remove('passkey_challenge');

            // En mode API, créer un token
            // En mode session, la session est déjà créée par verifyAuthentication
            if ($request->attributes->get('better_auth.mode') === 'api') {
                // Créer un access token
                $tokenService = $this->container->get('BetterAuth\Core\TokenService');
                $accessToken = $tokenService->sign([
                    'sub' => $result['user']->getId(),
                    'type' => 'access_token',
                ], 7200);

                return $this->json([
                    'access_token' => $accessToken,
                    'user' => [
                        'id' => $result['user']->getId(),
                        'email' => $result['user']->getEmail(),
                        'name' => $result['user']->getName(),
                    ],
                ]);
            }

            // En mode session/hybrid, retourner la session
            return $this->json([
                'session' => $result['session']->getToken(),
                'user' => [
                    'id' => $result['user']->getId(),
                    'email' => $result['user']->getEmail(),
                    'name' => $result['user']->getName(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 401);
        }
    }

    #[Route('/list', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $token = $this->getBearerToken($request);
            if (!$token) {
                return $this->json(['error' => 'No token provided'], 401);
            }

            $user = $this->authManager->getCurrentUser($token);
            if (!$user) {
                return $this->json(['error' => 'Invalid token'], 401);
            }

            $credentials = $this->passkeyProvider->getUserCredentials($user->getId());

            return $this->json(['credentials' => $credentials]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/delete/{credentialId}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $credentialId, Request $request): JsonResponse
    {
        try {
            $token = $this->getBearerToken($request);
            if (!$token) {
                return $this->json(['error' => 'No token provided'], 401);
            }

            $user = $this->authManager->getCurrentUser($token);
            if (!$user) {
                return $this->json(['error' => 'Invalid token'], 401);
            }

            $this->passkeyProvider->deleteCredential($credentialId);

            return $this->json(['message' => 'Passkey deleted successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    private function getBearerToken(Request $request): ?string
    {
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        return substr($authHeader, 7);
    }
}
```

### Notes importantes

⚠️ **Le code actuel est une implémentation simplifiée**. Pour la production, vous devez :
1. Utiliser une bibliothèque WebAuthn complète (ex: `web-auth/webauthn-lib`)
2. Vérifier correctement les signatures
3. Valider l'origine et le challenge
4. Gérer les compteurs de replay attacks

---

## 2. OIDC (OpenID Connect Provider)

OIDC permet à BetterAuth d'agir comme un serveur SSO pour d'autres applications.

### Étape 1 : Configuration dans `services.yaml`

```yaml
# config/services.yaml
services:
    BetterAuth\Providers\OidcProvider\OidcProvider:
        arguments:
            $clientRepository: '@BetterAuth\Core\Interfaces\OAuthClientRepositoryInterface'
            $codeRepository: '@BetterAuth\Core\Interfaces\AuthorizationCodeRepositoryInterface'
            $userRepository: '@BetterAuth\Core\Interfaces\UserRepositoryInterface'
            $refreshTokenRepository: '@BetterAuth\Core\Interfaces\RefreshTokenRepositoryInterface'
            $tokenService: '@BetterAuth\Core\TokenService'
            $issuer: '%env(OIDC_ISSUER)%'  # Ex: 'https://auth.example.com'
            $authCodeLifetime: 600  # 10 minutes
            $accessTokenLifetime: 3600  # 1 hour
            $refreshTokenLifetime: 2592000  # 30 days
        public: true
```

### Étape 2 : Variables d'environnement

```env
# .env
OIDC_ISSUER=https://auth.example.com  # URL publique de votre serveur d'authentification
```

### Étape 3 : Créer les entités nécessaires

Les entités suivantes sont nécessaires :
- `OAuthClient` : Clients OAuth qui peuvent utiliser votre serveur
- `AuthorizationCode` : Codes d'autorisation temporaires
- `RefreshToken` : Déjà créé si vous utilisez le mode API

**Note** : Les repositories Doctrine pour OIDC n'existent pas encore. Vous devez les créer :

```php
// src/Storage/Doctrine/DoctrineOAuthClientRepository.php
<?php

namespace App\Storage\Doctrine;

use BetterAuth\Core\Entities\OAuthClient;
use BetterAuth\Core\Interfaces\OAuthClientRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineOAuthClientRepository implements OAuthClientRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $clientClass = 'App\Entity\OAuthClient'
    ) {
    }

    public function findById(string $clientId): ?OAuthClient
    {
        $client = $this->entityManager->find($this->clientClass, $clientId);
        if ($client === null) {
            return null;
        }

        return OAuthClient::fromArray([
            'id' => $client->getId(),
            'name' => $client->getName(),
            'client_secret' => $client->getClientSecret(),
            'redirect_uris' => $client->getRedirectUris(),
            'allowed_scopes' => $client->getAllowedScopes(),
            'type' => $client->getType(),
            'active' => $client->isActive(),
            'created_at' => $client->getCreatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    public function create(array $data): OAuthClient
    {
        $client = new ($this->clientClass)();
        $client->setId($data['id']);
        $client->setName($data['name']);
        $client->setClientSecret($data['client_secret']);
        $client->setRedirectUris($data['redirect_uris']);
        $client->setAllowedScopes($data['allowed_scopes']);
        $client->setType($data['type'] ?? 'confidential');
        $client->setActive($data['active'] ?? true);

        $this->entityManager->persist($client);
        $this->entityManager->flush();

        return $this->findById($data['id']);
    }

    public function update(string $clientId, array $data): OAuthClient
    {
        $client = $this->entityManager->find($this->clientClass, $clientId);
        if ($client === null) {
            throw new \RuntimeException('Client not found');
        }

        if (isset($data['name'])) {
            $client->setName($data['name']);
        }
        if (isset($data['redirect_uris'])) {
            $client->setRedirectUris($data['redirect_uris']);
        }
        if (isset($data['allowed_scopes'])) {
            $client->setAllowedScopes($data['allowed_scopes']);
        }
        if (isset($data['active'])) {
            $client->setActive($data['active']);
        }

        $this->entityManager->flush();
        return $this->findById($clientId);
    }
}
```

```php
// src/Storage/Doctrine/DoctrineAuthorizationCodeRepository.php
<?php

namespace App\Storage\Doctrine;

use BetterAuth\Core\Entities\AuthorizationCode;
use BetterAuth\Core\Interfaces\AuthorizationCodeRepositoryInterface;
use BetterAuth\Symfony\Service\UserIdConverter;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineAuthorizationCodeRepository implements AuthorizationCodeRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserIdConverter $idConverter,
        private readonly string $codeClass = 'App\Entity\AuthorizationCode'
    ) {
    }

    public function findByCode(string $code): ?AuthorizationCode
    {
        $authCode = $this->entityManager->getRepository($this->codeClass)
            ->findOneBy(['code' => $code]);

        if ($authCode === null) {
            return null;
        }

        return AuthorizationCode::fromArray([
            'code' => $authCode->getCode(),
            'client_id' => $authCode->getClientId(),
            'user_id' => $this->idConverter->fromDatabaseId($authCode->getUserId()),
            'redirect_uri' => $authCode->getRedirectUri(),
            'scopes' => $authCode->getScopes(),
            'expires_at' => $authCode->getExpiresAt()?->format('Y-m-d H:i:s'),
            'code_challenge' => $authCode->getCodeChallenge(),
            'code_challenge_method' => $authCode->getCodeChallengeMethod(),
            'used' => $authCode->isUsed(),
        ]);
    }

    public function create(array $data): AuthorizationCode
    {
        $authCode = new ($this->codeClass)();
        $authCode->setCode($data['code']);
        $authCode->setClientId($data['clientId']);
        $authCode->setUserId($this->idConverter->toDatabaseId($data['userId']));
        $authCode->setRedirectUri($data['redirectUri']);
        $authCode->setScopes($data['scopes']);
        $authCode->setExpiresAt(new \DateTimeImmutable($data['expiresAt']));
        $authCode->setCodeChallenge($data['codeChallenge'] ?? null);
        $authCode->setCodeChallengeMethod($data['codeChallengeMethod'] ?? null);
        $authCode->setUsed(false);

        $this->entityManager->persist($authCode);
        $this->entityManager->flush();

        return $this->findByCode($data['code']);
    }

    public function markAsUsed(string $code): bool
    {
        $authCode = $this->entityManager->getRepository($this->codeClass)
            ->findOneBy(['code' => $code]);

        if ($authCode === null) {
            return false;
        }

        $authCode->setUsed(true);
        $this->entityManager->flush();
        return true;
    }
}
```

Puis configurez dans `services.yaml` :

```yaml
# config/services.yaml
services:
    App\Storage\Doctrine\DoctrineOAuthClientRepository:
        arguments:
            $entityManager: '@Doctrine\ORM\EntityManagerInterface'
        public: true

    BetterAuth\Core\Interfaces\OAuthClientRepositoryInterface:
        alias: App\Storage\Doctrine\DoctrineOAuthClientRepository

    App\Storage\Doctrine\DoctrineAuthorizationCodeRepository:
        arguments:
            $entityManager: '@Doctrine\ORM\EntityManagerInterface'
            $idConverter: '@BetterAuth\Symfony\Service\UserIdConverter'
        public: true

    BetterAuth\Core\Interfaces\AuthorizationCodeRepositoryInterface:
        alias: App\Storage\Doctrine\DoctrineAuthorizationCodeRepository
```

### Étape 4 : Créer le Controller OIDC

```php
// src/Controller/OidcController.php
<?php

namespace App\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Providers\OidcProvider\OidcProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/oauth', name: 'oauth_')]
class OidcController extends AbstractController
{
    public function __construct(
        private readonly AuthManager $authManager,
        private readonly OidcProvider $oidcProvider
    ) {
    }

    /**
     * Endpoint d'autorisation OAuth (Step 1)
     * GET /oauth/authorize?client_id=xxx&redirect_uri=xxx&response_type=code&scope=openid profile email&state=xxx
     */
    #[Route('/authorize', name: 'authorize', methods: ['GET'])]
    public function authorize(Request $request): JsonResponse
    {
        try {
            // L'utilisateur doit être authentifié
            $token = $this->getAuthToken($request);
            if (!$token) {
                // Rediriger vers la page de login
                return $this->json(['error' => 'Authentication required'], 401);
            }

            $user = $this->authManager->getCurrentUser($token);
            if (!$user) {
                return $this->json(['error' => 'Invalid token'], 401);
            }

            $clientId = $request->query->get('client_id');
            $redirectUri = $request->query->get('redirect_uri');
            $responseType = $request->query->get('response_type', 'code');
            $scopes = explode(' ', $request->query->get('scope', 'openid'));
            $state = $request->query->get('state');
            $codeChallenge = $request->query->get('code_challenge');
            $codeChallengeMethod = $request->query->get('code_challenge_method');

            $result = $this->oidcProvider->authorize(
                $clientId,
                $redirectUri,
                $responseType,
                $scopes,
                $state,
                $user,
                $codeChallenge,
                $codeChallengeMethod
            );

            // Rediriger vers redirect_uri avec le code
            return $this->redirect($redirectUri . '?code=' . $result['code'] . '&state=' . $result['state']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Endpoint d'échange de token (Step 2)
     * POST /oauth/token
     */
    #[Route('/token', name: 'token', methods: ['POST'])]
    public function token(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Supporte aussi application/x-www-form-urlencoded
            if (empty($data)) {
                $data = $request->request->all();
            }

            $grantType = $data['grant_type'] ?? null;
            $code = $data['code'] ?? null;
            $redirectUri = $data['redirect_uri'] ?? null;
            $clientId = $data['client_id'] ?? null;
            $clientSecret = $data['client_secret'] ?? null;
            $codeVerifier = $data['code_verifier'] ?? null;
            $refreshToken = $data['refresh_token'] ?? null;

            $result = $this->oidcProvider->token(
                $grantType,
                $code,
                $redirectUri,
                $clientId,
                $clientSecret,
                $codeVerifier,
                $refreshToken
            );

            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Endpoint userinfo (OIDC standard)
     * GET /oauth/userinfo
     */
    #[Route('/userinfo', name: 'userinfo', methods: ['GET'])]
    public function userinfo(Request $request): JsonResponse
    {
        try {
            $token = $this->getBearerToken($request);
            if (!$token) {
                return $this->json(['error' => 'No token provided'], 401);
            }

            $userinfo = $this->oidcProvider->userinfo($token);

            return $this->json($userinfo);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 401);
        }
    }

    /**
     * Discovery endpoint (OIDC standard)
     * GET /.well-known/openid-configuration
     */
    #[Route('/.well-known/openid-configuration', name: 'discovery', methods: ['GET'])]
    public function discovery(): JsonResponse
    {
        $config = $this->oidcProvider->getDiscoveryConfiguration();
        return $this->json($config);
    }

    /**
     * JWKS endpoint (pour la vérification des tokens)
     * GET /.well-known/jwks.json
     */
    #[Route('/.well-known/jwks.json', name: 'jwks', methods: ['GET'])]
    public function jwks(): JsonResponse
    {
        // En production, générez les clés publiques JWKS
        // Pour l'instant, retournez un objet vide
        return $this->json(['keys' => []]);
    }

    private function getBearerToken(Request $request): ?string
    {
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        return substr($authHeader, 7);
    }

    private function getAuthToken(Request $request): ?string
    {
        // Support Bearer token ou cookie
        $token = $this->getBearerToken($request);
        if ($token) {
            return $token;
        }

        return $request->cookies->get('access_token');
    }
}
```

### Étape 5 : Créer un client OAuth

Vous devez créer un client OAuth dans votre base de données :

```php
// src/Command/CreateOAuthClientCommand.php
<?php

namespace App\Command;

use BetterAuth\Core\Interfaces\OAuthClientRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-oauth-client',
    description: 'Create a new OAuth client'
)]
class CreateOAuthClientCommand extends Command
{
    public function __construct(
        private readonly OAuthClientRepositoryInterface $clientRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $clientId = $io->ask('Client ID', 'my-app');
        $clientSecret = bin2hex(random_bytes(32));
        $name = $io->ask('Client Name', 'My Application');
        $redirectUri = $io->ask('Redirect URI', 'https://myapp.com/callback');
        $scopes = $io->ask('Allowed Scopes (space-separated)', 'openid profile email offline_access');

        $this->clientRepository->create([
            'id' => $clientId,
            'name' => $name,
            'client_secret' => $clientSecret,
            'redirect_uris' => [$redirectUri],
            'allowed_scopes' => explode(' ', $scopes),
            'type' => 'confidential',
            'active' => true,
        ]);

        $io->success("Client created!");
        $io->table(
            ['Property', 'Value'],
            [
                ['Client ID', $clientId],
                ['Client Secret', $clientSecret],
                ['Redirect URI', $redirectUri],
                ['Scopes', $scopes],
            ]
        );

        return Command::SUCCESS;
    }
}
```

### Test OIDC en local

#### Option 1 : Sous-domaines locaux (Recommandé)

**Configuration `/etc/hosts`** :
```bash
127.0.0.1 auth.localhost
127.0.0.1 app.localhost
```

**Configuration `.env`** :
```env
# Serveur d'authentification (BetterAuth)
OIDC_ISSUER=http://auth.localhost:8000
APP_URL=http://auth.localhost:8000

# Application cliente (qui utilise OIDC)
CLIENT_APP_URL=http://app.localhost:3000
```

**Docker Compose avec Caddy** :
```yaml
# docker-compose.yml
version: '3.8'
services:
  caddy:
    image: caddy:2-alpine
    ports:
      - "80:80"
      - "443:443"
      - "443:443/udp"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile
      - caddy_data:/data
      - caddy_config:/config
    depends_on:
      - auth-api
      - client-app

  auth-api:
    build: ./boilerplate-authentification
    ports:
      - "8000:8000"
    environment:
      - OIDC_ISSUER=https://auth.localhost
      - APP_URL=https://auth.localhost

  client-app:
    build: ./client-app
    ports:
      - "3000:3000"
    environment:
      - OIDC_ISSUER=https://auth.localhost
      - REACT_APP_OIDC_ISSUER=https://auth.localhost

volumes:
  caddy_data:
  caddy_config:
```

**Caddyfile** :
```caddyfile
# Caddyfile
auth.localhost {
    reverse_proxy auth-api:8000 {
        header_up Host {host}
        header_up X-Real-IP {remote}
        header_up X-Forwarded-For {remote}
        header_up X-Forwarded-Proto {scheme}
    }
}

app.localhost {
    reverse_proxy client-app:3000 {
        header_up Host {host}
        header_up X-Real-IP {remote}
        header_up X-Forwarded-For {remote}
        header_up X-Forwarded-Proto {scheme}
    }
}
```

**Avantages de Caddy** :
- ✅ HTTPS automatique avec certificats auto-signés en local
- ✅ Configuration ultra-simple (3 lignes par service)
- ✅ Pas besoin de configurer SSL manuellement
- ✅ Support HTTP/2 et HTTP/3 automatique

#### Option 2 : Ports différents (Plus simple)

**Configuration `.env`** :
```env
# Serveur d'authentification
OIDC_ISSUER=http://localhost:8000
APP_URL=http://localhost:8000

# Application cliente
CLIENT_APP_URL=http://localhost:3000
```

**Test rapide avec curl** :

```bash
# 1. Créer un client OAuth
php bin/console app:create-oauth-client
# Client ID: test-client
# Redirect URI: http://localhost:3000/callback
# Scopes: openid profile email

# 2. Obtenir l'URL d'autorisation (dans le navigateur)
# http://localhost:8000/oauth/authorize?client_id=test-client&redirect_uri=http://localhost:3000/callback&response_type=code&scope=openid profile email&state=xyz123

# 3. Après authentification, récupérer le code depuis redirect_uri

# 4. Échanger le code contre un token
curl -X POST http://localhost:8000/oauth/token \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "authorization_code",
    "code": "CODE_FROM_STEP_3",
    "redirect_uri": "http://localhost:3000/callback",
    "client_id": "test-client",
    "client_secret": "CLIENT_SECRET"
  }'

# 5. Utiliser le token pour obtenir userinfo
curl http://localhost:8000/oauth/userinfo \
  -H "Authorization: Bearer ACCESS_TOKEN_FROM_STEP_4"
```

#### Option 3 : Test avec Postman/Bruno

**Collection Bruno** :
```json
{
  "name": "OIDC Test",
  "requests": [
    {
      "name": "Get Authorization URL",
      "method": "GET",
      "url": "http://localhost:8000/oauth/authorize",
      "params": {
        "client_id": "test-client",
        "redirect_uri": "http://localhost:3000/callback",
        "response_type": "code",
        "scope": "openid profile email",
        "state": "{{$randomUUID}}"
      }
    },
    {
      "name": "Exchange Code for Token",
      "method": "POST",
      "url": "http://localhost:8000/oauth/token",
      "body": {
        "grant_type": "authorization_code",
        "code": "{{code}}",
        "redirect_uri": "http://localhost:3000/callback",
        "client_id": "test-client",
        "client_secret": "{{client_secret}}"
      }
    },
    {
      "name": "Get UserInfo",
      "method": "GET",
      "url": "http://localhost:8000/oauth/userinfo",
      "headers": {
        "Authorization": "Bearer {{access_token}}"
      }
    }
  ]
}
```

#### Application cliente de test (React/Vue)

```javascript
// client-app/src/App.jsx
import { useEffect, useState } from 'react';

function App() {
  const [user, setUser] = useState(null);

  const handleLogin = () => {
    const clientId = 'test-client';
    const redirectUri = 'http://localhost:3000/callback';
    const scope = 'openid profile email';
    const state = Math.random().toString(36);
    
    // Stocker state pour vérification
    sessionStorage.setItem('oauth_state', state);
    
    // Rediriger vers le serveur d'authentification
    window.location.href = `http://localhost:8000/oauth/authorize?client_id=${clientId}&redirect_uri=${encodeURIComponent(redirectUri)}&response_type=code&scope=${scope}&state=${state}`;
  };

  useEffect(() => {
    // Gérer le callback OAuth
    const urlParams = new URLSearchParams(window.location.search);
    const code = urlParams.get('code');
    const state = urlParams.get('state');
    
    if (code && state === sessionStorage.getItem('oauth_state')) {
      // Échanger le code contre un token
      fetch('http://localhost:8000/oauth/token', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          grant_type: 'authorization_code',
          code,
          redirect_uri: 'http://localhost:3000/callback',
          client_id: 'test-client',
          client_secret: 'YOUR_CLIENT_SECRET',
        }),
      })
        .then(res => res.json())
        .then(data => {
          // Obtenir les infos utilisateur
          return fetch('http://localhost:8000/oauth/userinfo', {
            headers: { 'Authorization': `Bearer ${data.access_token}` },
          });
        })
        .then(res => res.json())
        .then(userInfo => setUser(userInfo));
    }
  }, []);

  return (
    <div>
      {user ? (
        <div>
          <h1>Connecté !</h1>
          <pre>{JSON.stringify(user, null, 2)}</pre>
        </div>
      ) : (
        <button onClick={handleLogin}>Se connecter avec OIDC</button>
      )}
    </div>
  );
}

export default App;
```

**Recommandation** : Utilisez l'**Option 2** (ports différents) pour commencer rapidement. Passez à l'**Option 1** (sous-domaines) si vous voulez tester des scénarios plus réalistes (CORS, cookies, etc.).

---

## 3. GuestSessionProvider

Le GuestSessionProvider permet de créer des sessions temporaires pour les utilisateurs non authentifiés, puis de les convertir en utilisateurs réels lors de l'inscription.

### Fonctionnement par mode

#### Mode `api`
- ✅ Crée un token guest temporaire
- ✅ Peut être converti en utilisateur avec un access token
- ❌ Pas de session persistante (stateless)

#### Mode `session`
- ✅ Crée une session guest en base de données
- ✅ Peut être convertie en utilisateur avec une session réelle
- ✅ Tracking complet (IP, User-Agent, etc.)

#### Mode `hybrid`
- ✅ Supporte les deux approches
- ✅ Flexibilité maximale

### Étape 1 : Configuration (déjà fait dans `services.yaml`)

Le GuestSessionProvider est déjà configuré :

```yaml
# config/services.yaml (déjà présent)
services:
    BetterAuth\Providers\GuestSessionProvider\GuestSessionProvider:
        arguments:
            $guestSessionRepository: '@BetterAuth\Core\Interfaces\GuestSessionRepositoryInterface'
            $userRepository: '@BetterAuth\Core\Interfaces\UserRepositoryInterface'
            $sessionLifetime: 86400  # 24 heures
        public: true
```

### Étape 2 : Créer le Controller

```php
// src/Controller/GuestSessionController.php
<?php

namespace App\Controller;

use BetterAuth\Providers\GuestSessionProvider\GuestSessionProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/guest', name: 'auth_guest_')]
class GuestSessionController extends AbstractController
{
    public function __construct(
        private readonly GuestSessionProvider $guestSessionProvider
    ) {
    }

    /**
     * Créer une session guest
     * POST /auth/guest/create
     */
    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            $guestSession = $this->guestSessionProvider->createGuestSession(
                deviceInfo: $data['deviceInfo'] ?? null,
                ipAddress: $request->getClientIp() ?? '127.0.0.1',
                metadata: $data['metadata'] ?? null
            );

            return $this->json([
                'token' => $guestSession->token,
                'expiresAt' => $guestSession->expiresAt,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Convertir une session guest en utilisateur
     * POST /auth/guest/convert
     */
    #[Route('/convert', name: 'convert', methods: ['POST'])]
    public function convert(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['guestToken'], $data['email'], $data['password'])) {
                return $this->json(['error' => 'Missing required fields'], 400);
            }

            // Convertir la session guest en utilisateur
            $user = $this->guestSessionProvider->convertToUser(
                $data['guestToken'],
                [
                    'email' => $data['email'],
                    'password' => $data['password'],  // Sera hashé par UserRepository
                    'name' => $data['name'] ?? null,
                ]
            );

            // En mode API, créer un access token
            if ($request->attributes->get('better_auth.mode') === 'api') {
                $tokenService = $this->container->get('BetterAuth\Core\TokenService');
                $accessToken = $tokenService->sign([
                    'sub' => $user->getId(),
                    'type' => 'access_token',
                ], 7200);

                return $this->json([
                    'access_token' => $accessToken,
                    'user' => [
                        'id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'name' => $user->getName(),
                    ],
                ]);
            }

            // En mode session/hybrid, créer une session
            $sessionService = $this->container->get('BetterAuth\Core\SessionService');
            $session = $sessionService->create(
                $user,
                $request->getClientIp() ?? '127.0.0.1',
                $request->headers->get('User-Agent') ?? 'Unknown'
            );

            return $this->json([
                'session' => $session->getToken(),
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'name' => $user->getName(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Récupérer une session guest
     * GET /auth/guest/{token}
     */
    #[Route('/{token}', name: 'get', methods: ['GET'])]
    public function get(string $token): JsonResponse
    {
        try {
            $guestSession = $this->guestSessionProvider->getGuestSession($token);
            
            if (!$guestSession) {
                return $this->json(['error' => 'Guest session not found'], 404);
            }

            return $this->json([
                'id' => $guestSession->id,
                'expiresAt' => $guestSession->expiresAt,
                'metadata' => $guestSession->metadata,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Supprimer une session guest
     * DELETE /auth/guest/{id}
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        try {
            $this->guestSessionProvider->deleteGuestSession($id);
            return $this->json(['message' => 'Guest session deleted']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
```

### Cas d'usage : Panier d'achat

```javascript
// Frontend - Créer une session guest pour un panier
const response = await fetch('/auth/guest/create', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    metadata: { cartId: 'cart_123' }
  })
});

const { token } = await response.json();
localStorage.setItem('guest_token', token);

// Plus tard, lors de l'inscription
const convertResponse = await fetch('/auth/guest/convert', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    guestToken: localStorage.getItem('guest_token'),
    email: 'user@example.com',
    password: 'password123',
    name: 'John Doe'
  })
});

// La session guest est convertie en utilisateur
// Le panier est préservé via les métadonnées
```

### Nettoyage automatique

Créez une commande pour nettoyer les sessions expirées :

```php
// src/Command/CleanupGuestSessionsCommand.php
<?php

namespace App\Command;

use BetterAuth\Providers\GuestSessionProvider\GuestSessionProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:cleanup-guest-sessions',
    description: 'Cleanup expired guest sessions'
)]
class CleanupGuestSessionsCommand extends Command
{
    public function __construct(
        private readonly GuestSessionProvider $guestSessionProvider
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->guestSessionProvider->cleanupExpiredSessions();
        $output->writeln("Cleaned up {$count} expired guest sessions");
        
        return Command::SUCCESS;
    }
}
```

Ajoutez au crontab :
```bash
# Nettoyer les sessions guest expirées chaque jour à 2h du matin
0 2 * * * cd /path/to/project && php bin/console app:cleanup-guest-sessions
```

---

## Résumé

### Passkey
- ✅ Configuration `rpId` et `rpName`
- ✅ Entité `PasskeyData`
- ✅ Controller avec endpoints d'enregistrement et d'authentification
- ⚠️ Utiliser une bibliothèque WebAuthn complète en production

### OIDC
- ✅ Configuration `issuer`
- ✅ Entités `OAuthClient`, `AuthorizationCode`
- ✅ Controller avec endpoints OAuth/OIDC standards
- ✅ Commande pour créer des clients OAuth

### GuestSessionProvider
- ✅ Déjà configuré dans `services.yaml`
- ✅ Fonctionne dans tous les modes (api/session/hybrid)
- ✅ Controller pour créer/convertir des sessions guest
- ✅ Commande de nettoyage automatique

