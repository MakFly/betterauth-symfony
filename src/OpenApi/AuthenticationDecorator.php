<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\OpenApi;
use ArrayObject;
use Symfony\Component\Routing\RouterInterface;

/**
 * Decorator to add BetterAuth authentication endpoints to OpenAPI documentation.
 *
 * This decorator automatically adds all BetterAuth authentication endpoints
 * to the API Platform OpenAPI specification, including:
 * - Register/Login endpoints
 * - Token refresh and logout
 * - OAuth provider endpoints
 * - Bearer authentication scheme (Paseto V4)
 */
final class AuthenticationDecorator implements OpenApiFactoryInterface
{
    private ?string $detectedPrefix = null;
    private ?string $configuredPrefix;

    public function __construct(
        private readonly OpenApiFactoryInterface $decorated,
        ?string $authPathPrefix = null,
        private readonly ?RouterInterface $router = null
    ) {
        // Store configured prefix (can be null for auto-detection)
        $this->configuredPrefix = $authPathPrefix;
    }

    private function getAuthPathPrefix(): string
    {
        // Return cached result if available
        if ($this->detectedPrefix !== null) {
            return $this->detectedPrefix;
        }

        // PRIORITY 1: Try to detect from routes (most accurate, reflects actual config)
        if ($this->router !== null) {
            try {
                $routeCollection = $this->router->getRouteCollection();
                
                // Try common auth route name patterns
                $authRoutePatterns = [
                    'api_v1_auth_login', 'api_v1_auth_register', 'api_v1_auth_me',
                    'api_v2_auth_login', 'api_v2_auth_register', 'api_v2_auth_me',
                    'api_auth_login', 'api_auth_register', 'api_auth_me',
                    'better_auth_login', 'better_auth_register',
                ];
                
                foreach ($authRoutePatterns as $routeName) {
                    $route = $routeCollection->get($routeName);
                    if ($route !== null) {
                        $path = $route->getPath();
                        // Extract prefix: /api/v2/auth/login -> /api/v2/auth
                        if (preg_match('#^(.*?/auth)(?:/|$)#', $path, $matches)) {
                            $this->detectedPrefix = $matches[1];
                            return $this->detectedPrefix;
                        }
                    }
                }

                // Fallback: search all routes for auth patterns
                foreach ($routeCollection as $name => $route) {
                    $path = $route->getPath();
                    if (str_contains($path, '/auth/') || str_ends_with($path, '/auth')) {
                        // Extract prefix: /api/v2/auth/login -> /api/v2/auth
                        if (preg_match('#^(.*?/auth)(?:/|$)#', $path, $matches)) {
                            $this->detectedPrefix = $matches[1];
                            return $this->detectedPrefix;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Router not ready yet, fall through to config
            }
        }

        // PRIORITY 2: Use configured prefix if provided
        if ($this->configuredPrefix !== null) {
            $this->detectedPrefix = $this->configuredPrefix;
            return $this->detectedPrefix;
        }

        // PRIORITY 3: Default fallback
        $this->detectedPrefix = '/auth';
        return $this->detectedPrefix;
    }

    /**
     * Remove 'Authentication' tag from OAuth endpoints to avoid duplication.
     */
    private function removeAuthenticationTagFromOAuth(PathItem $pathItem): PathItem
    {
        $methods = ['get', 'post', 'put', 'delete', 'patch', 'head', 'options', 'trace'];
        $newPathItem = $pathItem;

        foreach ($methods as $method) {
            $getter = 'get' . ucfirst($method);
            $setter = 'with' . ucfirst($method);

            if (!method_exists($pathItem, $getter) || !method_exists($pathItem, $setter)) {
                continue;
            }

            /** @var Operation|null $operation */
            $operation = $pathItem->$getter();
            if ($operation === null) {
                continue;
            }

            $tags = $operation->getTags();
            if ($tags === null || !in_array('Authentication', $tags, true)) {
                continue;
            }

            // Remove 'Authentication' tag, keep only 'OAuth'
            $newTags = array_filter($tags, fn($tag) => $tag !== 'Authentication');
            if (empty($newTags)) {
                // If no tags left, keep at least 'OAuth'
                $newTags = ['OAuth'];
            }

            $newOperation = $operation->withTags(array_values($newTags));
            $newPathItem = $newPathItem->$setter($newOperation);
        }

        return $newPathItem;
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        // Detect auth prefix FIRST before adding endpoints
        $authPrefix = $this->getAuthPathPrefix();

        // Replace any existing /auth/ paths with the dynamic prefix BEFORE adding new ones
        $openApi = $this->replaceAuthPaths($openApi);

        // Add Bearer authentication security scheme
        $components = $openApi->getComponents();
        $securitySchemes = $components->getSecuritySchemes() ?? new ArrayObject();
        $securitySchemes['bearerAuth'] = [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'Paseto V4',
            'description' => 'Enter your Paseto V4 access token',
        ];

        $components = $components->withSecuritySchemes($securitySchemes);
        $openApi = $openApi->withComponents($components);

        // Add Authentication tag
        $tags = $openApi->getTags();
        $tags[] = new \ApiPlatform\OpenApi\Model\Tag('Authentication', 'BetterAuth authentication endpoints');
        $openApi = $openApi->withTags($tags);

        // Register endpoint
        $openApi = $this->addRegisterEndpoint($openApi);

        // Login endpoint
        $openApi = $this->addLoginEndpoint($openApi);

        // Get current user endpoint
        $openApi = $this->addMeEndpoint($openApi);

        // Refresh token endpoint
        $openApi = $this->addRefreshEndpoint($openApi);

        // Logout endpoint
        $openApi = $this->addLogoutEndpoint($openApi);

        // Revoke all sessions endpoint
        $openApi = $this->addRevokeAllEndpoint($openApi);

        // OAuth endpoints
        $openApi = $this->addOAuthEndpoints($openApi);

        return $openApi;
    }

    /**
     * Replace all /auth/ paths with the dynamic prefix and remove Authentication tag from OAuth endpoints.
     * Also ensures all auth endpoints use the correct dynamic prefix.
     */
    private function replaceAuthPaths(OpenApi $openApi): OpenApi
    {
        $authPrefix = $this->getAuthPathPrefix();
        $paths = $openApi->getPaths();
        $newPaths = new Paths();

        foreach ($paths->getPaths() as $path => $pathItem) {
            $isOAuthEndpoint = str_contains($path, '/oauth/');
            $isAuthEndpoint = str_contains($path, '/auth');

            // Replace /auth/ paths with the dynamic prefix
            if (str_starts_with($path, '/auth/')) {
                $newPath = str_replace('/auth/', $authPrefix . '/', $path);
                $pathItemToAdd = $pathItem;

                // Remove 'Authentication' tag from OAuth endpoints
                if ($isOAuthEndpoint) {
                    $pathItemToAdd = $this->removeAuthenticationTagFromOAuth($pathItem);
                }

                $newPaths->addPath($newPath, $pathItemToAdd);
            } elseif ($isAuthEndpoint && !str_starts_with($path, $authPrefix)) {
                // If it's an auth endpoint but doesn't start with the correct prefix, replace it
                // This handles cases where endpoints were added with /auth/ instead of the dynamic prefix
                if (preg_match('#^/auth(/.*)$#', $path, $matches)) {
                    $newPath = $authPrefix . $matches[1];
                } elseif (preg_match('#^(.+?)/auth(/.*)$#', $path, $matches)) {
                    // Already has a prefix, but might be wrong - replace with detected prefix
                    $newPath = $authPrefix . $matches[2];
                } else {
                    $newPath = $path;
                }

                $pathItemToAdd = $pathItem;
                if ($isOAuthEndpoint) {
                    $pathItemToAdd = $this->removeAuthenticationTagFromOAuth($pathItem);
                }

                $newPaths->addPath($newPath, $pathItemToAdd);
            } else {
                $newPaths->addPath($path, $pathItem);
            }
        }

        return $openApi->withPaths($newPaths);
    }

    private function addRegisterEndpoint(OpenApi $openApi): OpenApi
    {
        $pathItem = new PathItem(
            post: new Operation(
                operationId: 'authRegister',
                tags: ['Authentication'],
                summary: 'Register a new user',
                description: 'Create a new user account',
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'user@example.com'],
                                    'password' => ['type' => 'string', 'format' => 'password', 'example' => 'SecurePassword123'],
                                    'username' => ['type' => 'string', 'example' => 'JohnDoe', 'nullable' => true],
                                ],
                                'required' => ['email', 'password'],
                            ],
                        ],
                    ])
                ),
                responses: [
                    '201' => [
                        'description' => 'User created successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'string', 'example' => '06DB1F28JAB2JHV1VXZVTKJEHM'],
                                                'email' => ['type' => 'string', 'example' => 'user@example.com'],
                                                'username' => ['type' => 'string', 'example' => 'JohnDoe'],
                                                'emailVerified' => ['type' => 'boolean', 'example' => false],
                                                'createdAt' => ['type' => 'string', 'format' => 'date-time', 'example' => '2025-11-23 12:42:00'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '400' => ['description' => 'Invalid input'],
                ],
            )
        );

        $paths = $openApi->getPaths();
        $paths->addPath($this->getAuthPathPrefix() . '/register', $pathItem);

        return $openApi->withPaths($paths);
    }

    private function addLoginEndpoint(OpenApi $openApi): OpenApi
    {
        $pathItem = new PathItem(
            post: new Operation(
                operationId: 'authLogin',
                tags: ['Authentication'],
                summary: 'Login user',
                description: 'Authenticate user and receive access & refresh tokens',
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'user@example.com'],
                                    'password' => ['type' => 'string', 'format' => 'password', 'example' => 'SecurePassword123'],
                                ],
                                'required' => ['email', 'password'],
                            ],
                        ],
                    ])
                ),
                responses: [
                    '200' => [
                        'description' => 'Login successful',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'user' => ['type' => 'object'],
                                        'access_token' => ['type' => 'string', 'example' => 'v4.local.xxx...'],
                                        'refresh_token' => ['type' => 'string', 'example' => 'xxx...'],
                                        'token_type' => ['type' => 'string', 'example' => 'Bearer'],
                                        'expires_in' => ['type' => 'integer', 'example' => 7200],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Invalid credentials'],
                ],
            )
        );

        $paths = $openApi->getPaths();
        $paths->addPath($this->getAuthPathPrefix() . '/login', $pathItem);

        return $openApi->withPaths($paths);
    }

    private function addMeEndpoint(OpenApi $openApi): OpenApi
    {
        $pathItem = new PathItem(
            get: new Operation(
                operationId: 'authMe',
                tags: ['Authentication'],
                summary: 'Get current user',
                description: 'Get current authenticated user information',
                security: [['bearerAuth' => []]],
                responses: [
                    '200' => [
                        'description' => 'User information',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'string'],
                                        'email' => ['type' => 'string'],
                                        'username' => ['type' => 'string'],
                                        'emailVerified' => ['type' => 'boolean'],
                                        'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                ],
            )
        );

        $paths = $openApi->getPaths();
        $paths->addPath($this->getAuthPathPrefix() . '/me', $pathItem);

        return $openApi->withPaths($paths);
    }

    private function addRefreshEndpoint(OpenApi $openApi): OpenApi
    {
        $pathItem = new PathItem(
            post: new Operation(
                operationId: 'authRefresh',
                tags: ['Authentication'],
                summary: 'Refresh access token',
                description: 'Refresh access token using refresh token',
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'refreshToken' => ['type' => 'string', 'example' => 'xxx...'],
                                ],
                                'required' => ['refreshToken'],
                            ],
                        ],
                    ])
                ),
                responses: [
                    '200' => [
                        'description' => 'Token refreshed',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'access_token' => ['type' => 'string'],
                                        'refresh_token' => ['type' => 'string'],
                                        'token_type' => ['type' => 'string'],
                                        'expires_in' => ['type' => 'integer'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Invalid refresh token'],
                ],
            )
        );

        $paths = $openApi->getPaths();
        $paths->addPath($this->getAuthPathPrefix() . '/refresh', $pathItem);

        return $openApi->withPaths($paths);
    }

    private function addLogoutEndpoint(OpenApi $openApi): OpenApi
    {
        $pathItem = new PathItem(
            post: new Operation(
                operationId: 'authLogout',
                tags: ['Authentication'],
                summary: 'Logout user',
                description: 'Logout current user (revoke current session)',
                security: [['bearerAuth' => []]],
                responses: [
                    '200' => [
                        'description' => 'Logout successful',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => ['type' => 'string', 'example' => 'Logged out successfully'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                ],
            )
        );

        $paths = $openApi->getPaths();
        $paths->addPath($this->getAuthPathPrefix() . '/logout', $pathItem);

        return $openApi->withPaths($paths);
    }

    private function addRevokeAllEndpoint(OpenApi $openApi): OpenApi
    {
        $pathItem = new PathItem(
            post: new Operation(
                operationId: 'authRevokeAll',
                tags: ['Authentication'],
                summary: 'Revoke all sessions',
                description: 'Revoke all refresh tokens for current user',
                security: [['bearerAuth' => []]],
                responses: [
                    '200' => [
                        'description' => 'All sessions revoked',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => ['type' => 'string', 'example' => 'All sessions revoked successfully'],
                                        'count' => ['type' => 'integer', 'example' => 3],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                ],
            )
        );

        $paths = $openApi->getPaths();
        $paths->addPath($this->getAuthPathPrefix() . '/revoke-all', $pathItem);

        return $openApi->withPaths($paths);
    }

    private function addOAuthEndpoints(OpenApi $openApi): OpenApi
    {
        $paths = $openApi->getPaths();
        $authPrefix = $this->getAuthPathPrefix();

        // OAuth redirect
        $pathItem = new PathItem(
            get: new Operation(
                operationId: 'authOAuthRedirect',
                tags: ['OAuth'],
                summary: 'Get OAuth authorization URL',
                description: 'Get OAuth provider authorization URL (Google, GitHub, etc.)',
                parameters: [
                    [
                        'name' => 'provider',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string', 'enum' => ['google', 'github', 'facebook', 'microsoft', 'discord']],
                        'example' => 'google',
                    ],
                ],
                responses: [
                    '200' => [
                        'description' => 'OAuth URL',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'url' => ['type' => 'string', 'example' => 'https://accounts.google.com/o/oauth2/auth?...'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            )
        );
        $paths->addPath($authPrefix . '/oauth/{provider}', $pathItem);

        // OAuth callback
        $pathItem = new PathItem(
            get: new Operation(
                operationId: 'authOAuthCallback',
                tags: ['OAuth'],
                summary: 'OAuth callback',
                description: 'Handle OAuth provider callback with authorization code',
                parameters: [
                    [
                        'name' => 'provider',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string', 'enum' => ['google', 'github', 'facebook', 'microsoft', 'discord']],
                    ],
                    [
                        'name' => 'code',
                        'in' => 'query',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                        'description' => 'Authorization code from OAuth provider',
                    ],
                ],
                responses: [
                    '200' => [
                        'description' => 'Authentication successful',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'user' => ['type' => 'object'],
                                        'access_token' => ['type' => 'string'],
                                        'refresh_token' => ['type' => 'string'],
                                        'token_type' => ['type' => 'string'],
                                        'expires_in' => ['type' => 'integer'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            )
        );
        $paths->addPath($authPrefix . '/oauth/{provider}/callback', $pathItem);

        return $openApi->withPaths($paths);
    }
}

