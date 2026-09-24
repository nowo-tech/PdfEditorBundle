<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Document;

use Nowo\PdfEditorBundle\Config\EditorProfile;
use Nowo\PdfEditorBundle\Document\Workspace;
use Nowo\PdfEditorBundle\Document\WorkspaceManager;
use Nowo\PdfEditorBundle\Exception\DocumentException;
use Nowo\PdfEditorBundle\Tests\ProfileFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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
