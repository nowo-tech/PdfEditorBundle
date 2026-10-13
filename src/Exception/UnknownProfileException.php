<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Exception;

use function sprintf;

/**
 * @deprecated since 1.2, never thrown (the configuration already rejects an unknown default_profile); will be removed in 2.0
 */
final class UnknownProfileException extends PdfEditorException
{
    public static function forProfile(string $name): self
    {
        return new self(sprintf('Unknown PDF editor profile "%s".', $name));
    }
}
