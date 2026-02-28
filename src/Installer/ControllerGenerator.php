<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Installer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Generates AuthController and optional controller files during BetterAuth installation.
 */
class ControllerGenerator
{
    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    /**
     * Generate controller files into the project.
     *
     * @param array<string, mixed> $state
     */
    public function generateController(
        SymfonyStyle $io,
        string $projectDir,
        array $state,
        InputInterface $input
    ): void {
        if ($input->getOption('skip-controller')) {
            $io->section('🎮 Step 4/6: Controllers (Skipped)');

            return;
        }

        $io->section('🎮 Step 4/6: Generating Controllers');

        $templatesDir = dirname(__DIR__) . '/Resources/templates/controller';
        $controllerDir = $projectDir . '/src/Controller';
        $traitDir = $controllerDir . '/Trait';

        // Create directories
        if (!$this->filesystem->exists($controllerDir)) {
            $this->filesystem->mkdir($controllerDir);
        }
        if (!$this->filesystem->exists($traitDir)) {
            $this->filesystem->mkdir($traitDir);
        }

        // Core controllers (always installed)
        $coreControllers = [
            'Trait/ApiResponseTrait' => 'ApiResponseTrait.php.tpl',
            'AuthController' => 'AuthController.php.tpl',
            'PasswordController' => 'PasswordController.php.tpl',
            'SessionsController' => 'SessionsController.php.tpl',
        ];

        // Optional controllers (ask user)
        $optionalControllers = [
            'oauth' => ['OAuthController' => 'OAuthController.php.tpl'],
            'email-verification' => ['EmailVerificationController' => 'EmailVerificationController.php.tpl'],
            'magic-link' => ['MagicLinkController' => 'MagicLinkController.php.tpl'],
            'guest' => ['GuestSessionController' => 'GuestSessionController.php.tpl'],
            'account-link' => ['AccountLinkController' => 'AccountLinkController.php.tpl'],
            'devices' => ['DeviceController' => 'DeviceController.php.tpl'],
        ];

        // Check for existing controllers in both locations
        $existingControllers = $this->detectExistingControllers($projectDir);
        if (!empty($existingControllers)) {
            $io->writeln('');
            $io->writeln('  <fg=yellow>Detected existing controllers:</>');
            foreach ($existingControllers as $name => $path) {
                $io->writeln(sprintf('    • %s at %s', $name, $path));
            }
            $io->writeln('');
        }

        // Ask for optional controllers
        $io->writeln('');
        $io->writeln('  <fg=cyan>Optional Controllers:</fg>');
        $io->writeln('  You can add more controllers later with: <info>better-auth:add-controller</info>');
        $io->writeln('');

        $selectedOptional = [];
        if ($io->confirm('Do you want to install additional controllers?', false)) {
            foreach ($optionalControllers as $key => $config) {
                $descriptions = [
                    'oauth' => 'OAuth (Google, GitHub, Facebook, etc.)',
                    'email-verification' => 'Email verification flow',
                    'magic-link' => 'Passwordless authentication',
                    'guest' => 'Guest/anonymous sessions',
                    'account-link' => 'Link third-party accounts',
                    'devices' => 'Device management & tracking',
                ];
                if ($io->confirm(sprintf('  Add %s?', $descriptions[$key]), false)) {
                    $selectedOptional = array_merge($selectedOptional, $config);
                }
            }
        }

        $controllers = array_merge($coreControllers, $selectedOptional);

        $generatedCount = 0;
        foreach ($controllers as $name => $template) {
            $targetFile = $controllerDir . '/' . $name . '.php';
            $templateFile = $templatesDir . '/' . $template;

            if (!$this->filesystem->exists($templateFile)) {
                $io->writeln(sprintf('  <fg=yellow>⊘</> Template not found: %s', $template));
                continue;
            }

            $controllerName = basename($name, '.php');
            $legacyPath = $projectDir . '/src/Controller/Api/' . $name . '.php';

            if ($this->filesystem->exists($targetFile)) {
                if (!$io->confirm(sprintf('  %s already exists. Overwrite?', $name), false)) {
                    $io->writeln(sprintf('  <fg=yellow>⊘</> Skipped %s', $name));
                    continue;
                }
            } elseif ($this->filesystem->exists($legacyPath)) {
                $io->writeln(sprintf('  <fg=yellow>⚠</> %s exists in legacy location (src/Controller/Api/)', $controllerName));
                if (!$io->confirm(sprintf('  Generate new %s in src/Controller/ anyway?', $controllerName), false)) {
                    $io->writeln(sprintf('  <fg=yellow>⊘</> Skipped %s', $name));
                    continue;
                }
            }

            $content = file_get_contents($templateFile);
            $this->filesystem->dumpFile($targetFile, $content);
            $io->writeln(sprintf('  <fg=green>✓</> Generated %s.php', $name));
            $generatedCount++;
        }

        if ($generatedCount > 0) {
            $io->writeln('  <fg=green>✓</> Routes automatically configured via attributes');
            $io->writeln('');
            $io->writeln('  <fg=cyan>Generated structure:</>');
            $io->writeln('    src/Controller/');
            $io->writeln('    ├── Trait/ApiResponseTrait.php');
            $io->writeln('    ├── AuthController.php');
            $io->writeln('    ├── PasswordController.php');
            $io->writeln('    └── SessionsController.php');
        }
    }

    /**
     * Detect existing controllers in both standard and legacy locations.
     *
     * @return array<string, string> controller name => relative path
     */
    public function detectExistingControllers(string $projectDir): array
    {
        $controllers = [
            'AuthController',
            'PasswordController',
            'SessionsController',
            'OAuthController',
            'MagicLinkController',
            'EmailVerificationController',
            'GuestSessionController',
            'AccountLinkController',
            'DeviceController',
        ];

        $found = [];
        foreach ($controllers as $controller) {
            $standardPath = $projectDir . '/src/Controller/' . $controller . '.php';
            if ($this->filesystem->exists($standardPath)) {
                $found[$controller] = 'src/Controller/' . $controller . '.php';
                continue;
            }

            $legacyPath = $projectDir . '/src/Controller/Api/' . $controller . '.php';
            if ($this->filesystem->exists($legacyPath)) {
                $found[$controller] = 'src/Controller/Api/' . $controller . '.php';
            }
        }

        return $found;
    }
}
