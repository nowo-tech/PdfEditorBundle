<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Engine;

use Nowo\PdfEditorBundle\Config\EditorProfile;
use Nowo\PdfEditorBundle\Exception\EngineException;
use Nowo\PdfEditorBundle\Operation\EditorOperation;

use function is_array;
use function is_file;
use function json_decode;
use function json_encode;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_THROW_ON_ERROR;

final class PythonPdfEngine implements PdfEngineInterface
{
    public function __construct(
        private readonly ProcessRunnerInterface $processRunner,
        private readonly EditorProfile $profile,
    ) {
    }

    public function version(): array
    {
        $payload = $this->run(['version']);

        return [
            'engine'  => (string) ($payload['engine'] ?? 'pymupdf'),
            'pymupdf' => (string) ($payload['pymupdf'] ?? ''),
            'python'  => (string) ($payload['python'] ?? ''),
        ];
    }

    public function inspect(string $pdfPath): array
    {
        return $this->run(['inspect', '--pdf', $pdfPath]);
    }

    public function renderPage(string $pdfPath, int $page, string $outputPng, int $dpi): void
    {
        $this->run([
            'render',
            '--pdf',
            $pdfPath,
            '--page',
            (string) $page,
            '--out',
            $outputPng,
            '--dpi',
            (string) $dpi,
        ]);
    }

    /**
     * @param list<EditorOperation> $operations
     */
    public function apply(string $pdfPath, array $operations, string $outputPdf): array
    {
        $opsFile = tempnam(sys_get_temp_dir(), 'pdfed-ops-');
        if ($opsFile === false) { // @codeCoverageIgnoreStart
            throw EngineException::failed('Could not create a temporary operations file.');
        } // @codeCoverageIgnoreEnd

        try {
            $json = json_encode(['ops' => array_map(static fn (EditorOperation $op): array => $op->toArray(), $operations)], JSON_THROW_ON_ERROR);
            if (file_put_contents($opsFile, $json) === false) { // @codeCoverageIgnoreStart
                throw EngineException::failed('Could not write operations JSON.');
            } // @codeCoverageIgnoreEnd

            return $this->run([
                'apply',
                '--pdf',
                $pdfPath,
                '--ops',
                $opsFile,
                '--out',
                $outputPdf,
            ]);
        } finally {
            @unlink($opsFile);
        }
    }

    /**
     * @param list<string> $args
     *
     * @return array<string, mixed>
     */
    private function run(array $args): array
    {
        if (!is_file($this->profile->engineScript)) {
            throw EngineException::missingEngineScript($this->profile->engineScript);
        }

        $command = array_merge([$this->profile->pythonBinary, $this->profile->engineScript], $args);
        $outcome = $this->processRunner->run($command, $this->profile->timeout, $this->profile->idleTimeout);

        if ($outcome->timedOut) {
            throw EngineException::timedOut($this->profile->timeout);
        }

        $raw = $outcome->stdout !== '' ? $outcome->stdout : $outcome->stderr;
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            if (!$outcome->isSuccessful()) {
                throw EngineException::failed(trim($outcome->stderr !== '' ? $outcome->stderr : $outcome->stdout) ?: sprintf('exit %d', $outcome->exitCode));
            }

            throw EngineException::invalidJson($exception->getMessage());
        }

        if (!is_array($decoded)) {
            throw EngineException::invalidJson('JSON root is not an object.');
        }

        if (($decoded['ok'] ?? false) !== true) {
            $error = (string) ($decoded['error'] ?? trim($outcome->stderr));
            throw EngineException::failed($error !== '' ? $error : sprintf('exit %d', $outcome->exitCode));
        }

        if (!$outcome->isSuccessful()) {
            throw EngineException::failed(trim($outcome->stderr) !== '' ? $outcome->stderr : sprintf('exit %d', $outcome->exitCode));
        }

        unset($decoded['ok']);

        return $decoded;
    }
}
