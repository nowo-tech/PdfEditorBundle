<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Controller;

use Nowo\PdfEditorBundle\Controller\EditorController;
use Nowo\PdfEditorBundle\Document\Workspace;
use Nowo\PdfEditorBundle\Document\WorkspaceManager;
use Nowo\PdfEditorBundle\Engine\PdfEngineInterface;
use Nowo\PdfEditorBundle\Exception\AccessDeniedException;
use Nowo\PdfEditorBundle\Exception\PdfEditorException;
use Nowo\PdfEditorBundle\Operation\OperationDecoder;
use Nowo\PdfEditorBundle\Security\PdfEditorAccessCheckerInterface;
use Nowo\PdfEditorBundle\Tests\ProfileFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

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

    public function testIndexSubmittedWithoutFile(): void
    {
        $response = $this->controller(submittedEmpty: true)->index(new Request());
        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexRedirectsWhenPdfUploaded(): void
    {
        $root = $this->workspaceRoot();
        $pdf = $root . '/in.pdf';
        file_put_contents($pdf, '%PDF-1.4');
        $upload = new UploadedFile($pdf, 'in.pdf', 'application/pdf', null, true);
        $response = $this->controller(workspaceRoot: $root, submittedPdf: $upload)->index(new Request());
        self::assertSame(302, $response->getStatusCode());
    }

    public function testWorkspaceAndInspect(): void
    {
        $root = $this->workspaceRoot();
        $ws = $this->seedWorkspace($root);
        $controller = $this->controller(workspaceRoot: $root, inspect: ['pageCount' => 1]);
        self::assertSame(200, $controller->workspace($ws->id)->getStatusCode());
        $json = json_decode((string) $controller->inspect($ws->id)->getContent(), true);
        self::assertSame(1, $json['pageCount']);
    }

    public function testPageImageRejectsZero(): void
    {
        $root = $this->workspaceRoot();
        $ws = $this->seedWorkspace($root);
        $this->expectException(PdfEditorException::class);
        $this->controller(workspaceRoot: $root)->pageImage($ws->id, 0);
    }

    public function testPageImageOk(): void
    {
        $root = $this->workspaceRoot();
        $ws = $this->seedWorkspace($root);
        $response = $this->controller(workspaceRoot: $root, writePageImage: true)->pageImage($ws->id, 1);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testPageImageUsesCachedPreview(): void
    {
        $root = $this->workspaceRoot();
        $ws = $this->seedWorkspace($root);
        $png = $ws->directory . '/page-1.png';
        file_put_contents($png, 'cached-png');
        touch($ws->pdfPath, time() - 10);
        touch($png, time());

        $engine = $this->createMock(PdfEngineInterface::class);
        $engine->expects(self::never())->method('renderPage');
        $engine->method('inspect')->willReturn(['pageCount' => 1]);

        $controller = $this->controllerWithEngine($root, $engine);
        $response = $controller->pageImage($ws->id, 1);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testPageImageMissingFile(): void
    {
        $root = $this->workspaceRoot();
        $ws = $this->seedWorkspace($root);
        $this->expectException(PdfEditorException::class);
        $this->controller(workspaceRoot: $root, writePageImage: false)->pageImage($ws->id, 1);
    }

    public function testApplyRequiresCsrf(): void
    {
        $root = $this->workspaceRoot();
        $ws = $this->seedWorkspace($root);
        $this->expectException(AccessDeniedException::class);
        $this->controller(workspaceRoot: $root, csrf: false)->apply(new Request(), $ws->id);
    }

    public function testApplyInvalidJson(): void
    {
        $root = $this->workspaceRoot();
        $ws = $this->seedWorkspace($root);
        $request = Request::create('/', 'POST', [], [], [], [], '{}');
        $request->headers->set('X-CSRF-TOKEN', 'token');
        $this->expectException(PdfEditorException::class);
        $this->controller(workspaceRoot: $root)->apply($request, $ws->id);
    }

    public function testApplyOk(): void
    {
        $root = $this->workspaceRoot();
        $ws = $this->seedWorkspace($root);
        file_put_contents($ws->directory . '/page-1.png', 'png');
        $request = Request::create('/', 'POST', [], [], [], [], '{"ops":[{"op":"rotate_page","page":1}]}');
        $request->headers->set('X-CSRF-TOKEN', 'token');
        $response = $this->controller(workspaceRoot: $root)->apply($request, $ws->id);
        self::assertSame(200, $response->getStatusCode());
        self::assertFileDoesNotExist($ws->directory . '/page-1.png');
    }

    public function testApplyClientPdfBytes(): void
    {
        $root = $this->workspaceRoot();
        $ws = $this->seedWorkspace($root);
        $pdf = '%PDF-1.4 client-bytes';
        $request = Request::create('/', 'POST', [], [], [], [], $pdf);
        $request->headers->set('Content-Type', 'application/pdf');
        $request->headers->set('X-CSRF-TOKEN', 'token');
        $response = $this->controller(workspaceRoot: $root)->apply($request, $ws->id);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame($pdf, (string) file_get_contents($ws->pdfPath));
    }

    public function testViewInline(): void
    {
        $root = $this->workspaceRoot();
        $ws = $this->seedWorkspace($root);
        $response = $this->controller(workspaceRoot: $root)->view($ws->id);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function testDownload(): void
    {
        $root = $this->workspaceRoot();
        $ws = $this->seedWorkspace($root);
        $response = $this->controller(workspaceRoot: $root)->download($ws->id);
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @param array<string, mixed> $inspect
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
    ): EditorController {
        $root = $workspaceRoot ?? $this->workspaceRoot();
        $access = $this->createMock(PdfEditorAccessCheckerInterface::class);
        $access->method('canUseEditor')->willReturn($canUse);
        $workspaces = new WorkspaceManager(ProfileFactory::create($root));
        $engine = $this->createMock(PdfEngineInterface::class);
        $engine->method('inspect')->willReturn($inspect);
        $engine->method('apply')->willReturnCallback(static function (string $pdf, array $operations, string $out) {
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
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<html></html>');
        $tokens = $this->createMock(TokenStorageInterface::class);
        if ($withUserObject) {
            $user = $this->createMock(UserInterface::class);
            $token = $this->createMock(TokenInterface::class);
            $token->method('getUser')->willReturn($user);
            $tokens->method('getToken')->willReturn($token);
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
        );
    }

    private function controllerWithEngine(string $root, PdfEngineInterface $engine): EditorController
    {
        $access = $this->createMock(PdfEditorAccessCheckerInterface::class);
        $access->method('canUseEditor')->willReturn(true);
        $workspaces = new WorkspaceManager(ProfileFactory::create($root));
        $forms = $this->createMock(FormFactoryInterface::class);
        $forms->method('create')->willReturnCallback(function () {
            $form = $this->createMock(FormInterface::class);
            $form->method('createView')->willReturn(new FormView());

            return $form;
        });
        $twig = $this->createMock(Environment::class);
        $tokens = $this->createMock(TokenStorageInterface::class);
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $csrfMgr = $this->createMock(CsrfTokenManagerInterface::class);

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
        $id = str_repeat('ab', 16);
        $dir = $root . '/' . $id;
        mkdir($dir, 0700, true);
        file_put_contents($dir . '/current.pdf', '%PDF-1.4');
        file_put_contents($dir . '/meta.txt', 'a.pdf');

        return new Workspace($id, $dir, $dir . '/current.pdf', 'a.pdf');
    }
}
