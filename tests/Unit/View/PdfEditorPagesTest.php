<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\View;

use Nowo\PdfEditorBundle\View\PdfEditorPages;
use Nowo\UiKitBundle\View\Label;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormView;

#[CoversClass(PdfEditorPages::class)]
final class PdfEditorPagesTest extends TestCase
{
    public function testUploadPage(): void
    {
        $form = new FormView();
        $page = PdfEditorPages::upload($form, 'admin/layout.html.twig');

        self::assertSame($form, $page->form);
        self::assertSame('NowoPdfEditorBundle', $page->bundle);
        self::assertSame('admin/layout.html.twig', $page->layout);
        self::assertEquals(new Label('pdf_editor.home.heading', 'NowoPdfEditorBundle'), $page->header->title);
        self::assertEquals(new Label('pdf_editor.home.lead', 'NowoPdfEditorBundle'), $page->header->intro);
        self::assertEquals(new Label('pdf_editor.home.open', 'NowoPdfEditorBundle'), $page->submitLabel);
        self::assertSame(['data-testid' => 'pdf-editor-upload'], $page->attributes);
    }

    public function testDefaultsToTheUiKitLayout(): void
    {
        self::assertNull(PdfEditorPages::upload(new FormView())->layout);
    }
}
