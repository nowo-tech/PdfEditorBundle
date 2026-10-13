<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Security;

/**
 * Identifies who owns a PDF workspace so one user (or anonymous browser session) cannot open another's.
 */
interface WorkspaceOwnerResolverInterface
{
    /**
     * Opaque owner key for the current caller, or null when no user and no session is available (CLI, stateless).
     */
    public function currentOwner(): ?string;
}
