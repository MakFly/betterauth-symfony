<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Installer;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Generates Doctrine entity files from templates during BetterAuth installation.
 */
class EntityGenerator
{
    /**
     * Entity template map: entity name => [id-strategy => template filename].
     *
     * @var array<string, array<string, string|null>>
     */
    private const ENTITY_TEMPLATES = [
        'User' => [
            'uuid' => 'user.uuid.php.tpl',
            'int' => 'user.int.php.tpl',
        ],
        'Session' => [
            'uuid' => 'session.uuid.php.tpl',
            'int' => 'session.int.php.tpl',
        ],
        'RefreshToken' => [
            'uuid' => 'refresh_token.uuid.php.tpl',
            'int' => 'refresh_token.int.php.tpl',
        ],
        'AccountLink' => [
            'uuid' => 'account_link.uuid.php.tpl',
            'int' => 'account_link.int.php.tpl',
        ],
        'DeviceInfo' => [
            'uuid' => 'device_info.uuid.php.tpl',
            'int' => 'device_info.int.php.tpl',
        ],
        'SecurityEvent' => [
            'uuid' => 'security_event.uuid.php.tpl',
            'int' => 'security_event.int.php.tpl',
        ],
        'SuspiciousActivity' => [
            'uuid' => 'suspicious_activity.uuid.php.tpl',
            'int' => 'suspicious_activity.int.php.tpl',
        ],
        'SessionActivity' => [
            'uuid' => 'session_activity.uuid.php.tpl',
            'int' => 'session_activity.int.php.tpl',
        ],
        'MagicLinkToken' => [
            'uuid' => 'magic_link_token.uuid.php.tpl',
            'int' => null,
        ],
        'EmailVerificationToken' => [
            'uuid' => 'email_verification_token.uuid.php.tpl',
            'int' => null,
        ],
        'PasswordResetToken' => [
            'uuid' => 'password_reset_token.uuid.php.tpl',
            'int' => null,
        ],
        'TotpData' => [
            'uuid' => 'totp_data.uuid.php.tpl',
            'int' => null,
        ],
    ];

    /**
     * Optional User fields that can be excluded.
     */
    private const OPTIONAL_USER_FIELDS = ['name', 'avatar'];

    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    /**
     * Generate all entity files for the given ID strategy.
     *
     * @param array<string, mixed> $state
     * @param string[]             $excludedFields
     * @return string[]            Names of generated entities
     */
    public function generateEntities(
        SymfonyStyle $io,
        string $projectDir,
        string $idStrategy,
        array $state,
        array $excludedFields = []
    ): array {
        $io->section('📦 Step 1/5: Generating Entities');

        $entitiesDir = $projectDir . '/src/Entity';
        if (!$this->filesystem->exists($entitiesDir)) {
            $this->filesystem->mkdir($entitiesDir);
        }

        $templatesDir = dirname(__DIR__, 2) . '/Resources/templates/entities';
        $generatedFiles = [];

        foreach (self::ENTITY_TEMPLATES as $entityName => $templates) {
            // Skip if template not available for this ID strategy
            if ($templates[$idStrategy] === null) {
                continue;
            }

            $templateFile = $templatesDir . '/' . $templates[$idStrategy];
            $targetFile = $entitiesDir . '/' . $entityName . '.php';

            if ($this->filesystem->exists($targetFile)) {
                if (!$io->confirm(sprintf('  Entity %s already exists. Overwrite?', $entityName), false)) {
                    $io->writeln(sprintf('  <fg=yellow>⊘</> Skipped %s', $entityName));
                    continue;
                }
            }

            $content = file_get_contents($templateFile);

            // Process User entity template with field exclusions
            if ($entityName === 'User') {
                $content = $this->processUserTemplate($content, $excludedFields);
            }

            $this->filesystem->dumpFile($targetFile, $content);
            $generatedFiles[] = $entityName;

            if ($entityName === 'User' && !empty($excludedFields)) {
                $io->writeln(sprintf('  <fg=green>✓</> Generated %s.php <fg=gray>(excluded: %s)</>', $entityName, implode(', ', $excludedFields)));
            } else {
                $io->writeln(sprintf('  <fg=green>✓</> Generated %s.php', $entityName));
            }
        }

        return $generatedFiles;
    }

    /**
     * Process the User entity template to apply field exclusions.
     *
     * @param string[] $excludedFields
     */
    public function processUserTemplate(string $content, array $excludedFields): string
    {
        $allOptionalFieldsExcluded = count(array_intersect($excludedFields, self::OPTIONAL_USER_FIELDS)) === count(self::OPTIONAL_USER_FIELDS);
        $someFieldsExcluded = !empty($excludedFields) && !$allOptionalFieldsExcluded;

        if ($allOptionalFieldsExcluded) {
            // Minimal mode: no trait, no optional fields
            $content = str_replace("{{USE_PROFILE_TRAIT}}\n", '', $content);
            $content = str_replace("{{PROFILE_TRAIT}}\n", '', $content);
            $content = str_replace("{{CUSTOM_FIELDS}}\n", '', $content);
        } elseif ($someFieldsExcluded) {
            // Partial mode: generate individual fields instead of trait
            $content = str_replace("{{USE_PROFILE_TRAIT}}\n", "use Doctrine\\DBAL\\Types\\Types;\n", $content);
            $content = str_replace("{{PROFILE_TRAIT}}\n", '', $content);

            $customFields = $this->generateCustomFields($excludedFields);
            $content = str_replace("{{CUSTOM_FIELDS}}\n", $customFields, $content);
        } else {
            // Full mode: use the profile trait
            $content = str_replace("{{USE_PROFILE_TRAIT}}\n", "use BetterAuth\\Symfony\\Model\\UserProfileTrait;\n", $content);
            $content = str_replace("{{PROFILE_TRAIT}}", "    use UserProfileTrait;\n", $content);
            $content = str_replace("{{CUSTOM_FIELDS}}\n", '', $content);
        }

        return $content;
    }

    /**
     * Generate inline field definitions for optional fields that are not excluded.
     *
     * @param string[] $excludedFields
     */
    public function generateCustomFields(array $excludedFields): string
    {
        $fields = [];

        if (!in_array('name', $excludedFields, true)) {
            $fields[] = <<<'PHP'

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $name = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }
PHP;
        }

        if (!in_array('avatar', $excludedFields, true)) {
            $fields[] = <<<'PHP'

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    protected ?string $avatar = null;

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }
PHP;
        }

        return empty($fields) ? '' : implode("\n", $fields) . "\n";
    }
}
