<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Template;

use Nowo\PdfEditorBundle\Form\PdfUploadType;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\TwigFunction;

use function dirname;

/**
 * Renders the bundle's upload page with the real Twig form theme.
 */
#[CoversNothing]
final class UploadTemplateTest extends TestCase
{
    public function testUploadFormRendersEnctypeExactlyOnce(): void
    {
        $bridgeDir = dirname((string) (new ReflectionClass(FormExtension::class))->getFileName(), 2);

        $loader = new FilesystemLoader([$bridgeDir . '/Resources/views/Form']);
        $loader->addPath(dirname(__DIR__, 3) . '/src/Resources/views', 'PdfEditor');

        $twig = new Environment($loader, ['strict_variables' => false]);
        $twig->addExtension(new FormExtension());
        $twig->addExtension(new TranslationExtension());
        $twig->addFunction(new TwigFunction('asset', static fn (string $path): string => '/' . $path));

        $engine = new TwigRendererEngine(['form_div_layout.html.twig'], $twig);
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => static fn (): FormRenderer => new FormRenderer($engine),
        ]));

        $form = Forms::createFormFactory()->create(PdfUploadType::class);
        $html = $twig->render('@PdfEditor/editor/index.html.twig', ['form' => $form->createView()]);

        self::assertMatchesRegularExpression('/<form\b[^>]*>/', $html);
        self::assertSame(1, preg_match_all('/enctype="multipart\/form-data"/', $html));
    }
}
