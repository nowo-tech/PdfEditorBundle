<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Form;

use Nowo\PdfEditorBundle\Form\EditorTextType;
use Nowo\PdfEditorBundle\Form\PdfUploadType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;

#[CoversClass(PdfUploadType::class)]
#[CoversClass(EditorTextType::class)]
final class FormTypesTest extends TestCase
{
    public function testTypesBuild(): void
    {
        $factory = Forms::createFormFactory();
        $upload  = $factory->create(PdfUploadType::class);
        $text    = $factory->create(EditorTextType::class);
        self::assertTrue($upload->has('pdf'));
        self::assertTrue($text->has('text'));
        self::assertSame('NowoPdfEditorBundle', $upload->getConfig()->getOption('translation_domain'));
        self::assertSame('pdf_editor', $text->getConfig()->getOption('csrf_token_id'));
    }
}
