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

    public function testRejectsTooManyOperations(): void
    {
        $decoder = new OperationDecoder();
        self::assertCount(OperationDecoder::MAX_OPERATIONS, $decoder->decode(array_fill(0, OperationDecoder::MAX_OPERATIONS, ['op' => 'rotate_page', 'page' => 1])));

        $this->expectException(PdfEditorException::class);
        $this->expectExceptionMessage('Too many operations (501); the maximum is 500 per apply.');
        $decoder->decode(array_fill(0, OperationDecoder::MAX_OPERATIONS + 1, ['op' => 'rotate_page', 'page' => 1]));
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

    public function testRejectsAddImageWithoutPath(): void
    {
        $this->expectException(PdfEditorException::class);
        $this->expectExceptionMessage('non-empty "path"');
        (new OperationDecoder())->decode([['op' => 'add_image', 'page' => 1]]);
    }

    public function testBindFailsWhenWorkspaceDirectoryIsMissing(): void
    {
        $this->expectException(PdfEditorException::class);
        $this->expectExceptionMessage('Workspace directory does not exist.');
        (new OperationDecoder())->bindAddImagePathsToWorkspace([], sys_get_temp_dir() . '/pdf-editor-missing-' . uniqid('', true));
    }

    public function testBindKeepsNonImageOperationsUntouched(): void
    {
        $workspace = $this->workspace();
        $rotate    = new EditorOperation('rotate_page', ['page' => 1]);

        self::assertSame([$rotate], (new OperationDecoder())->bindAddImagePathsToWorkspace([$rotate], $workspace));
    }

    public function testBindRejectsUnvalidatedOperationWithoutPath(): void
    {
        $this->expectException(PdfEditorException::class);
        $this->expectExceptionMessage('non-empty "path"');
        (new OperationDecoder())->bindAddImagePathsToWorkspace([new EditorOperation('add_image', ['path' => ''])], $this->workspace());
    }

    public function testBindRejectsUnvalidatedTraversalPath(): void
    {
        $this->expectException(PdfEditorException::class);
        $this->expectExceptionMessage('..');
        (new OperationDecoder())->bindAddImagePathsToWorkspace([new EditorOperation('add_image', ['path' => 'a/../../etc/passwd'])], $this->workspace());
    }

    public function testBindResolvesNotYetExistingFileInsideWorkspace(): void
    {
        $workspace = $this->workspace();
        mkdir($workspace . '/assets');
        $decoder = new OperationDecoder();
        $ops     = $decoder->decode([['op' => 'add_image', 'path' => 'assets/new.png', 'page' => 1, 'bbox' => [0, 0, 1, 1]]]);

        $bound = $decoder->bindAddImagePathsToWorkspace($ops, $workspace);

        self::assertSame(realpath($workspace) . '/assets/new.png', $bound[0]->payload['path']);
        self::assertSame(1, $bound[0]->payload['page']);
    }

    public function testBindRejectsNotYetExistingFileInSymlinkedDirectoryOutsideWorkspace(): void
    {
        $workspace = $this->workspace();
        $outside   = $this->workspace();
        symlink($outside, $workspace . '/linked');
        $decoder = new OperationDecoder();
        $ops     = $decoder->decode([['op' => 'add_image', 'path' => 'linked/new.png', 'page' => 1, 'bbox' => [0, 0, 1, 1]]]);

        $this->expectException(PdfEditorException::class);
        $this->expectExceptionMessage('escapes the workspace');
        $decoder->bindAddImagePathsToWorkspace($ops, $workspace);
    }

    public function testBindRejectsFileInMissingDirectory(): void
    {
        $decoder = new OperationDecoder();
        $ops     = $decoder->decode([['op' => 'add_image', 'path' => 'nope/new.png', 'page' => 1, 'bbox' => [0, 0, 1, 1]]]);

        $this->expectException(PdfEditorException::class);
        $this->expectExceptionMessage('escapes the workspace');
        $decoder->bindAddImagePathsToWorkspace($ops, $this->workspace());
    }

    private function workspace(): string
    {
        $dir = sys_get_temp_dir() . '/pdf-editor-bind-' . uniqid('', true);
        mkdir($dir, 0777, true);

        return $dir;
    }
}
