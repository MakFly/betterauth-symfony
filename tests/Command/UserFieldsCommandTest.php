<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Command;

use BetterAuth\Symfony\Command\UserFieldsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UserFieldsCommandTest extends TestCase
{
    private string $tmpDir;
    private UserFieldsCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ba_userfields_' . uniqid();
        mkdir($this->tmpDir . '/src/Entity', 0777, true);
        file_put_contents($this->tmpDir . '/composer.json', '{}');
        chdir($this->tmpDir);

        $this->command = new UserFieldsCommand();
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createUserEntityWithTrait(): void
    {
        file_put_contents($this->tmpDir . '/src/Entity/User.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Entity;
use BetterAuth\Symfony\Model\UserProfileTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User
{
    use UserProfileTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    private string $id;
}
PHP);
    }

    private function createUserEntityWithExplicitFields(): void
    {
        file_put_contents($this->tmpDir . '/src/Entity/User.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    private string $id;

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
}
PHP);
    }

    public function testCommandNameAndDescription(): void
    {
        $this->assertSame('better-auth:user-fields', $this->command->getName());
        $this->assertStringContainsString('User', $this->command->getDescription());
    }

    public function testCommandFailsWithInvalidAction(): void
    {
        $this->createUserEntityWithExplicitFields();

        $exitCode = $this->commandTester->execute([
            'action' => 'invalid',
            'fields' => 'name',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Invalid action', $this->commandTester->getDisplay());
    }

    public function testCommandFailsWithInvalidFields(): void
    {
        $this->createUserEntityWithExplicitFields();

        $exitCode = $this->commandTester->execute([
            'action' => 'add',
            'fields' => 'invalid_field',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Invalid fields', $this->commandTester->getDisplay());
    }

    public function testCommandFailsWhenUserEntityMissing(): void
    {
        // No User entity created

        $exitCode = $this->commandTester->execute([
            'action' => 'add',
            'fields' => 'name',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('User entity not found', $this->commandTester->getDisplay());
    }

    public function testAddFieldAlreadyPresentWithTrait(): void
    {
        $this->createUserEntityWithTrait();

        $exitCode = $this->commandTester->execute([
            'action' => 'add',
            'fields' => 'name',
            '--force' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('UserProfileTrait', $this->commandTester->getDisplay());
    }

    public function testAddFieldAlreadyExplicitlyPresent(): void
    {
        $this->createUserEntityWithExplicitFields();

        $exitCode = $this->commandTester->execute([
            'action' => 'add',
            'fields' => 'name',
            '--force' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        // Should report field already exists
        $display = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($display, 'already exists') || str_contains($display, 'already exist')
        );
    }

    public function testRemoveExplicitFieldWithForce(): void
    {
        $this->createUserEntityWithExplicitFields();

        $exitCode = $this->commandTester->execute([
            'action' => 'remove',
            'fields' => 'avatar',
            '--force' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $content = file_get_contents($this->tmpDir . '/src/Entity/User.php');
        $this->assertStringNotContainsString('$avatar', $content);
        $this->assertStringContainsString('$name', $content);
    }

    public function testRemoveAllExplicitFieldsWithForce(): void
    {
        $this->createUserEntityWithExplicitFields();

        $exitCode = $this->commandTester->execute([
            'action' => 'remove',
            'fields' => 'name,avatar',
            '--force' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $content = file_get_contents($this->tmpDir . '/src/Entity/User.php');
        $this->assertStringNotContainsString('$avatar', $content);
        $this->assertStringNotContainsString('$name', $content);
    }

    public function testRemoveTraitWhenAllFieldsRemovedWithForce(): void
    {
        $this->createUserEntityWithTrait();

        $exitCode = $this->commandTester->execute([
            'action' => 'remove',
            'fields' => 'name,avatar',
            '--force' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $content = file_get_contents($this->tmpDir . '/src/Entity/User.php');
        $this->assertStringNotContainsString('UserProfileTrait', $content);
    }

    public function testRemoveNonExistentFieldReportsSkip(): void
    {
        $this->createUserEntityWithExplicitFields();
        // Remove avatar first, then try to remove it again
        $content = file_get_contents($this->tmpDir . '/src/Entity/User.php');
        $content = str_replace('?string $avatar', '// removed', $content);
        file_put_contents($this->tmpDir . '/src/Entity/User.php', $content);

        $exitCode = $this->commandTester->execute([
            'action' => 'remove',
            'fields' => 'avatar',
            '--force' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testRemoveCancelsWhenUserDeclines(): void
    {
        $this->createUserEntityWithExplicitFields();

        $this->commandTester->setInputs(['n']); // user declines confirmation

        $exitCode = $this->commandTester->execute([
            'action' => 'remove',
            'fields' => 'avatar',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Avatar should still be present (not removed)
        $content = file_get_contents($this->tmpDir . '/src/Entity/User.php');
        $this->assertStringContainsString('$avatar', $content);
    }

    public function testAddCancelsWhenUserDeclines(): void
    {
        // Entity without name field
        file_put_contents($this->tmpDir . '/src/Entity/User.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
class User
{
    #[ORM\Id]
    #[ORM\Column]
    private string $id;
    // Add your custom fields here
}
PHP);

        $this->commandTester->setInputs(['n']); // user declines

        $exitCode = $this->commandTester->execute([
            'action' => 'add',
            'fields' => 'name',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $content = file_get_contents($this->tmpDir . '/src/Entity/User.php');
        $this->assertStringNotContainsString('protected ?string $name', $content);
    }
}
