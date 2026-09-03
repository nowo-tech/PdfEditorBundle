<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Exception;

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

    public static function failed(string $detail): self
    {
        return new self(sprintf('PDF engine failed: %s', $detail));
    }

    public static function invalidJson(string $detail): self
    {
        return new self(sprintf('PDF engine returned invalid JSON: %s', $detail));
    }
}
