<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Exception;

use function preg_replace;
use function sprintf;
use function strlen;
use function strtok;
use function substr;
use function trim;

final class EngineException extends PdfEditorException
{
    public static function missingPython(string $binary): self
    {
        return new self(sprintf('Python interpreter not found: %s', $binary));
    }

    public static function missingEngineScript(string $path): self
    {
        return new self(sprintf('PDF engine script not found: %s', $path));
    }

    public static function timedOut(float $seconds): self
    {
        return new self(sprintf('PDF engine timed out after %.1f seconds.', $seconds));
    }

    /**
     * The detail is the engine's one-line error: absolute paths are replaced by "[path]" and only the first line is
     * kept, so server paths and tracebacks never reach a rendered exception message (REQ-SEC-004 I5).
     */
    public static function failed(string $detail): self
    {
        return new self(sprintf('PDF engine failed: %s', self::redact($detail)));
    }

    public static function redact(string $detail): string
    {
        $line = trim(strtok($detail, "\r\n") ?: '');
        $line = (string) preg_replace('#(?<![\w.:/])(?:[A-Za-z]:)?[/\\\\][^\s\'"]*#', '[path]', $line);

        return strlen($line) > 300 ? substr($line, 0, 300) . '...' : $line;
    }

    public static function invalidJson(string $detail): self
    {
        return new self(sprintf('PDF engine returned invalid JSON: %s', $detail));
    }
}
