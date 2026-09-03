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
}
