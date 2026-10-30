<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\PdfEditorBundle\DependencyInjection\PdfEditorExtension;
use Nowo\PdfEditorBundle\Form\EditorTextType;
use Nowo\PdfEditorBundle\Form\PdfUploadType;
use Nowo\PdfEditorBundle\Tests\Support\FormKitTestSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(PdfUploadType::class)]
#[CoversClass(EditorTextType::class)]
final class FormTypesTest extends TestCase
{
    public function testTypesBuild(): void
    {
        $factory = FormKitTestSupport::formFactory();
        $upload  = $factory->create(PdfUploadType::class);
        $text    = $factory->create(EditorTextType::class);
        self::assertTrue($upload->has('pdf'));
        self::assertTrue($text->has('text'));
        self::assertSame('NowoPdfEditorBundle', $upload->getConfig()->getOption('translation_domain'));
        self::assertSame('pdf_editor', $text->getConfig()->getOption('csrf_token_id'));
        self::assertSame('pdf_editor.upload.label', $upload->get('pdf')->getConfig()->getOption('label'));
        self::assertSame('NowoPdfEditorBundle', $upload->get('pdf')->getConfig()->getOption('translation_domain'));
        self::assertNull($upload->get('pdf')->getConfig()->getOption('help'), 'No convention help key (raw key).');
        self::assertSame('nowo-pdf-editor-text', $text->get('text')->getConfig()->getOption('attr')['id'] ?? null);
    }

    public function testEveryFormTypeIsAFormKitTypeWithTheBundleProfile(): void
    {
        $files = glob(__DIR__ . '/../../../src/Form/*Type.php');
        self::assertNotFalse($files);
        self::assertNotEmpty($files);
        foreach ($files as $file) {
            /** @var class-string $class */
            $class      = 'Nowo\\PdfEditorBundle\\Form\\' . basename($file, '.php');
            $attributes = (new ReflectionClass($class))->getAttributes(FormKitConfig::class);
            self::assertCount(1, $attributes, $class . ' carries #[FormKitConfig].');
            self::assertSame(PdfEditorExtension::FORM_KIT_PROFILE, $attributes[0]->newInstance()->name);
            self::assertContains(FormOptionsTrait::class, class_uses($class), $class . ' uses FormOptionsTrait.');
        }
    }
}
