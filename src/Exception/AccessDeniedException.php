<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Exception;

final class AccessDeniedException extends PdfEditorException
{
    public static function workspace(): self
    {
        return new self('You are not allowed to use the PDF editor.');
    }
}
<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Exception;

final class AccessDeniedException extends PdfEditorException
{
    public static function workspace(): self
    {
        return new self('You are not allowed to use the PDF editor.');
    }
}
