<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Security;

/**
 * Demo/dev checker used when security.allow_unauthenticated is true.
 */
final class AllowAllPdfEditorAccessChecker implements PdfEditorAccessCheckerInterface
{
    public function canUseEditor(?object $user): bool
    {
        return true;
    }
}
