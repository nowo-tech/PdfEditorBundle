<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Document;

use Nowo\PdfEditorBundle\Exception\DocumentException;
use Nowo\PdfEditorBundle\Security\WorkspaceOwnerResolverInterface;

/**
 * Port: where editor workspaces (the working PDF, its page images and metadata) live and who may open them.
 *
 * The bundle ships {@see WorkspaceManager} (a private directory per workspace under the profile's `workspace_dir`, bound
 * to the caller by {@see WorkspaceOwnerResolverInterface}) and aliases this interface
 * to it. The controller and the purge command depend on this port only, so a host can keep workspaces elsewhere
 * (another volume, an object store mirrored to a local path) by replacing the alias.
 *
 * Every method takes and returns scalars or the {@see Workspace} value object: no HTTP or Symfony type appears here.
 * The engine reads and writes real files, so a workspace always resolves to a local directory and PDF path.
 */
interface WorkspaceStorageInterface
{
    /**
     * Creates the workspace root, refusing one another account owns or that is group / world writable.
     *
     * @throws DocumentException When the root cannot be created or is unsafe
     */
    public function ensureWorkspaceDir(): void;

    /**
     * Creates a workspace from an uploaded file that is already on the local disk.
     *
     * The path MUST be a validated upload temp file (for example the temporary file of a PHP upload that passed
     * `is_uploaded_file()` / `UploadedFile::isValid()`), because it is MOVED into the workspace (copied and deleted
     * when it cannot be moved). Never pass a path an end user chooses. Symlinks and non-regular files are refused.
     * Before anything is moved, the real file size (the larger of it and the declared size) is checked against the
     * profile's limit, then the `.pdf` extension and the `%PDF-` magic bytes; a refused file is left untouched.
     * The workspace is bound to the current caller.
     *
     * @param string   $path         Local path of the validated upload temp file (moved)
     * @param string   $originalName Client-side file name (only the extension and a sanitised copy are kept)
     * @param int|null $size         Declared size in bytes when known; the real size is always measured too
     *
     * @throws DocumentException When the file is not an acceptable PDF
     */
    public function createFromUploadedFile(string $path, string $originalName, ?int $size = null): Workspace;

    /**
     * Creates a workspace from a PDF already on disk (copied; fixtures, imports).
     *
     * @param string $sourcePath   Local path of the PDF
     * @param string $originalName Display name
     *
     * @throws DocumentException When the file is missing or cannot be copied
     */
    public function createFromPath(string $sourcePath, string $originalName = 'document.pdf'): Workspace;

    /**
     * Opens a workspace owned by the current caller.
     *
     * @throws DocumentException When it does not exist or belongs to someone else
     */
    public function get(string $id): Workspace;

    /**
     * Replaces the working PDF atomically with the file at the given local path.
     *
     * @throws DocumentException When the replacement is missing or cannot be activated
     */
    public function replacePdf(Workspace $workspace, string $newPdfPath): void;

    /**
     * Local path of the cached PNG of a page.
     */
    public function pageImagePath(Workspace $workspace, int $page): string;

    /**
     * Cached page count of the current PDF; null when unknown or stale.
     */
    public function cachedPageCount(Workspace $workspace): ?int;

    /**
     * Stores the page count of the current PDF.
     */
    public function storePageCount(Workspace $workspace, int $pageCount): void;

    /**
     * Deletes workspaces whose newest file is older than the given age.
     *
     * @param int      $maxAgeSeconds Maximum age in seconds (at least 1)
     * @param bool     $dryRun        When true, only reports what would be purged
     * @param int|null $now           Reference timestamp (clock when null)
     *
     * @return list<string> Ids of the purged (or purgeable) workspaces
     */
    public function purgeOlderThan(int $maxAgeSeconds, bool $dryRun = false, ?int $now = null): array;
}
