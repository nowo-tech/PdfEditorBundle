<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Controller;

use Nowo\PdfEditorBundle\Config\EditorProfile;
use Nowo\PdfEditorBundle\Controller\EditorController;
use Nowo\PdfEditorBundle\Document\Workspace;
use Nowo\PdfEditorBundle\Document\WorkspaceManager;
use Nowo\PdfEditorBundle\Engine\PdfEngineInterface;
use Nowo\PdfEditorBundle\Exception\AccessDeniedException;
use Nowo\PdfEditorBundle\Exception\PdfEditorException;
use Nowo\PdfEditorBundle\Operation\OperationDecoder;
use Nowo\PdfEditorBundle\Security\PdfEditorAccessCheckerInterface;
use Nowo\PdfEditorBundle\Security\WorkspaceOwnerResolver;
use Nowo\PdfEditorBundle\Tests\ProfileFactory;
use Nowo\UiKitBundle\View\FormPage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

use function intdiv;

use const JSON_THROW_ON_ERROR;

#[CoversClass(EditorController::class)]
final class EditorControllerTest extends TestCase
{
    public function testIndexDenied(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->controller(canUse: false)->index(new Request());
    }

    public function testIndexRenders(): void
    {
        $response = $this->controller(withUserObject: true)->index(new Request());
        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexRendersTheUploadFormPageInTheDefaultTemplate(): void
    {
        $calls = [];
        $twig  = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $name, array $context) use (&$calls): string {
            $calls[] = [$name, $context];

            return '<main></main>';
        });

        $this->controller(twig: $twig)->index(new Request());

        self::assertSame('@NowoPdfEditorBundle/editor/index.html.twig', $calls[0][0]);
        $page = $calls[0][1]['page'];
        self::assertInstanceOf(FormPage::class, $page);
        self::assertSame('NowoPdfEditorBundle', $page->bundle);
        self::assertNull($page->layout);
        self::assertNull($calls[0][1]['layout']);
        self::assertSame('NowoPdfEditorBundle', $calls[0][1]['bundle']);
        self::assertSame($page->form, $calls[0][1]['form']);
    }

    public function testConfiguredTemplatesAndLayoutAreUsed(): void
    {
        $root  = $this->workspaceRoot();
        $ws    = $this->seedWorkspace($root);
        $calls = [];
        $twig  = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $name, array $context) use (&$calls): string {
            $calls[] = [$name, $context];

            return '<main></main>';
        });
        $controller = $this->controller(workspaceRoot: $root, twig: $twig, layout: 'admin/layout.html.twig', templates: [
            'upload'    => 'admin/pdf/upload.html.twig',
            'workspace' => 'admin/pdf/workspace.html.twig',
        ]);

        $controller->index(new Request());
        $controller->workspace($ws->id);

        self::assertSame('admin/pdf/upload.html.twig', $calls[0][0]);
        self::assertSame('admin/layout.html.twig', $calls[0][1]['page']->layout);
        self::assertSame('admin/pdf/workspace.html.twig', $calls[1][0]);
        self::assertSame('admin/layout.html.twig', $calls[1][1]['layout']);
        self::assertSame('NowoPdfEditorBundle', $calls[1][1]['bundle']);
        self::assertSame($ws->id, $calls[1][1]['workspace']->id);
    }

    public function testIndexSubmittedWithoutFile(): void
    {
        $response = $this->controller(submittedEmpty: true)->index(new Request());
        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexRedirectsWhenPdfUploaded(): void
    {
        $root = $this->workspaceRoot();
        $pdf  = $root . '/in.pdf';
        file_put_contents($pdf, '%PDF-1.4');
        $upload   = new UploadedFile($pdf, 'in.pdf', 'application/pdf', null, true);
        $response = $this->controller(workspaceRoot: $root, submittedPdf: $upload)->index(new Request());
        self::assertSame(302, $response->getStatusCode());
    }

    public function testWorkspaceAndInspect(): void
    {
        $root       = $this->workspaceRoot();
        $ws         = $this->seedWorkspace($root);
        $controller = $this->controller(workspaceRoot: $root, inspect: ['pageCount' => 1]);
        self::assertSame(200, $controller->workspace($ws->id)->getStatusCode());
        $json = json_decode((string) $controller->inspect($ws->id)->getContent(), true);
        self::assertSame(1, $json['pageCount']);
    }

    public function testPageImageRejectsZero(): void
    {
        $root = $this->workspaceRoot();
        $ws   = $this->seedWorkspace($root);
        $this->expectException(PdfEditorException::class);
        $this->controller(workspaceRoot: $root)->pageImage($ws->id, 0);
    }

    public function testPageImageOk(): void
    {
        $root     = $this->workspaceRoot();
        $ws       = $this->seedWorkspace($root);
        $response = $this->controller(workspaceRoot: $root, writePageImage: true)->pageImage($ws->id, 1);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testPageImageUsesCachedPreview(): void
    {
        $root = $this->workspaceRoot();
        $ws   = $this->seedWorkspace($root);
        $png  = $ws->directory . '/page-1.png';
        file_put_contents($png, 'cached-png');
        touch($ws->pdfPath, time() - 10);
        touch($png, time());

        $engine = $this->createMock(PdfEngineInterface::class);
        $engine->expects(self::never())->method('renderPage');
        $engine->method('inspect')->willReturn(['pageCount' => 1]);

        $controller = $this->controllerWithEngine($root, $engine);
        $response   = $controller->pageImage($ws->id, 1);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testPageImageMissingFile(): void
    {
        $root = $this->workspaceRoot();
        $ws   = $this->seedWorkspace($root);
        $this->expectException(PdfEditorException::class);
        $this->controller(workspaceRoot: $root, writePageImage: false)->pageImage($ws->id, 1);
    }

    public function testApplyRequiresCsrf(): void
    {
        $root = $this->workspaceRoot();
        $ws   = $this->seedWorkspace($root);
        $this->expectException(AccessDeniedException::class);
        $this->controller(workspaceRoot: $root, csrf: false)->apply(new Request(), $ws->id);
    }

    public function testApplyInvalidJson(): void
    {
        $root    = $this->workspaceRoot();
        $ws      = $this->seedWorkspace($root);
        $request = Request::create('/', 'POST', [], [], [], [], '{}');
        $request->headers->set('X-CSRF-TOKEN', 'token');
        $this->expectException(PdfEditorException::class);
        $this->controller(workspaceRoot: $root)->apply($request, $ws->id);
    }

    public function testApplyOk(): void
    {
        $root = $this->workspaceRoot();
        $ws   = $this->seedWorkspace($root);
        file_put_contents($ws->directory . '/page-1.png', 'png');
        file_put_contents($ws->directory . '/page-1.png.lock', '');
        $request = Request::create('/', 'POST', [], [], [], [], '{"ops":[{"op":"rotate_page","page":1}]}');
        $request->headers->set('X-CSRF-TOKEN', 'token');
        $response = $this->controller(workspaceRoot: $root)->apply($request, $ws->id);
        self::assertSame(200, $response->getStatusCode());
        self::assertFileDoesNotExist($ws->directory . '/page-1.png');
        self::assertFileDoesNotExist($ws->directory . '/page-1.png.lock');
    }

    public function testApplyClientPdfBytes(): void
    {
        $root    = $this->workspaceRoot();
        $ws      = $this->seedWorkspace($root);
        $pdf     = '%PDF-1.4 client-bytes';
        $request = Request::create('/', 'POST', [], [], [], [], $pdf);
        $request->headers->set('Content-Type', 'application/pdf');
        $request->headers->set('X-CSRF-TOKEN', 'token');
        $response = $this->controller(workspaceRoot: $root)->apply($request, $ws->id);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame($pdf, (string) file_get_contents($ws->pdfPath));
    }

    public function testInspectInClientMode(): void
    {
        $root    = $this->workspaceRoot();
        $ws      = $this->seedWorkspace($root);
        $profile = new EditorProfile(
            name: 'default',
            pythonBinary: 'python3',
            engineScript: '/tmp/missing-engine.py',
            timeout: 5.0,
            idleTimeout: 2.0,
            maxUploadBytes: 1024 * 1024,
            renderDpi: 72,
            workspaceDir: $root,
            engineMode: 'client',
            allowUnauthenticated: true,
            roles: ['ROLE_ADMIN'],
        );
        $controller = $this->controllerWithProfile($profile);
        $json       = json_decode((string) $controller->inspect($ws->id)->getContent(), true);
        self::assertTrue($json['client']);
        self::assertNull($json['pageCount']);
    }

    public function testApplyEmptyPdfBody(): void
    {
        $root    = $this->workspaceRoot();
        $ws      = $this->seedWorkspace($root);
        $request = Request::create('/', 'POST', [], [], [], [], '');
        $request->headers->set('Content-Type', 'application/pdf');
        $request->headers->set('X-CSRF-TOKEN', 'token');
        $this->expectException(PdfEditorException::class);
        $this->controller(workspaceRoot: $root)->apply($request, $ws->id);
    }

    public function testApplyInvalidBase64Pdf(): void
    {
        $root    = $this->workspaceRoot();
        $ws      = $this->seedWorkspace($root);
        $request = Request::create('/', 'POST', [], [], [], [], '{"pdf":"%%%"}');
        $request->headers->set('X-CSRF-TOKEN', 'token');
        $this->expectException(PdfEditorException::class);
        $this->controller(workspaceRoot: $root)->apply($request, $ws->id);
    }

    public function testApplyBase64PdfOk(): void
    {
        $root    = $this->workspaceRoot();
        $ws      = $this->seedWorkspace($root);
        $pdf     = '%PDF-1.4 from-b64';
        $request = Request::create('/', 'POST', [], [], [], [], json_encode(['pdf' => base64_encode($pdf)], JSON_THROW_ON_ERROR));
        $request->headers->set('X-CSRF-TOKEN', 'token');
        $response = $this->controller(workspaceRoot: $root)->apply($request, $ws->id);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame($pdf, (string) file_get_contents($ws->pdfPath));
    }

    public function testApplyRejectsNonPdfClientPayload(): void
    {
        $root    = $this->workspaceRoot();
        $ws      = $this->seedWorkspace($root);
        $request = Request::create('/', 'POST', [], [], [], [], 'NOT-A-PDF');
        $request->headers->set('Content-Type', 'application/pdf');
        $request->headers->set('X-CSRF-TOKEN', 'token');
        $this->expectException(PdfEditorException::class);
        $this->controller(workspaceRoot: $root)->apply($request, $ws->id);
    }

    public function testViewInline(): void
    {
        $root     = $this->workspaceRoot();
        $ws       = $this->seedWorkspace($root);
        $response = $this->controller(workspaceRoot: $root)->view($ws->id);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('sandbox', $response->headers->get('Content-Security-Policy'));
    }

    public function testDownload(): void
    {
        $root     = $this->workspaceRoot();
        $ws       = $this->seedWorkspace($root);
        $response = $this->controller(workspaceRoot: $root)->download($ws->id);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function testUnknownWorkspaceIs404(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller()->view(str_repeat('ef', 16));
    }

    public function testWorkspaceOfAnotherOwnerIs404(): void
    {
        $root = $this->workspaceRoot();
        $ws   = $this->seedWorkspace($root);
        file_put_contents($ws->directory . '/owner.txt', 'user:' . hash('sha256', 'alice'));

        $this->expectException(NotFoundHttpException::class);
        $this->controller(workspaceRoot: $root, withUserObject: true)->download($ws->id);
    }

    public function testApplyRejectsBodyThatIsNeitherJsonNorPdf(): void
    {
        $root    = $this->workspaceRoot();
        $ws      = $this->seedWorkspace($root);
        $request = Request::create('/', 'POST', [], [], [], [], 'not json');
        $request->headers->set('X-CSRF-TOKEN', 'token');
        $this->expectException(PdfEditorException::class);
        $this->expectExceptionMessage('Request body must be JSON {"ops": [...]} or application/pdf.');
        $this->controller(workspaceRoot: $root)->apply($request, $ws->id);
    }

    public function testApplyOpsAreRejectedInClientMode(): void
    {
        $root    = $this->workspaceRoot();
        $ws      = $this->seedWorkspace($root);
        $request = Request::create('/', 'POST', [], [], [], [], '{"ops":[{"op":"rotate_page","page":1}]}');
        $request->headers->set('X-CSRF-TOKEN', 'token');
        $engine = $this->createMock(PdfEngineInterface::class);
        $engine->expects(self::never())->method('apply');
        $controller = $this->controllerWithProfile($this->clientProfile($root, 1024 * 1024), $engine);

        $this->expectException(PdfEditorException::class);
        $this->expectExceptionMessage('Client engine expects application/pdf');
        $controller->apply($request, $ws->id);
    }

    public function testApplyRejectsClientPdfAboveMaxUploadBytes(): void
    {
        $root    = $this->workspaceRoot();
        $ws      = $this->seedWorkspace($root);
        $request = Request::create('/', 'POST', [], [], [], [], '%PDF-1.4 ' . str_repeat('x', 2048));
        $request->headers->set('Content-Type', 'application/pdf');
        $request->headers->set('X-CSRF-TOKEN', 'token');
        $controller = $this->controllerWithProfile($this->clientProfile($root, 1024));

        try {
            $controller->apply($request, $ws->id);
            self::fail('Expected an oversized PDF to be rejected.');
        } catch (PdfEditorException $e) {
            self::assertSame('PDF exceeds max_upload_bytes (1024).', $e->getMessage());
        }
        self::assertSame('%PDF-1.4', (string) file_get_contents($ws->pdfPath));
        self::assertFileDoesNotExist($ws->directory . '/next.pdf');
    }

    public function testApplyRejectsOversizedDeclaredBodyBeforeReadingIt(): void
    {
        $root    = $this->workspaceRoot();
        $ws      = $this->seedWorkspace($root);
        $request = Request::create('/', 'POST', [], [], [], [], '%PDF-1.4');
        $request->headers->set('Content-Type', 'application/pdf');
        $request->headers->set('Content-Length', (string) (intdiv(1024 * 4, 3) + 65536 + 1));
        $request->headers->set('X-CSRF-TOKEN', 'token');
        $controller = $this->controllerWithProfile($this->clientProfile($root, 1024));

        $this->expectException(PdfEditorException::class);
        $this->expectExceptionMessage('Request body exceeds max_upload_bytes (1024).');
        $controller->apply($request, $ws->id);
    }

    public function testApplyAcceptsDeclaredBodyWithinTheBase64Allowance(): void
    {
        $root    = $this->workspaceRoot();
        $ws      = $this->seedWorkspace($root);
        $request = Request::create('/', 'POST', [], [], [], [], '%PDF-1.4 small');
        $request->headers->set('Content-Type', 'application/pdf');
        $request->headers->set('Content-Length', (string) (intdiv(1024 * 4, 3) + 65536));
        $request->headers->set('X-CSRF-TOKEN', 'token');

        $response = $this->controllerWithProfile($this->clientProfile($root, 1024))->apply($request, $ws->id);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testApplyIgnoresANonNumericContentLength(): void
    {
        $root    = $this->workspaceRoot();
        $ws      = $this->seedWorkspace($root);
        $request = Request::create('/', 'POST', [], [], [], [], '%PDF-1.4 small');
        $request->headers->set('Content-Type', 'application/pdf');
        $request->headers->set('Content-Length', 'abc');
        $request->headers->set('X-CSRF-TOKEN', 'token');

        self::assertSame(200, $this->controllerWithProfile($this->clientProfile($root, 1024))->apply($request, $ws->id)->getStatusCode());
    }

    private function clientProfile(string $root, int $maxUploadBytes): EditorProfile
    {
        return new EditorProfile(
            name: 'default',
            pythonBinary: 'python3',
            engineScript: '/tmp/missing-engine.py',
            timeout: 5.0,
            idleTimeout: 2.0,
            maxUploadBytes: $maxUploadBytes,
            renderDpi: 72,
            workspaceDir: $root,
            engineMode: 'client',
            allowUnauthenticated: true,
            roles: ['ROLE_ADMIN'],
        );
    }

    /**
     * @param array<string, mixed>                       $inspect
     * @param array{upload?: string, workspace?: string} $templates
     */
    private function controller(
        bool $canUse = true,
        ?string $workspaceRoot = null,
        array $inspect = [],
        bool $csrf = true,
        ?UploadedFile $submittedPdf = null,
        bool $writePageImage = true,
        bool $submittedEmpty = false,
        bool $withUserObject = false,
        ?Environment $twig = null,
        ?string $layout = null,
        array $templates = [],
    ): EditorController {
        $root   = $workspaceRoot ?? $this->workspaceRoot();
        $access = $this->createMock(PdfEditorAccessCheckerInterface::class);
        $access->method('canUseEditor')->willReturn($canUse);
        $tokens = $this->createMock(TokenStorageInterface::class);
        if ($withUserObject) {
            $user = $this->createMock(UserInterface::class);
            $user->method('getUserIdentifier')->willReturn('bob');
            $token = $this->createMock(TokenInterface::class);
            $token->method('getUser')->willReturn($user);
            $tokens->method('getToken')->willReturn($token);
        }
        $workspaces = new WorkspaceManager(ProfileFactory::create($root), new WorkspaceOwnerResolver($tokens));
        $engine     = $this->createMock(PdfEngineInterface::class);
        $engine->method('inspect')->willReturn($inspect);
        $engine->method('apply')->willReturnCallback(static function (string $pdf, array $operations, string $out): array {
            file_put_contents($out, 'rewritten');

            return ['applied' => 1];
        });
        $engine->method('renderPage')->willReturnCallback(static function (string $pdf, int $page, string $png) use ($writePageImage): void {
            if ($writePageImage) {
                file_put_contents($png, 'png');
            }
        });
        $pdfField = $this->createMock(FormInterface::class);
        $pdfField->method('getData')->willReturn($submittedPdf);
        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn($submittedPdf instanceof UploadedFile || $submittedEmpty);
        $form->method('isValid')->willReturn($submittedPdf instanceof UploadedFile || $submittedEmpty);
        $form->method('get')->willReturn($pdfField);
        $form->method('createView')->willReturn(new FormView());
        $forms = $this->createMock(FormFactoryInterface::class);
        $forms->method('create')->willReturn($form);
        if (!$twig instanceof Environment) {
            $twig = $this->createMock(Environment::class);
            $twig->method('render')->willReturn('<html></html>');
        }
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/pdf-editor/x');
        $csrfMgr = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfMgr->method('isTokenValid')->willReturn($csrf);

        return new EditorController(
            $access,
            $workspaces,
            $engine,
            new OperationDecoder(),
            $forms,
            $twig,
            $tokens,
            $urls,
            ProfileFactory::create($root),
            $csrfMgr,
            $layout,
            $templates,
        );
    }

    private function controllerWithEngine(string $root, PdfEngineInterface $engine): EditorController
    {
        return $this->controllerWithProfile(ProfileFactory::create($root), $engine);
    }

    private function controllerWithProfile(
        EditorProfile $profile,
        ?PdfEngineInterface $engine = null,
    ): EditorController {
        $access = $this->createMock(PdfEditorAccessCheckerInterface::class);
        $access->method('canUseEditor')->willReturn(true);
        $workspaces = new WorkspaceManager($profile);
        if (!$engine instanceof PdfEngineInterface) {
            $engine = $this->createMock(PdfEngineInterface::class);
            $engine->method('inspect')->willReturn(['pageCount' => 1]);
        }
        $forms = $this->createMock(FormFactoryInterface::class);
        $forms->method('create')->willReturnCallback(function (): MockObject {
            $form = $this->createMock(FormInterface::class);
            $form->method('createView')->willReturn(new FormView());

            return $form;
        });
        $twig    = $this->createMock(Environment::class);
        $tokens  = $this->createMock(TokenStorageInterface::class);
        $urls    = $this->createMock(UrlGeneratorInterface::class);
        $csrfMgr = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfMgr->method('isTokenValid')->willReturn(true);

        return new EditorController(
            $access,
            $workspaces,
            $engine,
            new OperationDecoder(),
            $forms,
            $twig,
            $tokens,
            $urls,
            $profile,
            $csrfMgr,
        );
    }

    private function workspaceRoot(): string
    {
        $root = sys_get_temp_dir() . '/pdfed-ctrl-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);

        return $root;
    }

    private function seedWorkspace(string $root): Workspace
    {
        $id  = str_repeat('ab', 16);
        $dir = $root . '/' . $id;
        mkdir($dir, 0700, true);
        file_put_contents($dir . '/current.pdf', '%PDF-1.4');
        file_put_contents($dir . '/meta.txt', 'a.pdf');

        return new Workspace($id, $dir, $dir . '/current.pdf', 'a.pdf');
    }
}
