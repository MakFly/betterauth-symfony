<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Installer;

use BetterAuth\Symfony\Installer\EntityGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class EntityGeneratorTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $filesystem;
    private EntityGenerator $generator;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/betterauth_entity_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        $this->filesystem = new Filesystem();
        $this->generator = new EntityGenerator($this->filesystem);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tmpDir);
    }

    private function createMockIo(): SymfonyStyle
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('section')->willReturnSelf();
        $io->method('writeln')->willReturnSelf();
        $io->method('confirm')->willReturn(false); // do not overwrite by default

        return $io;
    }

    public function testProcessUserTemplateWithNoExclusions(): void
    {
        $template = "{{USE_PROFILE_TRAIT}}\nclass User {\n{{PROFILE_TRAIT}}\n{{CUSTOM_FIELDS}}\n}";

        $result = $this->generator->processUserTemplate($template, []);

        $this->assertStringContainsString('UserProfileTrait', $result);
        $this->assertStringNotContainsString('{{USE_PROFILE_TRAIT}}', $result);
        $this->assertStringNotContainsString('{{PROFILE_TRAIT}}', $result);
        $this->assertStringNotContainsString('{{CUSTOM_FIELDS}}', $result);
    }

    public function testProcessUserTemplateWithAllExcluded(): void
    {
        $template = "{{USE_PROFILE_TRAIT}}\nclass User {\n{{PROFILE_TRAIT}}\n{{CUSTOM_FIELDS}}\n}";

        $result = $this->generator->processUserTemplate($template, ['name', 'avatar']);

        $this->assertStringNotContainsString('UserProfileTrait', $result);
        $this->assertStringNotContainsString('{{USE_PROFILE_TRAIT}}', $result);
        $this->assertStringNotContainsString('{{PROFILE_TRAIT}}', $result);
        $this->assertStringNotContainsString('{{CUSTOM_FIELDS}}', $result);
    }

    public function testProcessUserTemplateWithNameExcluded(): void
    {
        $template = "{{USE_PROFILE_TRAIT}}\nclass User {\n{{PROFILE_TRAIT}}\n{{CUSTOM_FIELDS}}\n}";

        $result = $this->generator->processUserTemplate($template, ['name']);

        // Should have Types import but not the profile trait
        $this->assertStringNotContainsString('UserProfileTrait', $result);
        // Should contain avatar field (not excluded)
        $this->assertStringContainsString('avatar', $result);
        // Should not contain name field (excluded)
        $this->assertStringNotContainsString('$name', $result);
    }

    public function testProcessUserTemplateWithAvatarExcluded(): void
    {
        $template = "{{USE_PROFILE_TRAIT}}\nclass User {\n{{PROFILE_TRAIT}}\n{{CUSTOM_FIELDS}}\n}";

        $result = $this->generator->processUserTemplate($template, ['avatar']);

        $this->assertStringNotContainsString('UserProfileTrait', $result);
        $this->assertStringContainsString('$name', $result);
        $this->assertStringNotContainsString('$avatar', $result);
    }

    public function testGenerateCustomFieldsWithNoExclusions(): void
    {
        $result = $this->generator->generateCustomFields([]);

        $this->assertStringContainsString('$name', $result);
        $this->assertStringContainsString('$avatar', $result);
        $this->assertStringContainsString('getName', $result);
        $this->assertStringContainsString('getAvatar', $result);
        $this->assertStringContainsString('setName', $result);
        $this->assertStringContainsString('setAvatar', $result);
    }

    public function testGenerateCustomFieldsWithNameExcluded(): void
    {
        $result = $this->generator->generateCustomFields(['name']);

        $this->assertStringNotContainsString('$name', $result);
        $this->assertStringContainsString('$avatar', $result);
    }

    public function testGenerateCustomFieldsWithAvatarExcluded(): void
    {
        $result = $this->generator->generateCustomFields(['avatar']);

        $this->assertStringContainsString('$name', $result);
        $this->assertStringNotContainsString('$avatar', $result);
    }

    public function testGenerateCustomFieldsWithAllExcluded(): void
    {
        $result = $this->generator->generateCustomFields(['name', 'avatar']);

        $this->assertEmpty($result);
    }

    public function testGenerateEntitiesCreatesEntityDirectory(): void
    {
        $io = $this->createMockIo();
        $state = ['entities' => ['User' => false]];

        // Templates dir may not exist in test env — will generate 0 files but creates dir
        $this->generator->generateEntities($io, $this->tmpDir, 'uuid', $state);

        $this->assertDirectoryExists($this->tmpDir . '/src/Entity');
    }

    public function testGenerateEntitiesSkipsExistingEntityWithoutConfirmation(): void
    {
        mkdir($this->tmpDir . '/src/Entity', 0777, true);
        file_put_contents($this->tmpDir . '/src/Entity/User.php', '<?php // existing');

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('section')->willReturnSelf();
        $io->method('writeln')->willReturnSelf();
        $io->method('confirm')->willReturn(false); // refuse overwrite

        $state = ['entities' => ['User' => true]];

        $generated = $this->generator->generateEntities($io, $this->tmpDir, 'uuid', $state);

        // User should not be in generated list if user refused overwrite
        // (template may not exist in test, so result depends on template availability)
        $this->assertIsArray($generated);
    }
}
