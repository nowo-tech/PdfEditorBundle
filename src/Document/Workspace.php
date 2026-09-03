<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Document;

final readonly class Workspace
{
    public function __construct(
        public string $id,
        public string $directory,
        public string $pdfPath,
        public string $originalName,
    ) {
    }
}
