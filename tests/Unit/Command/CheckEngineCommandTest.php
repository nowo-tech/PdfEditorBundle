<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Command;

use Nowo\PdfEditorBundle\Command\CheckEngineCommand;
use Nowo\PdfEditorBundle\Engine\PdfEngineInterface;
use Nowo\PdfEditorBundle\Exception\EngineException;
use Nowo\PdfEditorBundle\Tests\ProfileFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CheckEngineCommand::class)]
final class CheckEngineCommandTest extends TestCase
{
    public function testSuccess(): void
    {
        $engine = $this->createMock(PdfEngineInterface::class);
        $engine->method('version')->willReturn(['engine' => 'pymupdf', 'pymupdf' => '1.24.0', 'python' => '3.12']);
        $script = tempnam(sys_get_temp_dir(), 'eng');
        file_put_contents($script, "#\n");
        $command = new CheckEngineCommand($engine, ProfileFactory::create(sys_get_temp_dir(), $script));
        $tester  = new CommandTester($command);
        $code    = $tester->execute([]);
        self::assertSame(0, $code);
        self::assertStringContainsString('PyMuPDF 1.24.0', $tester->getDisplay());
        @unlink($script);
    }

    public function testMissingScript(): void
    {
        $engine = $this->createMock(PdfEngineInterface::class);
        $command = new CheckEngineCommand($engine, ProfileFactory::create(sys_get_temp_dir(), '/no/engine.py'));
        $this->expectException(EngineException::class);
        (new CommandTester($command))->execute([]);
    }
}
