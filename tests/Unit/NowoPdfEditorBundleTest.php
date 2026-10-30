<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit;

use Nowo\PdfEditorBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use Nowo\PdfEditorBundle\DependencyInjection\Compiler\SecurityBundlePass;
use Nowo\PdfEditorBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\PdfEditorBundle\DependencyInjection\PdfEditorExtension;
use Nowo\PdfEditorBundle\NowoPdfEditorBundle;
use Nowo\PdfEditorBundle\PdfEditorBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_map;
use function dirname;

#[CoversClass(NowoPdfEditorBundle::class)]
#[CoversClass(PdfEditorBundle::class)]
final class NowoPdfEditorBundleTest extends TestCase
{
    public function testGetContainerExtensionReturnsPdfEditorExtension(): void
    {
        $bundle    = new NowoPdfEditorBundle();
        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(PdfEditorExtension::class, $extension);
        self::assertSame($extension, $bundle->getContainerExtension());
    }

    public function testNameMatchesTwigNamespaceAndTranslationDomain(): void
    {
        $bundle = new NowoPdfEditorBundle();

        self::assertSame('NowoPdfEditorBundle', $bundle->getName());
        self::assertSame(TwigPathsPass::TWIG_NAMESPACE, $bundle->getName());
        self::assertFileExists(dirname(__DIR__, 2) . '/src/Resources/translations/' . $bundle->getName() . '.en.yaml');
    }

    public function testBuildRegistersTheCompilerPasses(): void
    {
        $container = new ContainerBuilder();
        (new NowoPdfEditorBundle())->build($container);

        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        self::assertContains(TwigPathsPass::class, $passes);
        self::assertContains(SecurityBundlePass::class, $passes);
        self::assertContains(CsrfProtectionPass::class, $passes);
    }

    public function testDeprecatedBundleClassStillBuildsAndKeepsItsName(): void
    {
        $this->expectUserDeprecationMessage('Since nowo-tech/pdf-editor-bundle 1.5: The "Nowo\PdfEditorBundle\PdfEditorBundle" bundle class is deprecated, register "Nowo\PdfEditorBundle\NowoPdfEditorBundle" in config/bundles.php instead and import "@NowoPdfEditorBundle/Resources/config/routing.yaml" (see docs/UPGRADING.md).');

        $bundle    = new PdfEditorBundle();
        $container = new ContainerBuilder();
        $bundle->build($container);

        self::assertSame('PdfEditorBundle', $bundle->getName());
        self::assertInstanceOf(PdfEditorExtension::class, $bundle->getContainerExtension());
        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        self::assertContains(TwigPathsPass::class, $passes);
    }
}
