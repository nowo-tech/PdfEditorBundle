<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Config;

/**
 * Immutable named profile (REQ-CFG-001).
 */
final readonly class EditorProfile
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $name,
        public string $pythonBinary,
        public string $engineScript,
        public float $timeout,
        public float $idleTimeout,
        public int $maxUploadBytes,
        public int $renderDpi,
        public string $workspaceDir,
        public string $engineMode,
        public bool $allowUnauthenticated,
        public array $roles,
    ) {
    }
}
