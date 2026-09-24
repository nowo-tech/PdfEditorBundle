<?php

declare(strict_types=1);

namespace App\Demo;

use Nowo\PdfEditorBundle\Exception\DocumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Resolves demo PDF fixtures across Docker mounts and local demo/fixtures. */
final class FixturesLocator
{
    public function __construct(
        private readonly string $projectDir,
        private readonly string $configuredDir = '',
    ) {
    }

    public function directory(): string
    {
        if ($this->configuredDir !== '') {
            $resolved = realpath($this->normalize($this->configuredDir));
            if ($resolved !== false && is_dir($resolved)) {
                return $resolved;
            }
        }

        foreach ($this->candidateDirectories() as $candidate) {
            $resolved = realpath($this->normalize($candidate));
            if ($resolved !== false && is_dir($resolved)) {
                return $resolved;
            }
        }

        throw new NotFoundHttpException(
            'Demo fixtures not found. Run: node demo/scripts/generate-fixtures.mjs (from the bundle root), then recreate the demo container.',
        );
    }

    public function resolveFile(string $filename): string
    {
        $basename = basename($filename);
        if ($basename === '' || $basename !== $filename || str_contains($basename, '..')) {
            throw new NotFoundHttpException(sprintf('Invalid fixture filename "%s".', $filename));
        }

        $path = $this->directory() . '/' . $basename;
        if (!is_file($path)) {
            throw DocumentException::invalidUpload(sprintf('Fixture PDF not found: "%s".', $path));
        }

        return $path;
    }

    /** @return list<string> */
    private function candidateDirectories(): array
    {
        return [
            $this->projectDir . '/fixtures',
            $this->projectDir . '/demo-fixtures',
            '/var/pdf-editor-bundle/demo/fixtures',
            $this->projectDir . '/../fixtures',
        ];
    }

    private function normalize(string $path): string
    {
        return (string) preg_replace('#/+#', '/', $path);
    }
}
