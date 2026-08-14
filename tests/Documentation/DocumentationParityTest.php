<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Documentation;

use PHPUnit\Framework\TestCase;

final class DocumentationParityTest extends TestCase
{
    public function testEnglishAndFrenchTreesHaveMatchingPathsAndHeadings(): void
    {
        $root = dirname(__DIR__, 2).'/docs';
        $english = $this->markdownPaths($root.'/en');
        $french = $this->markdownPaths($root.'/fr');
        self::assertSame(array_keys($english), array_keys($french));

        foreach ($english as $path => $englishFile) {
            self::assertCount(count($this->headings($englishFile)), $this->headings($french[$path]), $path);
        }
    }

    public function testLocalLinksAndAnchorsResolve(): void
    {
        $this->assertLinksResolve(dirname(__DIR__, 2).'/docs/CONFIGURATION.md');
        foreach (['en', 'fr'] as $language) {
            foreach ($this->markdownPaths(dirname(__DIR__, 2).'/docs/'.$language) as $file) {
                $this->assertLinksResolve($file);
            }
        }
    }

    public function testDemoDocumentationPublishesTheRealTenantRoute(): void
    {
        foreach (['en', 'fr'] as $language) {
            $contents = (string) file_get_contents(dirname(__DIR__, 2).'/docs/'.$language.'/demo.md');
            self::assertStringContainsString('/api/tenant/{tenant}', $contents);
            self::assertStringNotContainsString('/api/tenants/', $contents);
        }
    }

    public function testDocumentedPhpNamespacesAreCopyable(): void
    {
        foreach (['en', 'fr'] as $language) {
            foreach ($this->markdownPaths(dirname(__DIR__, 2).'/docs/'.$language) as $file) {
                self::assertStringNotContainsString('\\\\', (string) file_get_contents($file), $file);
            }
        }
    }

    /** @return array<string, string> */
    private function markdownPaths(string $directory): array
    {
        $paths = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'md') {
                continue;
            }
            $path = $file->getPathname();
            $paths[substr($path, strlen($directory) + 1)] = $path;
        }
        ksort($paths);

        return $paths;
    }

    /** @return list<string> */
    private function headings(string $file): array
    {
        preg_match_all('/^#{1,6} (.+)$/m', (string) file_get_contents($file), $matches);

        return $matches[1];
    }

    private function assertLinksResolve(string $file): void
    {
        $contents = (string) file_get_contents($file);
        preg_match_all('/!?\[[^\]]*\]\(([^)]+)\)/', $contents, $matches);
        foreach ($matches[1] as $link) {
            if (preg_match('~^(https?://|mailto:|#)~', $link) === 1) {
                continue;
            }
            [$target, $anchor] = array_pad(explode('#', $link, 2), 2, '');
            $path = $target === '' ? $file : dirname($file).'/'.$target;
            self::assertFileExists($path, sprintf('%s links to %s', $file, $link));
            if ($anchor !== '') {
                self::assertContains($anchor, $this->anchors($path), sprintf('%s links to %s', $file, $link));
            }
        }
    }

    /** @return list<string> */
    private function anchors(string $file): array
    {
        return array_map(static fn (string $heading): string => strtolower(str_replace(' ', '-', trim($heading))), $this->headings($file));
    }
}
