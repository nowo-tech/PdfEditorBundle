<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Controller;

use Nowo\PdfEditorBundle\Config\EditorProfile;
use Nowo\PdfEditorBundle\Document\Workspace;
use Nowo\PdfEditorBundle\Document\WorkspaceManager;
use Nowo\PdfEditorBundle\Engine\PdfEngineInterface;
use Nowo\PdfEditorBundle\Exception\AccessDeniedException;
use Nowo\PdfEditorBundle\Exception\PdfEditorException;
use Nowo\PdfEditorBundle\Form\EditorTextType;
use Nowo\PdfEditorBundle\Form\PdfUploadType;
use Nowo\PdfEditorBundle\Operation\OperationDecoder;
use Nowo\PdfEditorBundle\Security\PdfEditorAccessCheckerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

use function glob;
use function is_array;
use function is_file;
use function is_object;
use function json_decode;
use function sprintf;
use function unlink;
use function filemtime;
use function flock;
use function fopen;
use function fclose;
use function str_starts_with;
use function file_put_contents;

final class EditorController
{
    public function __construct(
        private readonly PdfEditorAccessCheckerInterface $accessChecker,
        private readonly WorkspaceManager $workspaces,
        private readonly PdfEngineInterface $engine,
        private readonly OperationDecoder $decoder,
        private readonly FormFactoryInterface $formFactory,
        private readonly Environment $twig,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EditorProfile $profile,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->assertAccess();
        $this->workspaces->ensureWorkspaceDir();

        $form = $this->formFactory->create(PdfUploadType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $pdf = $form->get('pdf')->getData();
            if ($pdf instanceof UploadedFile) {
                $workspace = $this->workspaces->createFromUpload($pdf);

                return new RedirectResponse($this->urlGenerator->generate('nowo_pdf_editor_workspace', ['id' => $workspace->id]));
            }
        }

        return new Response($this->twig->render('@PdfEditor/editor/index.html.twig', [
            'form' => $form->createView(),
        ]));
    }

    public function workspace(string $id): Response
    {
        $this->assertAccess();
        $workspace = $this->workspaces->get($id);

        $textForm = $this->formFactory->create(EditorTextType::class);

        return new Response($this->twig->render('@PdfEditor/editor/workspace.html.twig', [
            'workspace'   => $workspace,
            'textForm'    => $textForm->createView(),
            'engine_mode' => $this->profile->engineMode,
        ]));
    }

    public function inspect(string $id): JsonResponse
    {
        $this->assertAccess();
        $workspace = $this->workspaces->get($id);

        if ($this->profile->engineMode === 'client') {
            return new JsonResponse([
                'engine'    => 'client',
                'pageCount' => null,
                'client'    => true,
            ]);
        }

        return new JsonResponse($this->engine->inspect($workspace->pdfPath));
    }

    public function pageImage(string $id, int $page): Response
    {
        $this->assertAccess();
        $workspace = $this->workspaces->get($id);
        if ($page < 1) {
            throw new PdfEditorException('Page number must be 1 or greater.');
        }

        $png = $this->workspaces->pageImagePath($workspace, $page);
        $this->ensurePageImage($workspace, $page, $png);
        if (!is_file($png)) {
            throw new PdfEditorException('The page image was not generated.');
        }

        $response = new BinaryFileResponse($png);
        $response->headers->set('Content-Type', 'image/png');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, sprintf('page-%d.png', $page));

        return $response;
    }

    private function ensurePageImage(Workspace $workspace, int $page, string $png): void
    {
        $pdfMtime = filemtime($workspace->pdfPath);
        if (is_file($png) && $pdfMtime !== false && filemtime($png) >= $pdfMtime) {
            return;
        }

        $lockPath = $png . '.lock';
        $lock = @fopen($lockPath, 'c+');
        if ($lock === false) {
            $this->engine->renderPage($workspace->pdfPath, $page, $png, $this->profile->renderDpi);

            return;
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                $this->engine->renderPage($workspace->pdfPath, $page, $png, $this->profile->renderDpi);

                return;
            }

            if (is_file($png) && $pdfMtime !== false && filemtime($png) >= $pdfMtime) {
                return;
            }

            $this->engine->renderPage($workspace->pdfPath, $page, $png, $this->profile->renderDpi);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function apply(Request $request, string $id): JsonResponse
    {
        $this->assertAccess();
        $csrf = (string) $request->headers->get('X-CSRF-TOKEN', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('pdf_editor', $csrf))) {
            throw AccessDeniedException::workspace();
        }
        $workspace = $this->workspaces->get($id);
        $contentType = strtolower((string) $request->headers->get('Content-Type', ''));

        if (str_starts_with($contentType, 'application/pdf')) {
            return $this->applyClientPdf($request, $workspace);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            throw new PdfEditorException('Request body must be JSON {"ops": [...]} or application/pdf.');
        }

        if (is_string($payload['pdf'] ?? null)) {
            return $this->applyClientPdfBase64($workspace, (string) $payload['pdf']);
        }

        if ($this->profile->engineMode === 'client') {
            throw new PdfEditorException('Client engine expects application/pdf or {"pdf":"..."} on apply.');
        }

        if (!is_array($payload['ops'] ?? null)) {
            throw new PdfEditorException('Request body must be JSON {"ops": [...]} or {"pdf": "..."}.');
        }

        /** @var list<mixed> $rawOps */
        $rawOps = $payload['ops'];
        $ops    = $this->decoder->decode($rawOps);
        $target = $workspace->directory . '/next.pdf';
        $result = $this->engine->apply($workspace->pdfPath, $ops, $target);
        $this->workspaces->replacePdf($workspace, $target);
        $this->clearPageImages($workspace);

        return new JsonResponse(['ok' => true, 'result' => $result]);
    }

    public function view(string $id): BinaryFileResponse
    {
        $this->assertAccess();
        $workspace = $this->workspaces->get($id);
        $response  = new BinaryFileResponse($workspace->pdfPath);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $workspace->originalName);

        return $response;
    }

    public function download(string $id): BinaryFileResponse
    {
        $this->assertAccess();
        $workspace = $this->workspaces->get($id);
        $response  = new BinaryFileResponse($workspace->pdfPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $workspace->originalName);

        return $response;
    }

    private function assertAccess(): void
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        $user = is_object($user) ? $user : null;
        if (!$this->accessChecker->canUseEditor($user)) {
            throw AccessDeniedException::workspace();
        }
    }

    private function applyClientPdf(Request $request, Workspace $workspace): JsonResponse
    {
        $bytes = $request->getContent();
        if ($bytes === '' || $bytes === false) {
            throw new PdfEditorException('PDF body is empty.');
        }

        return $this->storeClientPdf($workspace, $bytes);
    }

    private function applyClientPdfBase64(Workspace $workspace, string $encoded): JsonResponse
    {
        $bytes = base64_decode($encoded, true);
        if ($bytes === false || $bytes === '') {
            throw new PdfEditorException('Invalid base64 PDF payload.');
        }

        return $this->storeClientPdf($workspace, $bytes);
    }

    private function storeClientPdf(Workspace $workspace, string $bytes): JsonResponse
    {
        if (!str_starts_with($bytes, '%PDF')) {
            throw new PdfEditorException('Uploaded content is not a PDF file.');
        }

        $target = $workspace->directory . '/next.pdf';
        if (file_put_contents($target, $bytes) === false) {
            throw new PdfEditorException('Could not store the edited PDF.');
        }

        $this->workspaces->replacePdf($workspace, $target);
        $this->clearPageImages($workspace);

        return new JsonResponse(['ok' => true, 'engine' => 'client']);
    }

    private function clearPageImages(Workspace $workspace): void
    {
        foreach (glob($workspace->directory . '/page-*.png') ?: [] as $stale) {
            @unlink($stale);
        }
    }
}
