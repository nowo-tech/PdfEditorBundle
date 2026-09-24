<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Exception;

use function sprintf;

final class DocumentException extends PdfEditorException
{
    public static function notFound(string $id): self
    {
        return new self(sprintf('PDF workspace "%s" was not found.', $id));
    }

    public static function invalidUpload(string $reason): self
    {
        return new self($reason);
    }

    public static function tooLarge(int $bytes): self
    {
        return new self(sprintf('The PDF exceeds the maximum size of %d bytes.', $bytes));
    }
}
