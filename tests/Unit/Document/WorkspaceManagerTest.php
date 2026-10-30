<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Document;

use Nowo\PdfEditorBundle\Config\EditorProfile;
use Nowo\PdfEditorBundle\Document\Workspace;
use Nowo\PdfEditorBundle\Document\WorkspaceManager;
use Nowo\PdfEditorBundle\Exception\DocumentException;
use Nowo\PdfEditorBundle\Security\WorkspaceOwnerResolverInterface;
use Nowo\PdfEditorBundle\Tests\ProfileFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use const GLOB_ONLYDIR;
use const UPLOAD_ERR_INI_SIZE;

#[CoversClass(WorkspaceManager::class)]
#[CoversClass(Workspace::class)]
#[CoversClass(EditorProfile::class)]
final class WorkspaceManagerTest extends TestCase
{
    public function testCreateGetReplaceAndImages(): void
    {
        $root = sys_get_temp_dir() . '/pdfed-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $manager = new WorkspaceManager(ProfileFactory::create($root));
        $manager->ensureWorkspaceDir();

        $pdf = $root . '/in.pdf';
        file_put_contents($pdf, '%PDF-1.4 test');
        $workspace = $manager->createFromUpload(new UploadedFile($pdf, 'My Doc.pdf', 'application/pdf', null, true));
        self::assertFileExists($workspace->pdfPath);
        self::assertSame($workspace->id, $manager->get($workspace->id)->id);

        $next = $root . '/next.pdf';
        file_put_contents($next, '%PDF-1.4 next');
        $manager->replacePdf($workspace, $next);
        self::assertStringContainsString('next', (string) file_get_contents($workspace->pdfPath));
        self::assertStringContainsString('page-1.png', $manager->pageImagePath($workspace, 1));

        $this->expectException(DocumentException::class);
        $manager->get('../nope');
    }

    public function testTooLarge(): void
    {
        $root = sys_get_temp_dir() . '/pdfed-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $base = ProfileFactory::create($root);
        $tiny = new EditorProfile(
            name: $base->name,
            pythonBinary: $base->pythonBinary,
            engineScript: $base->engineScript,
            timeout: $base->timeout,
            idleTimeout: $base->idleTimeout,
            maxUploadBytes: 4,
            renderDpi: $base->renderDpi,
            workspaceDir: $base->workspaceDir,
            engineMode: $base->engineMode,
            allowUnauthenticated: $base->allowUnauthenticated,
            roles: $base->roles,
        );
        $pdf = $root . '/big.pdf';
        file_put_contents($pdf, '%PDF-1.4 too-big');
        $this->expectException(DocumentException::class);
        (new WorkspaceManager($tiny))->createFromUpload(new UploadedFile($pdf, 'big.pdf', 'application/pdf', null, true));
    }

    public function testInvalidUploadError(): void
    {
        $root = sys_get_temp_dir() . '/pdfed-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $pdf = $root . '/x.pdf';
        file_put_contents($pdf, 'x');
        $this->expectException(DocumentException::class);
        (new WorkspaceManager(ProfileFactory::create($root)))->createFromUpload(
            new UploadedFile($pdf, 'x.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true),
        );
    }

    public function testCreateFromPath(): void
    {
        $root = sys_get_temp_dir() . '/pdfed-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $manager = new WorkspaceManager(ProfileFactory::create($root));
        $manager->ensureWorkspaceDir();

        $fixture = $root . '/fixture.pdf';
        file_put_contents($fixture, '%PDF-1.4 fixture');
        $workspace = $manager->createFromPath($fixture, 'Scenario.pdf');
        self::assertFileExists($workspace->pdfPath);
        self::assertSame('Scenario.pdf', $workspace->originalName);
    }

    public function testCreateFromPathMissingFixture(): void
    {
        $root = sys_get_temp_dir() . '/pdfed-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $this->expectException(DocumentException::class);
        (new WorkspaceManager(ProfileFactory::create($root)))->createFromPath($root . '/missing.pdf');
    }

    public function testRejectsNonPdf(): void
    {
        $root = sys_get_temp_dir() . '/pdfed-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $txt = $root . '/note.txt';
        file_put_contents($txt, 'hello');
        $this->expectException(DocumentException::class);
        (new WorkspaceManager(ProfileFactory::create($root)))->createFromUpload(
            new UploadedFile($txt, 'note.txt', 'text/plain', null, true),
        );
    }

    public function testRejectsPdfExtensionWithoutPdfSignature(): void
    {
        $root = sys_get_temp_dir() . '/pdfed-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $fake = $root . '/fake.pdf';
        file_put_contents($fake, '<html><script>alert(document.domain)</script></html>');
        $manager = new WorkspaceManager(ProfileFactory::create($root));

        try {
            $manager->createFromUpload(new UploadedFile($fake, 'invoice.pdf', 'application/pdf', null, true));
            self::fail('expected non-PDF content to be rejected');
        } catch (DocumentException $e) {
            self::assertStringContainsString('not a PDF', $e->getMessage());
        }

        self::assertSame([], glob($root . '/*/current.pdf') ?: [], 'Rejected upload must not leave a workspace behind.');
        self::assertSame([], glob($root . '/*', GLOB_ONLYDIR) ?: []);
    }

    public function testWorkspaceIsBoundToItsOwner(): void
    {
        $root = sys_get_temp_dir() . '/pdfed-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $owner   = new MutableOwnerResolver('user:alice');
        $manager = new WorkspaceManager(ProfileFactory::create($root), $owner);

        $pdf = $root . '/in.pdf';
        file_put_contents($pdf, '%PDF-1.4 test');
        $workspace = $manager->createFromUpload(new UploadedFile($pdf, 'a.pdf', 'application/pdf', null, true));
        self::assertSame($workspace->id, $manager->get($workspace->id)->id);

        // Another user guessing / leaking the id gets "not found".
        $owner->owner = 'user:mallory';
        $this->assertNotFound($manager, $workspace->id);

        // Caller without any identity cannot open an owned workspace either.
        $owner->owner = null;
        $this->assertNotFound($manager, $workspace->id);

        $owner->owner = 'user:alice';
        self::assertSame($workspace->id, $manager->get($workspace->id)->id);
    }

    public function testWorkspaceFromPathIsBoundToOwner(): void
    {
        $root = sys_get_temp_dir() . '/pdfed-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $owner   = new MutableOwnerResolver('session:abc');
        $manager = new WorkspaceManager(ProfileFactory::create($root), $owner);
        $fixture = $root . '/fixture.pdf';
        file_put_contents($fixture, '%PDF-1.4 fixture');

        $workspace = $manager->createFromPath($fixture);
        self::assertStringEqualsFile($workspace->directory . '/owner.txt', 'session:abc');

        $owner->owner = 'session:other';
        $this->assertNotFound($manager, $workspace->id);
    }

    public function testOwnerlessWorkspacesFailClosedByDefault(): void
    {
        $root = sys_get_temp_dir() . '/pdfed-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $fixture = $root . '/fixture.pdf';
        file_put_contents($fixture, '%PDF-1.4 fixture');
        $workspace = (new WorkspaceManager(ProfileFactory::create($root), new MutableOwnerResolver(null), allowOwnerlessWorkspaces: true))->createFromPath($fixture);

        $manager = new WorkspaceManager(ProfileFactory::create($root), new MutableOwnerResolver(null));
        $this->assertNotFound($manager, $workspace->id);

        $this->expectException(DocumentException::class);
        $this->expectExceptionMessage('needs an authenticated user or a session');
        $manager->createFromPath($fixture);
    }

    public function testOwnerlessUploadIsRefusedByDefault(): void
    {
        $root = sys_get_temp_dir() . '/pdfed-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $pdf = $root . '/in.pdf';
        file_put_contents($pdf, '%PDF-1.4');

        $this->expectException(DocumentException::class);
        $this->expectExceptionMessage('needs an authenticated user or a session');
        (new WorkspaceManager(ProfileFactory::create($root), new MutableOwnerResolver(null)))
            ->createFromUpload(new UploadedFile($pdf, 'in.pdf', 'application/pdf', null, true));
    }

    public function testPageCountCache(): void
    {
        $root = sys_get_temp_dir() . '/pdfed-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $fixture = $root . '/fixture.pdf';
        file_put_contents($fixture, '%PDF-1.4 fixture');
        $manager   = new WorkspaceManager(ProfileFactory::create($root));
        $workspace = $manager->createFromPath($fixture);

        self::assertNull($manager->cachedPageCount($workspace));
        touch($workspace->pdfPath, time() - 10);
        $manager->storePageCount($workspace, 7);
        self::assertSame(7, $manager->cachedPageCount($workspace));

        file_put_contents($workspace->directory . '/page-count.txt', 'garbage');
        self::assertNull($manager->cachedPageCount($workspace));

        $manager->storePageCount($workspace, 7);
        touch($workspace->directory . '/page-count.txt', time() - 20);
        self::assertNull($manager->cachedPageCount($workspace), 'a count older than the PDF is stale');
    }

    public function testWorkspaceRootIsCreatedPrivateAndUnsafeRootsAreRefused(): void
    {
        $root    = sys_get_temp_dir() . '/pdfed-' . bin2hex(random_bytes(4)) . '/nested';
        $manager = new WorkspaceManager(ProfileFactory::create($root));
        $manager->ensureWorkspaceDir();
        self::assertSame(0o700, fileperms($root) & 0o777);

        chmod($root, 0o777);
        try {
            $manager->ensureWorkspaceDir();
            self::fail('A world-writable workspace root must be refused.');
        } catch (DocumentException $e) {
            self::assertStringContainsString('must not be group- or world-writable', $e->getMessage());
        }

        chmod($root, 0o755);
        $manager->ensureWorkspaceDir();
        $this->addToAssertionCount(1);
    }

    public function testUnownedWorkspaceOnlyReachableWithoutIdentity(): void
    {
        $root = sys_get_temp_dir() . '/pdfed-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $owner   = new MutableOwnerResolver(null);
        $manager = new WorkspaceManager(ProfileFactory::create($root), $owner, allowOwnerlessWorkspaces: true);
        $fixture = $root . '/fixture.pdf';
        file_put_contents($fixture, '%PDF-1.4 fixture');

        $workspace = $manager->createFromPath($fixture);
        self::assertFileDoesNotExist($workspace->directory . '/owner.txt');
        self::assertSame($workspace->id, $manager->get($workspace->id)->id);

        $owner->owner = 'user:alice';
        $this->assertNotFound($manager, $workspace->id);
    }

    private function assertNotFound(WorkspaceManager $manager, string $id): void
    {
        try {
            $manager->get($id);
            self::fail('expected workspace to be hidden from this caller');
        } catch (DocumentException $e) {
            self::assertStringContainsString('was not found', $e->getMessage());
        }
    }

    public function testGetMissingAndReplaceMissing(): void
    {
        $root = sys_get_temp_dir() . '/pdfed-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $manager = new WorkspaceManager(ProfileFactory::create($root));
        try {
            $manager->get(str_repeat('a', 32));
            self::fail('expected missing workspace');
        } catch (DocumentException) {
        }

        $pdf = $root . '/in.pdf';
        file_put_contents($pdf, '%PDF-1.4 test');
        $workspace = $manager->createFromUpload(new UploadedFile($pdf, 'My Doc.pdf', 'application/pdf', null, true));
        $this->expectException(DocumentException::class);
        $manager->replacePdf($workspace, $root . '/nope.pdf');
    }

    public function testGetWithoutMetaAndEmptyId(): void
    {
        $root = sys_get_temp_dir() . '/pdfed-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $id = str_repeat('cd', 16);
        mkdir($root . '/' . $id, 0700, true);
        file_put_contents($root . '/' . $id . '/current.pdf', '%PDF-1.4');
        $manager   = new WorkspaceManager(ProfileFactory::create($root));
        $workspace = $manager->get($id);
        self::assertSame('document.pdf', $workspace->originalName);

        $this->expectException(DocumentException::class);
        $manager->get('');
    }
}

/**
 * @internal
 */
final class MutableOwnerResolver implements WorkspaceOwnerResolverInterface
{
    public function __construct(
        public ?string $owner,
    ) {
    }

    public function currentOwner(): ?string
    {
        return $this->owner;
    }
}
