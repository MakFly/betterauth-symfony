<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Installer;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Generates YAML configuration, registers the bundle, configures services and the .env file.
 */
class ConfigGenerator
{
    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    /**
     * Generate the better_auth.yaml configuration file.
     *
     * @param array<string, mixed> $state
     * @param string[]             $providers
     */
    public function generateConfiguration(
        SymfonyStyle $io,
        string $projectDir,
        string $mode,
        array $providers,
        array $state
    ): void {
        $io->section('⚙️  Step 3/6: Generating Configuration');

        $configDir = $projectDir . '/config/packages';
        $configFile = $configDir . '/better_auth.yaml';

        if ($this->filesystem->exists($configFile)) {
            if (!$io->confirm('  Configuration file already exists. Overwrite?', false)) {
                $io->writeln('  <fg=yellow>⊘</> Skipped configuration');

                return;
            }
        }

        $templateFile = dirname(__DIR__, 2) . '/Resources/templates/config/better_auth.yaml.tpl';
        $content = file_get_contents($templateFile);

        $content = str_replace('{{MODE}}', $mode, $content);
        $oauthConfig = $this->generateOAuthConfig($providers);
        $content = str_replace('{{OAUTH_PROVIDERS}}', $oauthConfig, $content);

        if (!$this->filesystem->exists($configDir)) {
            $this->filesystem->mkdir($configDir);
        }

        $this->filesystem->dumpFile($configFile, $content);
        $io->writeln('  <fg=green>✓</> Created config/packages/better_auth.yaml');
    }

    /**
     * Register the BetterAuthBundle in config/bundles.php.
     */
    public function registerBundle(SymfonyStyle $io, string $projectDir): void
    {
        $io->section('🔌 Step 2/5: Registering Bundle');

        $bundlesFile = $projectDir . '/config/bundles.php';
        if (!$this->filesystem->exists($bundlesFile)) {
            $io->writeln('  <fg=yellow>⊘</> bundles.php not found, skipping...');

            return;
        }

        $bundlesContent = file_get_contents($bundlesFile);
        if (str_contains($bundlesContent, 'BetterAuthBundle')) {
            $io->writeln('  <fg=green>✓</> Bundle already registered');

            return;
        }

        $bundlesContent = str_replace(
            '];',
            "    BetterAuth\\Symfony\\BetterAuthBundle::class => ['all' => true],\n];",
            $bundlesContent
        );
        $this->filesystem->dumpFile($bundlesFile, $bundlesContent);
        $io->writeln('  <fg=green>✓</> Bundle registered in bundles.php');
    }

    /**
     * Configure repository services in config/services.yaml for UUID strategy.
     */
    public function configureServices(SymfonyStyle $io, string $projectDir, string $idStrategy): void
    {
        $io->section('⚙️  Step 5/6: Configuring Services');

        $servicesFile = $projectDir . '/config/services.yaml';
        if (!$this->filesystem->exists($servicesFile)) {
            $io->writeln('  <fg=yellow>⊘</> services.yaml not found, skipping...');
            return;
        }

        $servicesContent = file_get_contents($servicesFile);

        if (str_contains($servicesContent, 'DoctrineUserRepository')) {
            $io->writeln('  <fg=green>✓</> BetterAuth repositories already configured');
            return;
        }

        if ($idStrategy === 'uuid') {
            $repositoryConfig = <<<'YAML'

    # BetterAuth - Configure repositories to use App entities
    BetterAuth\Symfony\Storage\Doctrine\DoctrineUserRepository:
        arguments:
            $userClass: 'App\Entity\User'

    BetterAuth\Symfony\Storage\Doctrine\DoctrineSessionRepository:
        arguments:
            $sessionClass: 'App\Entity\Session'

    BetterAuth\Symfony\Storage\Doctrine\DoctrineRefreshTokenRepository:
        arguments:
            $refreshTokenClass: 'App\Entity\RefreshToken'

    BetterAuth\Symfony\Storage\Doctrine\DoctrineMagicLinkRepository:
        arguments:
            $tokenClass: 'App\Entity\MagicLinkToken'

    BetterAuth\Symfony\Storage\Doctrine\DoctrineEmailVerificationRepository:
        arguments:
            $tokenClass: 'App\Entity\EmailVerificationToken'

    BetterAuth\Symfony\Storage\Doctrine\DoctrinePasswordResetRepository:
        arguments:
            $tokenClass: 'App\Entity\PasswordResetToken'

    BetterAuth\Symfony\Storage\Doctrine\DoctrineTotpRepository:
        arguments:
            $totpClass: 'App\Entity\TotpData'

YAML;

            $servicesContent .= $repositoryConfig;
            $this->filesystem->dumpFile($servicesFile, $servicesContent);
            $io->writeln('  <fg=green>✓</> Configured repository services in services.yaml');
        } else {
            $io->writeln('  <fg=yellow>⊘</> INT strategy: services configuration not needed');
        }
    }

    /**
     * Add BetterAuth environment variables to the .env file.
     *
     * @param string[] $providers
     */
    public function updateEnvFile(
        SymfonyStyle $io,
        string $projectDir,
        array $providers = [],
        string $appName = 'My App'
    ): void {
        $io->section('🔑 Step 6/6: Environment Configuration');

        $envFile = $projectDir . '/.env';
        if (!$this->filesystem->exists($envFile)) {
            $io->writeln('  <fg=yellow>⊘</> .env file not found, skipping...');

            return;
        }

        $envContent = file_get_contents($envFile);
        $modified = false;

        if (str_contains($envContent, 'BETTER_AUTH_SECRET=')) {
            $io->writeln('  <fg=green>✓</> BETTER_AUTH_SECRET already exists in .env');
        } else {
            $secret = bin2hex(random_bytes(32));
            $envContent .= "\n# BetterAuth Configuration\n";
            $envContent .= "BETTER_AUTH_SECRET=$secret\n";
            $envContent .= "APP_URL=http://localhost:8000\n";
            $io->writeln('  <fg=green>✓</> Added BETTER_AUTH_SECRET to .env');
            $io->writeln('  <fg=green>✓</> Added APP_URL to .env');
            $modified = true;
        }

        if (str_contains($envContent, 'APP_NAME=')) {
            $io->writeln('  <fg=green>✓</> APP_NAME already exists in .env');
        } else {
            $escapedAppName = str_contains($appName, ' ') ? "\"$appName\"" : $appName;
            $envContent .= "APP_NAME=$escapedAppName\n";
            $io->writeln(sprintf('  <fg=green>✓</> Added APP_NAME="%s" to .env', $appName));
            $modified = true;
        }

        if (!empty($providers)) {
            $oauthAdded = false;

            foreach ($providers as $provider) {
                $upperProvider = strtoupper($provider);
                $clientIdVar = "{$upperProvider}_CLIENT_ID";
                $clientSecretVar = "{$upperProvider}_CLIENT_SECRET";

                if (str_contains($envContent, "{$clientIdVar}=")) {
                    $io->writeln(sprintf('  <fg=green>✓</> %s already exists in .env', $clientIdVar));
                } else {
                    if (!$oauthAdded) {
                        $envContent .= "\n# OAuth Configuration\n";
                        $oauthAdded = true;
                    }
                    $envContent .= "{$clientIdVar}=\n";
                    $envContent .= "{$clientSecretVar}=\n";
                    $io->writeln(sprintf('  <fg=green>✓</> Added %s and %s to .env', $clientIdVar, $clientSecretVar));
                    $modified = true;
                }
            }
        }

        if ($modified) {
            $this->filesystem->dumpFile($envFile, $envContent);
        }
    }

    /**
     * Generate OAuth provider YAML configuration block.
     *
     * @param string[] $providers
     */
    public function generateOAuthConfig(array $providers): string
    {
        if (empty($providers)) {
            return '            # No OAuth providers enabled';
        }

        $config = [];
        foreach ($providers as $provider) {
            $upperProvider = strtoupper($provider);
            $config[] = sprintf("            %s:\n", $provider);
            $config[] = sprintf("                enabled: true\n");
            $config[] = sprintf("                client_id: '%%env(%s_CLIENT_ID)%%'\n", $upperProvider);
            $config[] = sprintf("                client_secret: '%%env(%s_CLIENT_SECRET)%%'\n", $upperProvider);
            $config[] = sprintf("                redirect_uri: '%%env(APP_URL)%%/auth/oauth/%s/callback'\n", $provider);
        }

        return rtrim(implode('', $config));
    }
}
