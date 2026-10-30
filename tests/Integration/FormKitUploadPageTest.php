<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Integration;

use Nowo\PdfEditorBundle\Form\PdfUploadType;
use Nowo\PdfEditorBundle\Tests\Integration\Fixtures\RoutingTestKernel;
use Nowo\PdfEditorBundle\View\PdfEditorPages;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Twig\Environment;

use function uniqid;

/**
 * REQ-TWIG-005 rule 11: in a kernel with FormKit, the upload form is built through the {@code pdf_editor} profile
 * and the upload page renders translated labels (no raw keys).
 */
#[CoversNothing]
final class FormKitUploadPageTest extends TestCase
{
    private ?RoutingTestKernel $kernel = null;

    protected function tearDown(): void
    {
        if ($this->kernel instanceof RoutingTestKernel) {
            $this->kernel->shutdown();
            (new Filesystem())->remove($this->kernel->getProjectDir());
        }
    }

    public function testUploadPageRendersTheFormKitFormWithoutRawKeys(): void
    {
        $this->kernel = new RoutingTestKernel(true, uniqid('', true));
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        $profiles = $container->getParameter('nowo_form_kit.profiles');
        self::assertIsArray($profiles);
        self::assertSame('NowoPdfEditorBundle', $profiles['pdf_editor']['translation_domain'] ?? null);

        // CSRF tokens live in the session: render as inside a request.
        $request = Request::create('/pdf-editor');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requests = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requests);
        $requests->push($request);

        $forms = $container->get('form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);
        $form = $forms->create(PdfUploadType::class);
        self::assertSame('NowoPdfEditorBundle', $form->get('pdf')->getConfig()->getOption('translation_domain'));

        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $view = $form->createView();
        $html = $twig->render('@NowoPdfEditorBundle/editor/index.html.twig', [
            'page'   => PdfEditorPages::upload($view),
            'form'   => $view,
            'layout' => null,
            'bundle' => PdfEditorPages::BUNDLE,
        ]);

        self::assertStringContainsString('PDF file', $html);
        self::assertStringContainsString('type="file"', $html);
        self::assertStringContainsString('enctype="multipart/form-data"', $html);
        self::assertDoesNotMatchRegularExpression('/\bpdf_editor\.[a-z_.]+|[a-z_]+\.pdf\.(label|help|placeholder)/', $html);
    }
}
