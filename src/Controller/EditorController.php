<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Controller;

use Nowo\PdfEditorBundle\Config\EditorProfile;
use Nowo\PdfEditorBundle\Document\Workspace;
use Nowo\PdfEditorBundle\Document\WorkspaceManager;
use Nowo\PdfEditorBundle\Engine\PdfEngineInterface;
use Nowo\PdfEditorBundle\Exception\AccessDeniedException;
use Nowo\PdfEditorBundle\Exception\DocumentException;
use Nowo\PdfEditorBundle\Exception\PdfEditorException;
use Nowo\PdfEditorBundle\Form\EditorTextType;
use Nowo\PdfEditorBundle\Form\PdfUploadType;
use Nowo\PdfEditorBundle\Operation\OperationDecoder;
use Nowo\PdfEditorBundle\Security\PdfEditorAccessCheckerInterface;
use Nowo\PdfEditorBundle\View\PdfEditorPages;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

use function ctype_digit;
use function fclose;
use function file_put_contents;
use function filemtime;
use function flock;
use function fopen;
use function glob;
use function intdiv;
use function is_array;
use function is_file;
use function is_object;
use function is_string;
use function json_decode;
use function sprintf;
use function str_starts_with;
use function strlen;
use function unlink;

use const LOCK_EX;
use const LOCK_UN;

#[AsController]
final class EditorController
{
    /** Room for the JSON envelope / operation list around a base64 PDF in the apply body. */
    private const JSON_ENVELOPE_BYTES = 65536;

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
        private readonly ?string $layoutTemplate = null,
        /** @var array{upload?: string, workspace?: string} */
        private readonly array $templates = [],
    ) {
    }

    #[Route(path: '/pdf-editor', name: 'nowo_pdf_editor_home', methods: ['GET', 'POST'])]
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

        $formView = $form->createView();

        return new Response($this->twig->render($this->templates['upload'] ?? '@NowoPdfEditorBundle/editor/index.html.twig', [
            'page'   => PdfEditorPages::upload($formView, $this->layoutTemplate),
            'form'   => $formView,
            'layout' => $this->layoutTemplate,
            'bundle' => PdfEditorPages::BUNDLE,
        ]));
    }

    #[Route(path: '/pdf-editor/{id}', name: 'nowo_pdf_editor_workspace', requirements: ['id' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function workspace(string $id): Response
    {
        $this->assertAccess();
        $workspace = $this->ownedWorkspace($id);

        $textForm = $this->formFactory->create(EditorTextType::class);

        return new Response($this->twig->render($this->templates['workspace'] ?? '@NowoPdfEditorBundle/editor/workspace.html.twig', [
            'layout'      => $this->layoutTemplate,
            'bundle'      => PdfEditorPages::BUNDLE,
            'workspace'   => $workspace,
            'textForm'    => $textForm->createView(),
            'engine_mode' => $this->profile->engineMode,
        ]));
    }

    #[Route(path: '/pdf-editor/{id}/inspect', name: 'nowo_pdf_editor_inspect', requirements: ['id' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function inspect(string $id): JsonResponse
    {
        $this->assertAccess();
        $workspace = $this->ownedWorkspace($id);

        if ($this->profile->engineMode === 'client') {
            return new JsonResponse([
                'engine'    => 'client',
                'pageCount' => null,
                'client'    => true,
            ]);
        }

        return new JsonResponse($this->engine->inspect($workspace->pdfPath));
    }

    #[Route(path: '/pdf-editor/{id}/pages/{page}', name: 'nowo_pdf_editor_page', requirements: ['id' => '[a-f0-9]{32}', 'page' => '\d+'], methods: ['GET'])]
    public function pageImage(string $id, int $page): BinaryFileResponse
    {
        $this->assertAccess();
        $workspace = $this->ownedWorkspace($id);
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
        $lock     = @fopen($lockPath, 'c+');
        if ($lock === false) { // @codeCoverageIgnoreStart
            $this->engine->renderPage($workspace->pdfPath, $page, $png, $this->profile->renderDpi);

            return;
            // @codeCoverageIgnoreEnd
        }

        try {
            if (!flock($lock, LOCK_EX)) { // @codeCoverageIgnoreStart
                $this->engine->renderPage($workspace->pdfPath, $page, $png, $this->profile->renderDpi);

                return;
                // @codeCoverageIgnoreEnd
            }

            if (is_file($png) && $pdfMtime !== false && filemtime($png) >= $pdfMtime) { // @codeCoverageIgnoreStart
                return;
                // @codeCoverageIgnoreEnd
            }

            $this->engine->renderPage($workspace->pdfPath, $page, $png, $this->profile->renderDpi);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    #[Route(path: '/pdf-editor/{id}/apply', name: 'nowo_pdf_editor_apply', requirements: ['id' => '[a-f0-9]{32}'], methods: ['POST'])]
    public function apply(Request $request, string $id): JsonResponse
    {
        $this->assertAccess();
        $csrf = (string) $request->headers->get('X-CSRF-TOKEN', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('pdf_editor', $csrf))) {
            throw AccessDeniedException::workspace();
        }
        $workspace = $this->ownedWorkspace($id);
        $this->assertDeclaredBodySize($request);
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
        $ops    = $this->decoder->bindAddImagePathsToWorkspace($ops, $workspace->directory);
        $target = $workspace->directory . '/next.pdf';
        $result = $this->engine->apply($workspace->pdfPath, $ops, $target);
        $this->workspaces->replacePdf($workspace, $target);
        $this->clearPageImages($workspace);

        return new JsonResponse(['ok' => true, 'result' => $result]);
    }

    #[Route(path: '/pdf-editor/{id}/view', name: 'nowo_pdf_editor_view', requirements: ['id' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function view(string $id): BinaryFileResponse
    {
        $this->assertAccess();
        $workspace = $this->ownedWorkspace($id);
        $response  = new BinaryFileResponse($workspace->pdfPath);
        $response->headers->set('Content-Type', 'application/pdf');
        // Served inline from the app origin: never sniff, and sandbox it so a crafted PDF/HTML polyglot
        // opened directly cannot run script with the application's origin.
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', 'sandbox');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $workspace->originalName);

        return $response;
    }

    #[Route(path: '/pdf-editor/{id}/download', name: 'nowo_pdf_editor_download', requirements: ['id' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function download(string $id): BinaryFileResponse
    {
        $this->assertAccess();
        $workspace = $this->ownedWorkspace($id);
        $response  = new BinaryFileResponse($workspace->pdfPath);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $workspace->originalName);

        return $response;
    }

    /**
     * Unknown ids and workspaces owned by another user / session are both a plain 404 (no existence oracle).
     */
    private function ownedWorkspace(string $id): Workspace
    {
        try {
            return $this->workspaces->get($id);
        } catch (DocumentException $e) {
            throw new NotFoundHttpException('PDF workspace not found.', $e);
        }
    }

    private function assertAccess(): void
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        $user = is_object($user) ? $user : null;
        if (!$this->accessChecker->canUseEditor($user)) {
            throw AccessDeniedException::workspace();
        }
    }

    /**
     * Rejects an oversized body from its Content-Length before reading it into memory: a raw PDF may be up to
     * max_upload_bytes, the base64 JSON variant up to 4/3 of it (plus a small JSON envelope).
     */
    private function assertDeclaredBodySize(Request $request): void
    {
        $max      = $this->profile->maxUploadBytes;
        $declared = $request->headers->get('Content-Length');
        if ($max <= 0 || $declared === null || !ctype_digit($declared)) {
            return;
        }

        if ((int) $declared > intdiv($max * 4, 3) + self::JSON_ENVELOPE_BYTES) {
            throw new PdfEditorException(sprintf('Request body exceeds max_upload_bytes (%d).', $max));
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
        $max = $this->profile->maxUploadBytes;
        if ($max > 0 && strlen($bytes) > $max) {
            throw new PdfEditorException(sprintf('PDF exceeds max_upload_bytes (%d).', $max));
        }

        if (!str_starts_with($bytes, '%PDF')) {
            throw new PdfEditorException('Uploaded content is not a PDF file.');
        }

        $target = $workspace->directory . '/next.pdf';
        if (file_put_contents($target, $bytes) === false) { // @codeCoverageIgnoreStart
            throw new PdfEditorException('Could not store the edited PDF.');
            // @codeCoverageIgnoreEnd
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
        // Lock files survive PNG deletion; remove them so long-lived workers do not accumulate empties.
        foreach (glob($workspace->directory . '/page-*.png.lock') ?: [] as $lock) {
            @unlink($lock);
        }
    }
}
