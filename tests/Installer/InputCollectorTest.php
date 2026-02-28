<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Installer;

use BetterAuth\Symfony\Installer\InputCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InputCollectorTest extends TestCase
{
    private InputCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new InputCollector();
    }

    private function createInput(array $options = [], bool $isInteractive = true): InputInterface
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->willReturnCallback(static fn ($key) => $options[$key] ?? null);
        $input->method('isInteractive')->willReturn($isInteractive);

        return $input;
    }

    private function createIo(mixed $choiceResult = 'uuid', mixed $confirmResult = true): SymfonyStyle
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('choice')->willReturn($choiceResult);
        $io->method('confirm')->willReturn($confirmResult);
        $io->method('ask')->willReturn('My App');
        $io->method('writeln')->willReturnSelf();
        $io->method('newLine')->willReturnSelf();

        return $io;
    }

    // chooseIdStrategy

    public function testChooseIdStrategyFromOption(): void
    {
        $input = $this->createInput(['id-strategy' => 'uuid']);
        $io = $this->createIo();
        $state = ['entities' => ['User' => false]];

        $result = $this->collector->chooseIdStrategy($input, $io, $state);

        $this->assertSame('uuid', $result);
    }

    public function testChooseIdStrategyIntFromOption(): void
    {
        $input = $this->createInput(['id-strategy' => 'int']);
        $io = $this->createIo();
        $state = ['entities' => ['User' => false]];

        $result = $this->collector->chooseIdStrategy($input, $io, $state);

        $this->assertSame('int', $result);
    }

    public function testChooseIdStrategyInvalidOptionFallsToChoice(): void
    {
        $input = $this->createInput(['id-strategy' => 'invalid']);
        $io = $this->createIo('uuid');
        $state = ['entities' => ['User' => false]];

        $result = $this->collector->chooseIdStrategy($input, $io, $state);

        $this->assertSame('uuid', $result);
    }

    public function testChooseIdStrategyFromInteractivePrompt(): void
    {
        $input = $this->createInput([]);
        $io = $this->createIo('int');
        $state = ['entities' => ['User' => false]];

        $result = $this->collector->chooseIdStrategy($input, $io, $state);

        $this->assertSame('int', $result);
    }

    // chooseMode

    public function testChooseModeFromOption(): void
    {
        $input = $this->createInput(['mode' => 'api']);
        $io = $this->createIo();
        $state = ['config' => false];

        $result = $this->collector->chooseMode($input, $io, $state);

        $this->assertSame('api', $result);
    }

    public function testChooseModeSessionFromOption(): void
    {
        $input = $this->createInput(['mode' => 'session']);
        $io = $this->createIo();
        $state = ['config' => false];

        $result = $this->collector->chooseMode($input, $io, $state);

        $this->assertSame('session', $result);
    }

    public function testChooseModeHybridFromOption(): void
    {
        $input = $this->createInput(['mode' => 'hybrid']);
        $io = $this->createIo();
        $state = ['config' => false];

        $result = $this->collector->chooseMode($input, $io, $state);

        $this->assertSame('hybrid', $result);
    }

    public function testChooseModeInvalidOptionFallsToChoice(): void
    {
        $input = $this->createInput(['mode' => 'invalid']);
        $io = $this->createIo('session');
        $state = ['config' => false];

        $result = $this->collector->chooseMode($input, $io, $state);

        $this->assertSame('session', $result);
    }

    public function testChooseModeFromInteractivePrompt(): void
    {
        $input = $this->createInput([]);
        $io = $this->createIo('hybrid');
        $state = ['config' => false];

        $result = $this->collector->chooseMode($input, $io, $state);

        $this->assertSame('hybrid', $result);
    }

    // chooseAppName

    public function testChooseAppNameFromOption(): void
    {
        $input = $this->createInput(['app-name' => 'My SaaS']);
        $io = $this->createIo();

        $result = $this->collector->chooseAppName($input, $io);

        $this->assertSame('My SaaS', $result);
    }

    public function testChooseAppNameFromInteractiveAsk(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('ask')->willReturn('Test App');
        $io->method('writeln')->willReturnSelf();
        $io->method('newLine')->willReturnSelf();

        $input = $this->createInput([]);

        $result = $this->collector->chooseAppName($input, $io);

        $this->assertSame('Test App', $result);
    }

    // chooseExcludedFields

    public function testChooseExcludedFieldsWithMinimalFlag(): void
    {
        $input = $this->createInput(['minimal' => true, 'exclude-fields' => null]);
        $io = $this->createIo();

        $result = $this->collector->chooseExcludedFields($input, $io);

        $this->assertContains('name', $result);
        $this->assertContains('avatar', $result);
    }

    public function testChooseExcludedFieldsWithExcludeOption(): void
    {
        $input = $this->createInput(['minimal' => false, 'exclude-fields' => 'avatar']);
        $io = $this->createIo();

        $result = $this->collector->chooseExcludedFields($input, $io);

        $this->assertContains('avatar', $result);
        $this->assertNotContains('name', $result);
    }

    public function testChooseExcludedFieldsWithInvalidFieldWarns(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('choice')->willReturn('none');
        $io->method('confirm')->willReturn(false);
        $io->method('writeln')->willReturnSelf();
        $io->expects($this->once())->method('warning');

        $input = $this->createInput(['minimal' => false, 'exclude-fields' => 'invalid_field']);

        $result = $this->collector->chooseExcludedFields($input, $io);

        $this->assertEmpty($result);
    }

    public function testChooseExcludedFieldsNonInteractiveReturnsEmpty(): void
    {
        $input = $this->createInput(['minimal' => false, 'exclude-fields' => null], false);
        $io = $this->createIo();

        $result = $this->collector->chooseExcludedFields($input, $io);

        $this->assertEmpty($result);
    }

    public function testChooseExcludedFieldsInteractiveNoCustomizationReturnsEmpty(): void
    {
        $input = $this->createInput(['minimal' => false, 'exclude-fields' => null], true);
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('confirm')->willReturn(false); // do not customize
        $io->method('writeln')->willReturnSelf();
        $io->method('newLine')->willReturnSelf();

        $result = $this->collector->chooseExcludedFields($input, $io);

        $this->assertEmpty($result);
    }

    public function testChooseExcludedFieldsInteractiveChooseAll(): void
    {
        $input = $this->createInput(['minimal' => false, 'exclude-fields' => null], true);
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('confirm')->willReturn(true); // customize
        $io->method('choice')->willReturn('all');
        $io->method('writeln')->willReturnSelf();
        $io->method('newLine')->willReturnSelf();

        $result = $this->collector->chooseExcludedFields($input, $io);

        $this->assertContains('name', $result);
        $this->assertContains('avatar', $result);
    }

    public function testChooseExcludedFieldsInteractiveChooseNone(): void
    {
        $input = $this->createInput(['minimal' => false, 'exclude-fields' => null], true);
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('confirm')->willReturn(true); // customize
        $io->method('choice')->willReturn('none');
        $io->method('writeln')->willReturnSelf();
        $io->method('newLine')->willReturnSelf();

        $result = $this->collector->chooseExcludedFields($input, $io);

        $this->assertEmpty($result);
    }

    // chooseOAuthProviders

    public function testChooseOAuthProvidersWhenUserDeclines(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('writeln')->willReturnSelf();
        $io->method('newLine')->willReturnSelf();
        $io->method('confirm')->willReturnOnConsecutiveCalls(false); // decline OAuth

        $result = $this->collector->chooseOAuthProviders($io);

        $this->assertEmpty($result);
    }
}
