<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'better-auth:user-fields',
    description: 'Add or remove optional fields from User entity'
)]
class UserFieldsCommand extends Command
{
    private const OPTIONAL_FIELDS = [
        'name' => [
            'property' => <<<'PHP'
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $name = null;
PHP,
            'getter' => <<<'PHP'
    public function getName(): ?string
    {
        return $this->name;
    }
PHP,
            'setter' => <<<'PHP'
    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }
PHP,
        ],
        'avatar' => [
            'property' => <<<'PHP'
    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    protected ?string $avatar = null;
PHP,
            'getter' => <<<'PHP'
    public function getAvatar(): ?string
    {
        return $this->avatar;
    }
PHP,
            'setter' => <<<'PHP'
    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }
PHP,
        ],
    ];

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform: add or remove')
            ->addArgument('fields', InputArgument::REQUIRED, 'Comma-separated list of fields (name, avatar)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force operation without confirmation')
            ->setHelp($this->getDetailedHelp());
    }

    private function getDetailedHelp(): string
    {
        return <<<'HELP'
The <info>better-auth:user-fields</info> command allows you to add or remove optional
fields from your User entity after installation.

<fg=yellow>Available Fields:</>
  • <info>name</info>   - User display name (VARCHAR 255)
  • <info>avatar</info> - User avatar URL (VARCHAR 500)

<fg=yellow>Usage Examples:</>

  <comment># Add the name field to User entity</comment>
  <info>php bin/console better-auth:user-fields add name</info>

  <comment># Add multiple fields</comment>
  <info>php bin/console better-auth:user-fields add name,avatar</info>

  <comment># Remove the avatar field from User entity</comment>
  <info>php bin/console better-auth:user-fields remove avatar</info>

  <comment># Remove all optional fields (minimal User)</comment>
  <info>php bin/console better-auth:user-fields remove name,avatar</info>

  <comment># Force without confirmation</comment>
  <info>php bin/console better-auth:user-fields remove name --force</info>

<fg=yellow>After Modifying Fields:</>

  1. Generate a new migration:
     <info>php bin/console doctrine:migrations:diff</info>

  2. Run the migration:
     <info>php bin/console doctrine:migrations:migrate</info>

<fg=yellow>Important Notes:</>

  • When removing fields, existing data in the database will be lost
  • Always backup your database before removing fields in production
  • If using the UserProfileTrait, this command will convert to explicit fields

HELP;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();
        $projectDir = $this->getProjectDir();

        $action = strtolower($input->getArgument('action'));
        $fieldsArg = $input->getArgument('fields');
        $force = $input->getOption('force');

        // Validate action
        if (!in_array($action, ['add', 'remove'], true)) {
            $io->error('Invalid action. Use "add" or "remove".');
            return Command::FAILURE;
        }

        // Parse and validate fields
        $fields = array_map('trim', explode(',', $fieldsArg));
        $validFields = array_intersect($fields, array_keys(self::OPTIONAL_FIELDS));
        $invalidFields = array_diff($fields, array_keys(self::OPTIONAL_FIELDS));

        if (!empty($invalidFields)) {
            $io->error(sprintf(
                'Invalid fields: %s. Valid options are: %s',
                implode(', ', $invalidFields),
                implode(', ', array_keys(self::OPTIONAL_FIELDS))
            ));
            return Command::FAILURE;
        }

        if (empty($validFields)) {
            $io->error('No valid fields specified.');
            return Command::FAILURE;
        }

        // Check User entity exists
        $userFile = $projectDir . '/src/Entity/User.php';
        if (!$filesystem->exists($userFile)) {
            $io->error('User entity not found at src/Entity/User.php');
            $io->info('Run "php bin/console better-auth:install" first.');
            return Command::FAILURE;
        }

        $io->title(sprintf('BetterAuth - %s User Fields', ucfirst($action)));

        $content = file_get_contents($userFile);

        if ($action === 'add') {
            return $this->addFields($io, $filesystem, $userFile, $content, $validFields, $force);
        }

        return $this->removeFields($io, $filesystem, $userFile, $content, $validFields, $force);
    }

    private function addFields(SymfonyStyle $io, Filesystem $filesystem, string $userFile, string $content, array $fields, bool $force): int
    {
        $io->section('Adding Fields');

        // Check if using UserProfileTrait
        $usesTrait = str_contains($content, 'use UserProfileTrait;');
        if ($usesTrait) {
            $io->note('Your User entity uses UserProfileTrait which already includes name and avatar fields.');
            $io->info('No changes needed - all optional fields are already available.');
            return Command::SUCCESS;
        }

        $fieldsToAdd = [];
        foreach ($fields as $field) {
            // Check if field already exists
            if (preg_match('/protected\s+\?string\s+\$' . $field . '\s*=/', $content)) {
                $io->writeln(sprintf('  <fg=yellow>⊘</> Field "%s" already exists', $field));
                continue;
            }
            $fieldsToAdd[] = $field;
        }

        if (empty($fieldsToAdd)) {
            $io->success('All specified fields already exist in User entity.');
            return Command::SUCCESS;
        }

        // Confirm
        if (!$force) {
            $io->writeln('');
            $io->writeln('<comment>Fields to add:</comment>');
            foreach ($fieldsToAdd as $field) {
                $io->writeln(sprintf('  • %s', $field));
            }
            $io->writeln('');

            if (!$io->confirm('Proceed with adding these fields?', true)) {
                $io->warning('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        // Check if we need to add the Types use statement
        $needsTypesImport = !str_contains($content, 'use Doctrine\DBAL\Types\Types;');

        // Find insertion point (before the closing brace of the class)
        // Look for "// Add your custom fields here" comment first
        if (preg_match('/\/\/\s*Add your custom fields here/', $content)) {
            $insertPoint = '// Add your custom fields here';
            $insertAfter = true;
        } else {
            // Find the last method's closing brace before class end
            $insertPoint = "\n}\n";
            $insertAfter = false;
        }

        // Build code to insert
        $codeToInsert = "\n";
        foreach ($fieldsToAdd as $field) {
            $fieldDef = self::OPTIONAL_FIELDS[$field];
            $codeToInsert .= "\n" . $fieldDef['property'] . "\n";
            $codeToInsert .= "\n" . $fieldDef['getter'] . "\n";
            $codeToInsert .= "\n" . $fieldDef['setter'] . "\n";
        }

        // Add Types import if needed
        if ($needsTypesImport) {
            $content = str_replace(
                'use Doctrine\ORM\Mapping as ORM;',
                "use Doctrine\DBAL\Types\Types;\nuse Doctrine\ORM\Mapping as ORM;",
                $content
            );
        }

        // Insert code
        if ($insertAfter) {
            $content = str_replace($insertPoint, $insertPoint . $codeToInsert, $content);
        } else {
            // Insert before the final closing brace
            $lastBracePos = strrpos($content, '}');
            $content = substr($content, 0, $lastBracePos) . $codeToInsert . substr($content, $lastBracePos);
        }

        $filesystem->dumpFile($userFile, $content);

        foreach ($fieldsToAdd as $field) {
            $io->writeln(sprintf('  <fg=green>✓</> Added field "%s"', $field));
        }

        $io->success('Fields added successfully!');
        $io->note([
            'Next steps:',
            '1. Generate migration: php bin/console doctrine:migrations:diff',
            '2. Run migration: php bin/console doctrine:migrations:migrate',
        ]);

        return Command::SUCCESS;
    }

    private function removeFields(SymfonyStyle $io, Filesystem $filesystem, string $userFile, string $content, array $fields, bool $force): int
    {
        $io->section('Removing Fields');

        // Check if using UserProfileTrait
        $usesTrait = str_contains($content, 'use UserProfileTrait;');

        $fieldsToRemove = [];
        foreach ($fields as $field) {
            if ($usesTrait) {
                // Trait provides both fields
                $fieldsToRemove[] = $field;
            } elseif (preg_match('/protected\s+\?string\s+\$' . $field . '\s*=/', $content)) {
                $fieldsToRemove[] = $field;
            } else {
                $io->writeln(sprintf('  <fg=yellow>⊘</> Field "%s" does not exist', $field));
            }
        }

        if (empty($fieldsToRemove)) {
            $io->success('No fields to remove.');
            return Command::SUCCESS;
        }

        // Confirm with warning
        if (!$force) {
            $io->writeln('');
            $io->warning('Removing fields will result in data loss after migration!');
            $io->writeln('<comment>Fields to remove:</comment>');
            foreach ($fieldsToRemove as $field) {
                $io->writeln(sprintf('  • %s', $field));
            }
            $io->writeln('');

            if (!$io->confirm('Are you sure you want to remove these fields?', false)) {
                $io->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        // If using trait and removing all fields, just remove the trait
        if ($usesTrait) {
            $allFieldsRemoved = count(array_intersect($fieldsToRemove, array_keys(self::OPTIONAL_FIELDS))) === count(self::OPTIONAL_FIELDS);

            if ($allFieldsRemoved) {
                // Remove trait use statement
                $content = preg_replace('/\s*use UserProfileTrait;\s*\n/', "\n", $content);
                // Remove trait import
                $content = preg_replace('/use BetterAuth\\\\Symfony\\\\Model\\\\UserProfileTrait;\s*\n/', '', $content);

                $filesystem->dumpFile($userFile, $content);

                $io->writeln('  <fg=green>✓</> Removed UserProfileTrait (all optional fields)');
                $io->success('Fields removed successfully!');
            } else {
                // Need to convert from trait to explicit fields (keep some)
                $fieldsToKeep = array_diff(array_keys(self::OPTIONAL_FIELDS), $fieldsToRemove);

                // Remove trait
                $content = preg_replace('/\s*use UserProfileTrait;\s*\n/', "\n", $content);
                $content = preg_replace('/use BetterAuth\\\\Symfony\\\\Model\\\\UserProfileTrait;\s*\n/', '', $content);

                // Add Types import if not present
                if (!str_contains($content, 'use Doctrine\DBAL\Types\Types;')) {
                    $content = str_replace(
                        'use Doctrine\ORM\Mapping as ORM;',
                        "use Doctrine\DBAL\Types\Types;\nuse Doctrine\ORM\Mapping as ORM;",
                        $content
                    );
                }

                // Add explicit fields for those we're keeping
                $codeToInsert = "\n";
                foreach ($fieldsToKeep as $field) {
                    $fieldDef = self::OPTIONAL_FIELDS[$field];
                    $codeToInsert .= "\n" . $fieldDef['property'] . "\n";
                    $codeToInsert .= "\n" . $fieldDef['getter'] . "\n";
                    $codeToInsert .= "\n" . $fieldDef['setter'] . "\n";
                }

                // Insert before final brace
                $lastBracePos = strrpos($content, '}');
                $content = substr($content, 0, $lastBracePos) . $codeToInsert . substr($content, $lastBracePos);

                $filesystem->dumpFile($userFile, $content);

                foreach ($fieldsToRemove as $field) {
                    $io->writeln(sprintf('  <fg=green>✓</> Removed field "%s"', $field));
                }
                foreach ($fieldsToKeep as $field) {
                    $io->writeln(sprintf('  <fg=blue>ℹ</> Kept field "%s" (converted from trait)', $field));
                }
                $io->success('Fields removed successfully!');
            }
        } else {
            // Remove explicit field definitions
            foreach ($fieldsToRemove as $field) {
                // Remove property
                $content = preg_replace(
                    '/\s*#\[ORM\\\\Column\([^\]]*\)\]\s*\n\s*protected\s+\?string\s+\$' . $field . '\s*=\s*null;\s*\n/',
                    "\n",
                    $content
                );

                // Remove getter
                $content = preg_replace(
                    '/\s*public function get' . ucfirst($field) . '\(\): \?string\s*\{[^}]+\}\s*\n/',
                    "\n",
                    $content
                );

                // Remove setter
                $content = preg_replace(
                    '/\s*public function set' . ucfirst($field) . '\(\?string \$' . $field . '\): static\s*\{[^}]+\}\s*\n/',
                    "\n",
                    $content
                );

                $io->writeln(sprintf('  <fg=green>✓</> Removed field "%s"', $field));
            }

            // Clean up multiple blank lines
            $content = preg_replace('/\n{3,}/', "\n\n", $content);

            $filesystem->dumpFile($userFile, $content);
            $io->success('Fields removed successfully!');
        }

        $io->note([
            'Next steps:',
            '1. Generate migration: php bin/console doctrine:migrations:diff',
            '2. Run migration: php bin/console doctrine:migrations:migrate',
            '',
            'WARNING: The migration will DROP the removed columns!',
            'Make sure to backup your database before running in production.',
        ]);

        return Command::SUCCESS;
    }

    private function getProjectDir(): string
    {
        $dir = getcwd();

        while ($dir !== dirname($dir)) {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }
            $dir = dirname($dir);
        }

        return getcwd();
    }
}
