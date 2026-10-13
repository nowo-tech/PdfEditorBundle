<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Document;

use InvalidArgumentException;
use Nowo\PdfEditorBundle\Config\EditorProfile;
use Nowo\PdfEditorBundle\Document\WorkspaceManager;
use Nowo\PdfEditorBundle\Tests\ProfileFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkspaceManager::class)]
#[CoversClass(EditorProfile::class)]
final class WorkspacePurgeTest extends TestCase
{
    private const NOW = 1_800_000_000;

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/pdfed-purge-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0700, true);
    }

    public function testPurgesOnlyWorkspacesOlderThanTheMaxAge(): void
    {
        $old    = $this->workspace(str_repeat('a', 32), self::NOW - 7200);
        $recent = $this->workspace(str_repeat('b', 32), self::NOW - 600);
        // An old workspace whose preview was regenerated recently is still in use.
        $touched = $this->workspace(str_repeat('c', 32), self::NOW - 7200);
        mkdir($touched . '/previews');
        touch($touched . '/previews/page-1.png', self::NOW - 60);
        touch($touched . '/previews', self::NOW - 7200);

        $purged = $this->manager()->purgeOlderThan(3600, false, self::NOW);

        self::assertSame([str_repeat('a', 32)], $purged);
        self::assertDirectoryDoesNotExist($old);
        self::assertDirectoryExists($recent);
        self::assertDirectoryExists($touched);
    }

    public function testDryRunReportsWithoutDeleting(): void
    {
        $old = $this->workspace(str_repeat('a', 32), self::NOW - 7200);

        self::assertSame([str_repeat('a', 32)], $this->manager()->purgeOlderThan(3600, true, self::NOW));
        self::assertFileExists($old . '/current.pdf');
    }

    public function testIgnoresEntriesThatAreNotWorkspaces(): void
    {
        $outside = sys_get_temp_dir() . '/pdfed-purge-outside-' . bin2hex(random_bytes(4));
        mkdir($outside);
        touch($outside . '/keep.txt', self::NOW - 7200);
        touch($outside, self::NOW - 7200);
        symlink($outside, $this->root . '/' . str_repeat('d', 32));
        mkdir($this->root . '/not-a-workspace');
        touch($this->root . '/not-a-workspace', self::NOW - 7200);
        touch($this->root . '/' . str_repeat('e', 32), self::NOW - 7200);

        self::assertSame([], $this->manager()->purgeOlderThan(3600, false, self::NOW));
        self::assertFileExists($outside . '/keep.txt');
        self::assertDirectoryExists($this->root . '/not-a-workspace');
    }

    public function testRemovesSymlinksInsideAWorkspaceWithoutFollowingThem(): void
    {
        $target = tempnam(sys_get_temp_dir(), 'pdfed-target');
        self::assertIsString($target);
        $old = $this->workspace(str_repeat('a', 32), self::NOW - 7200);
        symlink($target, $old . '/link.pdf');
        mkdir($old . '/nested');
        touch($old . '/nested/page-2.png', self::NOW - 7200);
        touch($old . '/nested', self::NOW - 7200);
        touch($old, self::NOW - 7200);

        self::assertSame([str_repeat('a', 32)], $this->manager()->purgeOlderThan(3600, false, self::NOW));
        self::assertDirectoryDoesNotExist($old);
        self::assertFileExists($target);
    }

    public function testMissingRootPurgesNothing(): void
    {
        $manager = new WorkspaceManager(ProfileFactory::create($this->root . '/missing'));

        self::assertSame([], $manager->purgeOlderThan(60));
    }

    public function testRejectsNonPositiveAge(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->manager()->purgeOlderThan(0);
    }

    private function manager(): WorkspaceManager
    {
        return new WorkspaceManager(ProfileFactory::create($this->root));
    }

    private function workspace(string $id, int $mtime): string
    {
        $dir = $this->root . '/' . $id;
        mkdir($dir);
        foreach (['current.pdf', 'meta.txt', 'owner.txt'] as $file) {
            file_put_contents($dir . '/' . $file, 'x');
            touch($dir . '/' . $file, $mtime);
        }
        touch($dir, $mtime);

        return $dir;
    }
}
