<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Engine;

use Nowo\PdfEditorBundle\Operation\EditorOperation;

interface PdfEngineInterface
{
    /**
     * @return array{engine: string, pymupdf: string, python: string}
     */
    public function version(): array;

    /**
     * @return array<string, mixed>
     */
    public function inspect(string $pdfPath): array;

    public function renderPage(string $pdfPath, int $page, string $outputPng, int $dpi): void;

    /**
     * @param list<EditorOperation> $operations
     *
     * @return array<string, mixed>
     */
    public function apply(string $pdfPath, array $operations, string $outputPdf): array;
}
