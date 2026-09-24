<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Exception;

use function sprintf;

final class UnknownProfileException extends PdfEditorException
{
    public static function forProfile(string $name): self
    {
        return new self(sprintf('Unknown PDF editor profile "%s".', $name));
    }
}
