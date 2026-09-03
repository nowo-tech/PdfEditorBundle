<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Security;

interface PdfEditorAccessCheckerInterface
{
    public function canUseEditor(?object $user): bool;
}
