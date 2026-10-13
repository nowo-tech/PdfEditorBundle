<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Document;

use InvalidArgumentException;
use Nowo\PdfEditorBundle\Config\EditorProfile;
use Nowo\PdfEditorBundle\Exception\DocumentException;
use Nowo\PdfEditorBundle\Security\WorkspaceOwnerResolverInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function bin2hex;
use function copy;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function fopen;
use function fread;
use function hash_equals;
use function is_dir;
use function is_file;
use function is_link;
use function is_string;
use function lstat;
use function max;
use function mkdir;
use function preg_match;
use function preg_replace;
use function random_bytes;
use function rename;
use function rmdir;
use function scandir;
use function sprintf;
use function str_contains;
use function strtolower;
use function time;
use function unlink;

final class WorkspaceManager
{
    private const OWNER_FILE = 'owner.txt';

    /**
     * @param WorkspaceOwnerResolverInterface|null $owners binds workspaces to their creator; null disables the check (direct instantiation / BC)
     */
    public function __construct(
        private readonly EditorProfile $profile,
        private readonly ?WorkspaceOwnerResolverInterface $owners = null,
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

        // The client extension proves nothing: require the PDF magic bytes before keeping the file.
        if (!self::hasPdfSignature($target)) {
            @unlink($target);
            @rmdir($directory);

            throw DocumentException::invalidUpload('The uploaded file is not a PDF document.');
        }

        $original = preg_replace('/[^A-Za-z0-9._-]+/', '_', $file->getClientOriginalName()) ?: 'document.pdf';
        file_put_contents($directory . '/meta.txt', $original);
        $this->writeOwner($directory);

        return new Workspace($id, $directory, $target, $original);
    }

    public function createFromPath(string $sourcePath, string $originalName = 'document.pdf'): Workspace
    {
        if (!is_file($sourcePath)) {
            throw DocumentException::invalidUpload(sprintf('Fixture PDF not found: "%s".', $sourcePath));
        }

        $id        = bin2hex(random_bytes(16));
        $directory = $this->profile->workspaceDir . '/' . $id;
        if (!@mkdir($directory, 0700, true) && !is_dir($directory)) { // @codeCoverageIgnoreStart
            throw DocumentException::invalidUpload(sprintf('Could not create workspace "%s".', $directory));
            // @codeCoverageIgnoreEnd
        }

        $target = $directory . '/current.pdf';
        if (!@copy($sourcePath, $target)) { // @codeCoverageIgnoreStart
            throw DocumentException::invalidUpload('The fixture PDF could not be copied.');
            // @codeCoverageIgnoreEnd
        }

        $original = preg_replace('/[^A-Za-z0-9._-]+/', '_', $originalName) ?: 'document.pdf';
        file_put_contents($directory . '/meta.txt', $original);
        $this->writeOwner($directory);

        return new Workspace($id, $directory, $target, $original);
    }

    public function get(string $id): Workspace
    {
        if ($id === '' || str_contains($id, '/') || str_contains($id, '\\') || str_contains($id, '..')) {
            throw DocumentException::notFound($id);
        }

        $directory = $this->profile->workspaceDir . '/' . $id;
        $pdf       = $directory . '/current.pdf';
        if (!is_file($pdf) || !$this->isOwnedByCurrentCaller($directory)) {
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

    private function writeOwner(string $directory): void
    {
        $owner = $this->owners?->currentOwner();
        if ($owner !== null) {
            file_put_contents($directory . '/' . self::OWNER_FILE, $owner);
        }
    }

    /**
     * Workspaces are bound to the user (or anonymous session) that created them; anyone else gets "not found".
     * A workspace without a stored owner (created from CLI or before owner binding existed) is only reachable by callers that have
     * no identity either.
     */
    private function isOwnedByCurrentCaller(string $directory): bool
    {
        if (!$this->owners instanceof WorkspaceOwnerResolverInterface) {
            return true;
        }

        $ownerFile = $directory . '/' . self::OWNER_FILE;
        $stored    = is_file($ownerFile) ? (string) file_get_contents($ownerFile) : null;
        $current   = $this->owners->currentOwner();

        if ($stored === null || $current === null) {
            return $stored === $current;
        }

        return hash_equals($stored, $current);
    }

    private static function hasPdfSignature(string $path): bool
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) { // @codeCoverageIgnoreStart
            return false;
            // @codeCoverageIgnoreEnd
        }
        $head = (string) fread($handle, 5);
        fclose($handle);

        return $head === '%PDF-';
    }

    /**
     * Deletes workspaces whose newest file is older than $maxAgeSeconds (workspace TTL).
     *
     * Only directories named like a workspace id (32 hex characters) directly under the workspace root are considered;
     * symbolic links are removed, never followed. Ownership is not checked: this is a maintenance operation.
     *
     * @return list<string> ids of the purged (or, with $dryRun, purgeable) workspaces
     */
    public function purgeOlderThan(int $maxAgeSeconds, bool $dryRun = false, ?int $now = null): array
    {
        if ($maxAgeSeconds < 1) {
            throw new InvalidArgumentException('The maximum workspace age must be at least one second.');
        }

        $root = $this->profile->workspaceDir;
        if (!is_dir($root)) {
            return [];
        }

        $cutoff = ($now ?? time()) - $maxAgeSeconds;
        $purged = [];
        foreach ((array) scandir($root) as $entry) {
            $directory = $root . '/' . $entry;
            if (!is_string($entry) || preg_match('/^[0-9a-f]{32}$/', $entry) !== 1 || is_link($directory) || !is_dir($directory)) {
                continue;
            }
            if (self::lastModified($directory) > $cutoff) {
                continue;
            }
            if (!$dryRun) {
                self::removeTree($directory);
            }
            $purged[] = $entry;
        }

        return $purged;
    }

    private static function lastModified(string $directory): int
    {
        $latest = (int) filemtime($directory);
        foreach ((array) scandir($directory) as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }
            $path   = $directory . '/' . $entry;
            $mtime  = is_link($path) ? (int) (lstat($path)['mtime'] ?? 0) : (int) filemtime($path);
            $latest = max($latest, $mtime);
            if (!is_link($path) && is_dir($path)) {
                $latest = max($latest, self::lastModified($path));
            }
        }

        return $latest;
    }

    private static function removeTree(string $directory): void
    {
        foreach ((array) scandir($directory) as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (!is_link($path) && is_dir($path)) {
                self::removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }

    public function ensureWorkspaceDir(): void
    {
        if (!is_dir($this->profile->workspaceDir) && !@mkdir($this->profile->workspaceDir, 0700, true) && !is_dir($this->profile->workspaceDir)) { // @codeCoverageIgnoreStart
            throw DocumentException::invalidUpload(sprintf('Could not create workspace root "%s".', $this->profile->workspaceDir));
            // @codeCoverageIgnoreEnd
        }
    }
}
