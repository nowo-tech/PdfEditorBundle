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
        /** Largest page preview in pixels (width × height); the engine lowers the dpi to fit (REQ-SEC-004 M1) */
        public int $maxRenderPixels = 25_000_000,
        /** Address-space cap (RLIMIT_AS) of each engine process in MiB; 0 = no cap */
        public int $engineMemoryLimit = 1024,
    ) {
    }
}
