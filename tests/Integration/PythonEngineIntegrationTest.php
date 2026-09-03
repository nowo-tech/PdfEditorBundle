<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Integration;

use Nowo\PdfEditorBundle\Engine\PythonPdfEngine;
use Nowo\PdfEditorBundle\Engine\SymfonyProcessRunner;
use Nowo\PdfEditorBundle\Operation\EditorOperation;
use Nowo\PdfEditorBundle\Tests\ProfileFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PythonPdfEngine::class)]
#[CoversClass(SymfonyProcessRunner::class)]
final class PythonEngineIntegrationTest extends TestCase
{
    public function testVersionInspectRenderAndApply(): void
    {
        $script = dirname(__DIR__, 2) . '/engine/pdf_editor_engine.py';
        if (!is_file($script)) {
            self::markTestSkipped('Engine script is missing.');
        }

        $probe = @shell_exec('python3 -c "import pymupdf; print(1)" 2>/dev/null');
        if (!str_contains((string) $probe, '1')) {
            self::markTestSkipped('Python 3 + PyMuPDF is not available.');
        }

        $dir = sys_get_temp_dir() . '/pdfed-int-' . bin2hex(random_bytes(4));
        mkdir($dir, 0700, true);
        $pdf = $dir . '/in.pdf';
        $make = 'import pymupdf; d=pymupdf.open(); p=d.new_page(); p.insert_text((72,72),"Hello"); d.save(r"' . $pdf . '")';
        $status = 0;
        $output = [];
        exec('python3 -c ' . escapeshellarg($make), $output, $status);
        self::assertSame(0, $status, implode("\n", $output));

        $engine = new PythonPdfEngine(new SymfonyProcessRunner(), ProfileFactory::create($dir, $script));
        $version = $engine->version();
        self::assertSame('pymupdf', $version['engine']);
        self::assertNotSame('', $version['pymupdf']);

        $inspect = $engine->inspect($pdf);
        self::assertSame(1, $inspect['pageCount']);

        $png = $dir . '/page.png';
        $engine->renderPage($pdf, 1, $png, 72);
        self::assertFileExists($png);

        $out = $dir . '/out.pdf';
        $result = $engine->apply($pdf, [
            new EditorOperation('insert_text', ['page' => 1, 'x' => 72, 'y' => 120, 'text' => 'Note', 'fontSize' => 12]),
        ], $out);
        self::assertSame(1, $result['applied']);
        self::assertFileExists($out);
    }
}
