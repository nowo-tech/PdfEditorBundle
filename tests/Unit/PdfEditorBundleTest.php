<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit;

use Nowo\PdfEditorBundle\DependencyInjection\PdfEditorExtension;
use Nowo\PdfEditorBundle\PdfEditorBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfEditorBundle::class)]
final class PdfEditorBundleTest extends TestCase
{
    public function testGetContainerExtensionReturnsPdfEditorExtension(): void
    {
        $bundle = new PdfEditorBundle();
        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(PdfEditorExtension::class, $extension);
        self::assertSame($extension, $bundle->getContainerExtension());
    }
}
