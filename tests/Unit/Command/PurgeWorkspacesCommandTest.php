<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Command;

use Nowo\PdfEditorBundle\Command\PurgeWorkspacesCommand;
use Nowo\PdfEditorBundle\Document\WorkspaceManager;
use Nowo\PdfEditorBundle\Tests\ProfileFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

use function sprintf;

#[CoversClass(PurgeWorkspacesCommand::class)]
final class PurgeWorkspacesCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/pdfed-purge-cmd-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0700, true);
    }

    public function testDeletesWorkspacesOlderThanTheDefaultDay(): void
    {
        $old    = $this->workspace(str_repeat('a', 32), time() - 2 * 86400);
        $recent = $this->workspace(str_repeat('b', 32), time() - 3600);

        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('1 workspace(s) older than 86400 seconds deleted.', $tester->getDisplay());
        self::assertDirectoryDoesNotExist($old);
        self::assertDirectoryExists($recent);
    }

    public function testDryRunListsWithoutDeleting(): void
    {
        $old = $this->workspace(str_repeat('a', 32), time() - 7200);

        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute(['--older-than' => '90m', '--dry-run' => true]));
        $display = $tester->getDisplay();
        self::assertStringContainsString(str_repeat('a', 32), $display);
        self::assertStringContainsString('older than 5400 seconds would be deleted (dry run)', $display);
        self::assertDirectoryExists($old);
    }

    public function testVerboseListsDeletedIds(): void
    {
        $this->workspace(str_repeat('a', 32), time() - 7200);

        $tester = $this->tester();
        $tester->execute(['--older-than' => '1h'], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        self::assertStringContainsString(str_repeat('a', 32), $tester->getDisplay());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function durations(): iterable
    {
        yield 'seconds' => ['3600', 3600];
        yield 'seconds suffix' => ['45s', 45];
        yield 'minutes' => ['10m', 600];
        yield 'hours' => ['2h', 7200];
        yield 'days' => ['7d', 604800];
        yield 'weeks' => [' 1W ', 604800];
    }

    #[DataProvider('durations')]
    public function testParsesDurations(string $value, int $seconds): void
    {
        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute(['--older-than' => $value]));
        self::assertStringContainsString(sprintf('older than %d seconds', $seconds), $tester->getDisplay());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDurations(): iterable
    {
        yield 'zero' => ['0'];
        yield 'zero hours' => ['0h'];
        yield 'negative' => ['-5'];
        yield 'unknown unit' => ['3y'];
        yield 'text' => ['yesterday'];
    }

    #[DataProvider('invalidDurations')]
    public function testRejectsInvalidDurations(string $value): void
    {
        $old    = $this->workspace(str_repeat('a', 32), time() - 7200);
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['--older-than' => $value]));
        self::assertStringContainsString('Invalid --older-than value', $tester->getDisplay());
        self::assertDirectoryExists($old);
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new PurgeWorkspacesCommand(new WorkspaceManager(ProfileFactory::create($this->root))));
    }

    private function workspace(string $id, int $mtime): string
    {
        $dir = $this->root . '/' . $id;
        mkdir($dir);
        file_put_contents($dir . '/current.pdf', '%PDF-1.4');
        touch($dir . '/current.pdf', $mtime);
        touch($dir, $mtime);

        return $dir;
    }
}
