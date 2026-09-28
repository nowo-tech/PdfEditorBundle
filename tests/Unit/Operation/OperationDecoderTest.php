<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Operation;

use Nowo\PdfEditorBundle\Exception\PdfEditorException;
use Nowo\PdfEditorBundle\Operation\EditorOperation;
use Nowo\PdfEditorBundle\Operation\OperationDecoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationDecoder::class)]
#[CoversClass(EditorOperation::class)]
final class OperationDecoderTest extends TestCase
{
    public function testDecodeValidOps(): void
    {
        $ops = (new OperationDecoder())->decode([
            ['op' => 'replace_text', 'page' => 1, 'text' => 'Hi'],
        ]);
        self::assertCount(1, $ops);
        self::assertSame('replace_text', $ops[0]->op);
        self::assertSame(1, $ops[0]->payload['page']);
        self::assertSame('replace_text', $ops[0]->toArray()['op']);
    }

    public function testRejectsInvalidShape(): void
    {
        $this->expectException(PdfEditorException::class);
        (new OperationDecoder())->decode(['nope']);
    }

    public function testRejectsUnknownOp(): void
    {
        $this->expectException(PdfEditorException::class);
        (new OperationDecoder())->decode([['op' => 'explode']]);
    }

    public function testRejectsAbsoluteAddImagePath(): void
    {
        $this->expectException(PdfEditorException::class);
        $this->expectExceptionMessage('relative path');
        (new OperationDecoder())->decode([
            ['op' => 'add_image', 'path' => '/etc/passwd', 'page' => 1, 'bbox' => [0, 0, 1, 1]],
        ]);
    }

    public function testRejectsTraversalAddImagePath(): void
    {
        $this->expectException(PdfEditorException::class);
        $this->expectExceptionMessage('..');
        (new OperationDecoder())->decode([
            ['op' => 'add_image', 'path' => '../secret.png', 'page' => 1, 'bbox' => [0, 0, 1, 1]],
        ]);
    }

    public function testAcceptsRelativeAddImagePath(): void
    {
        $ops = (new OperationDecoder())->decode([
            ['op' => 'add_image', 'path' => 'assets/logo.png', 'page' => 1, 'bbox' => [0, 0, 1, 1]],
        ]);
        self::assertSame('assets/logo.png', $ops[0]->payload['path']);
    }

    public function testBindAddImagePathsRelativeInsideWorkspace(): void
    {
        $workspace = sys_get_temp_dir() . '/pdf-editor-bind-' . uniqid('', true);
        mkdir($workspace . '/assets', 0777, true);
        $logo = $workspace . '/assets/logo.png';
        file_put_contents($logo, 'png');

        $decoder = new OperationDecoder();
        $ops     = $decoder->decode([
            ['op' => 'add_image', 'path' => 'assets/logo.png', 'page' => 1, 'bbox' => [0, 0, 1, 1]],
        ]);
        $bound = $decoder->bindAddImagePathsToWorkspace($ops, $workspace);

        self::assertSame(realpath($logo), $bound[0]->payload['path']);
    }

    public function testBindAddImagePathsOutsideWorkspaceFails(): void
    {
        $workspace = sys_get_temp_dir() . '/pdf-editor-bind-' . uniqid('', true);
        mkdir($workspace . '/assets', 0777, true);
        $outside = sys_get_temp_dir() . '/pdf-editor-outside-' . uniqid('', true) . '.png';
        file_put_contents($outside, 'png');
        symlink($outside, $workspace . '/assets/evil.png');

        $decoder = new OperationDecoder();
        $ops     = $decoder->decode([
            ['op' => 'add_image', 'path' => 'assets/evil.png', 'page' => 1, 'bbox' => [0, 0, 1, 1]],
        ]);

        $this->expectException(PdfEditorException::class);
        $this->expectExceptionMessage('escapes the workspace');
        $decoder->bindAddImagePathsToWorkspace($ops, $workspace);
    }
}
