<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Document;

use Nowo\PdfEditorBundle\Config\EditorProfile;
use Nowo\PdfEditorBundle\Exception\DocumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function bin2hex;
use function copy;
use function file_exists;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function preg_replace;
use function random_bytes;
use function rename;
use function sprintf;
use function str_contains;
use function unlink;

final class WorkspaceManager
{
    public function __construct(
        private readonly EditorProfile $profile,
    ) {
    }

    public function createFromUpload(UploadedFile $file): Workspace
    {
        if (!$file->isValid()) {
            throw DocumentException::invalidUpload($file->getErrorMessage());
        }

        $size = $file->getSize();
        if ($size !== false && $size > $this->profile->maxUploadBytes) {
            throw DocumentException::tooLarge($this->profile->maxUploadBytes);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'pdf') {
            throw DocumentException::invalidUpload('Only PDF files are accepted.');
        }

        $id        = bin2hex(random_bytes(16));
        $directory = $this->profile->workspaceDir . '/' . $id;
        if (!@mkdir($directory, 0700, true) && !is_dir($directory)) { // @codeCoverageIgnoreStart
            throw DocumentException::invalidUpload(sprintf('Could not create workspace "%s".', $directory));
            // @codeCoverageIgnoreEnd
        }

        $target = $directory . '/current.pdf';
        $file->move($directory, 'current.pdf');
        if (!is_file($target)) { // @codeCoverageIgnoreStart
            throw DocumentException::invalidUpload('The uploaded PDF could not be stored.');
            // @codeCoverageIgnoreEnd
        }

        $original = preg_replace('/[^A-Za-z0-9._-]+/', '_', $file->getClientOriginalName()) ?: 'document.pdf';
        file_put_contents($directory . '/meta.txt', $original);

        return new Workspace($id, $directory, $target, $original);
    }

    public function createFromPath(string $sourcePath, string $originalName = 'document.pdf'): Workspace
    {
        if (!is_file($sourcePath)) {
            throw DocumentException::invalidUpload(sprintf('Fixture PDF not found: "%s".', $sourcePath));
        }

        $id        = bin2hex(random_bytes(16));
        $directory = $this->profile->workspaceDir . '/' . $id;
        if (!@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw DocumentException::invalidUpload(sprintf('Could not create workspace "%s".', $directory));
        }

        $target = $directory . '/current.pdf';
        if (!@copy($sourcePath, $target)) {
            throw DocumentException::invalidUpload('The fixture PDF could not be copied.');
        }

        $original = preg_replace('/[^A-Za-z0-9._-]+/', '_', $originalName) ?: 'document.pdf';
        file_put_contents($directory . '/meta.txt', $original);

        return new Workspace($id, $directory, $target, $original);
    }

    public function get(string $id): Workspace
    {
        if ($id === '' || str_contains($id, '/') || str_contains($id, '\\') || str_contains($id, '..')) {
            throw DocumentException::notFound($id);
        }

        $directory = $this->profile->workspaceDir . '/' . $id;
        $pdf       = $directory . '/current.pdf';
        if (!is_file($pdf)) {
            throw DocumentException::notFound($id);
        }

        $original = is_file($directory . '/meta.txt') ? (string) file_get_contents($directory . '/meta.txt') : 'document.pdf';

        return new Workspace($id, $directory, $pdf, $original);
    }

    public function replacePdf(Workspace $workspace, string $newPdfPath): void
    {
        if (!is_file($newPdfPath)) {
            throw DocumentException::invalidUpload('Replacement PDF is missing.');
        }

        $tmp = $workspace->pdfPath . '.tmp';
        if (!@rename($newPdfPath, $tmp) && !@copy($newPdfPath, $tmp)) { // @codeCoverageIgnoreStart
            throw DocumentException::invalidUpload('Could not replace the working PDF.');
            // @codeCoverageIgnoreEnd
        }
        if (!@rename($tmp, $workspace->pdfPath)) { // @codeCoverageIgnoreStart
            @unlink($tmp);
            throw DocumentException::invalidUpload('Could not activate the working PDF.');
            // @codeCoverageIgnoreEnd
        }
    }

    public function pageImagePath(Workspace $workspace, int $page): string
    {
        return sprintf('%s/page-%d.png', $workspace->directory, $page);
    }

    public function ensureWorkspaceDir(): void
    {
        if (!is_dir($this->profile->workspaceDir) && !@mkdir($this->profile->workspaceDir, 0700, true) && !is_dir($this->profile->workspaceDir)) { // @codeCoverageIgnoreStart
            throw DocumentException::invalidUpload(sprintf('Could not create workspace root "%s".', $this->profile->workspaceDir));
            // @codeCoverageIgnoreEnd
        }
    }
}
<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Document;

use Nowo\PdfEditorBundle\Config\EditorProfile;
use Nowo\PdfEditorBundle\Exception\DocumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function bin2hex;
use function copy;
use function file_exists;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function preg_replace;
use function random_bytes;
use function rename;
use function sprintf;
use function str_contains;
use function unlink;

final class WorkspaceManager
{
    public function __construct(
        private readonly EditorProfile $profile,
    ) {
    }

    public function createFromUpload(UploadedFile $file): Workspace
    {
        if (!$file->isValid()) {
            throw DocumentException::invalidUpload($file->getErrorMessage());
        }

        $size = $file->getSize();
        if ($size !== false && $size > $this->profile->maxUploadBytes) {
            throw DocumentException::tooLarge($this->profile->maxUploadBytes);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'pdf') {
            throw DocumentException::invalidUpload('Only PDF files are accepted.');
        }

        $id        = bin2hex(random_bytes(16));
        $directory = $this->profile->workspaceDir . '/' . $id;
        if (!@mkdir($directory, 0700, true) && !is_dir($directory)) { // @codeCoverageIgnoreStart
            throw DocumentException::invalidUpload(sprintf('Could not create workspace "%s".', $directory));
            // @codeCoverageIgnoreEnd
        }

        $target = $directory . '/current.pdf';
        $file->move($directory, 'current.pdf');
        if (!is_file($target)) { // @codeCoverageIgnoreStart
            throw DocumentException::invalidUpload('The uploaded PDF could not be stored.');
            // @codeCoverageIgnoreEnd
        }

        $original = preg_replace('/[^A-Za-z0-9._-]+/', '_', $file->getClientOriginalName()) ?: 'document.pdf';
        file_put_contents($directory . '/meta.txt', $original);

        return new Workspace($id, $directory, $target, $original);
    }

    public function createFromPath(string $sourcePath, string $originalName = 'document.pdf'): Workspace
    {
        if (!is_file($sourcePath)) {
            throw DocumentException::invalidUpload(sprintf('Fixture PDF not found: "%s".', $sourcePath));
        }

        $id        = bin2hex(random_bytes(16));
        $directory = $this->profile->workspaceDir . '/' . $id;
        if (!@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw DocumentException::invalidUpload(sprintf('Could not create workspace "%s".', $directory));
        }

        $target = $directory . '/current.pdf';
        if (!@copy($sourcePath, $target)) {
            throw DocumentException::invalidUpload('The fixture PDF could not be copied.');
        }

        $original = preg_replace('/[^A-Za-z0-9._-]+/', '_', $originalName) ?: 'document.pdf';
        file_put_contents($directory . '/meta.txt', $original);

        return new Workspace($id, $directory, $target, $original);
    }

    public function get(string $id): Workspace
    {
        if ($id === '' || str_contains($id, '/') || str_contains($id, '\\') || str_contains($id, '..')) {
            throw DocumentException::notFound($id);
        }

        $directory = $this->profile->workspaceDir . '/' . $id;
        $pdf       = $directory . '/current.pdf';
        if (!is_file($pdf)) {
            throw DocumentException::notFound($id);
        }

        $original = is_file($directory . '/meta.txt') ? (string) file_get_contents($directory . '/meta.txt') : 'document.pdf';

        return new Workspace($id, $directory, $pdf, $original);
    }

    public function replacePdf(Workspace $workspace, string $newPdfPath): void
    {
        if (!is_file($newPdfPath)) {
            throw DocumentException::invalidUpload('Replacement PDF is missing.');
        }

        $tmp = $workspace->pdfPath . '.tmp';
        if (!@rename($newPdfPath, $tmp) && !@copy($newPdfPath, $tmp)) { // @codeCoverageIgnoreStart
            throw DocumentException::invalidUpload('Could not replace the working PDF.');
            // @codeCoverageIgnoreEnd
        }
        if (!@rename($tmp, $workspace->pdfPath)) { // @codeCoverageIgnoreStart
            @unlink($tmp);
            throw DocumentException::invalidUpload('Could not activate the working PDF.');
            // @codeCoverageIgnoreEnd
        }
    }

    public function pageImagePath(Workspace $workspace, int $page): string
    {
        return sprintf('%s/page-%d.png', $workspace->directory, $page);
    }

    public function ensureWorkspaceDir(): void
    {
        if (!is_dir($this->profile->workspaceDir) && !@mkdir($this->profile->workspaceDir, 0700, true) && !is_dir($this->profile->workspaceDir)) { // @codeCoverageIgnoreStart
            throw DocumentException::invalidUpload(sprintf('Could not create workspace root "%s".', $this->profile->workspaceDir));
            // @codeCoverageIgnoreEnd
        }
    }
}
