<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Operation;

use Nowo\PdfEditorBundle\Exception\PdfEditorException;

use function basename;
use function dirname;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function realpath;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;

use const DIRECTORY_SEPARATOR;

final class OperationDecoder
{
    private const OPS = [
        'replace_text',
        'insert_text',
        'delete_text',
        'add_image',
        'delete_image',
        'add_acroform_field',
        'update_acroform_field',
        'delete_acroform_field',
        'add_watermark',
        'remove_watermark',
        'remove_detected_watermarks',
        'rotate_page',
        'delete_page',
        'insert_blank_page',
        'reorder_pages',
        'add_annotation',
    ];

    /**
     * @param list<mixed> $raw
     *
     * @return list<EditorOperation>
     */
    public function decode(array $raw): array
    {
        $ops = [];
        foreach ($raw as $item) {
            if (!is_array($item) || !is_string($item['op'] ?? null)) {
                throw new PdfEditorException('Each operation must be an object with an "op" string.');
            }
            $op = $item['op'];
            if (!in_array($op, self::OPS, true)) {
                throw new PdfEditorException(sprintf('Unsupported operation "%s".', $op));
            }
            unset($item['op']);
            if ($op === 'add_image') {
                $item = $this->assertSafeAddImagePayload($item);
            }
            $ops[] = new EditorOperation($op, $item);
        }

        return $ops;
    }

    /**
     * Resolve add_image relative paths to absolute paths under the workspace.
     *
     * Expects paths already validated by {@see assertSafeAddImagePayload()}.
     *
     * @param list<EditorOperation> $ops
     *
     * @return list<EditorOperation>
     */
    public function bindAddImagePathsToWorkspace(array $ops, string $workspaceDir): array
    {
        $root = realpath($workspaceDir);
        if ($root === false) {
            throw new PdfEditorException('Workspace directory does not exist.');
        }
        $rootPrefix = $root . DIRECTORY_SEPARATOR;

        $bound = [];
        foreach ($ops as $op) {
            if ($op->op !== 'add_image') {
                $bound[] = $op;
                continue;
            }

            $rel = $op->payload['path'] ?? null;
            if (!is_string($rel) || $rel === '') {
                throw new PdfEditorException('add_image requires a non-empty "path" string.');
            }
            if (str_contains($rel, '..') || str_contains($rel, "\0")) {
                throw new PdfEditorException('add_image path must not contain ".." or null bytes.');
            }

            $candidate = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
            $resolved  = realpath($candidate);

            if ($resolved !== false) {
                if ($resolved !== $root && !str_starts_with($resolved, $rootPrefix)) {
                    throw new PdfEditorException('add_image path escapes the workspace.');
                }
                $absolute = $resolved;
            } else {
                $dirReal = realpath(dirname($candidate));
                if ($dirReal === false || ($dirReal !== $root && !str_starts_with($dirReal, $rootPrefix))) {
                    throw new PdfEditorException('add_image path escapes the workspace.');
                }
                $absolute = $dirReal . DIRECTORY_SEPARATOR . basename($candidate);
                if ($absolute !== $root && !str_starts_with($absolute, $rootPrefix)) {
                    throw new PdfEditorException('add_image path escapes the workspace.');
                }
            }

            $payload         = $op->payload;
            $payload['path'] = $absolute;
            $bound[]         = new EditorOperation($op->op, $payload);
        }

        return $bound;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function assertSafeAddImagePayload(array $payload): array
    {
        $path = $payload['path'] ?? null;
        if (!is_string($path) || $path === '') {
            throw new PdfEditorException('add_image requires a non-empty "path" string.');
        }

        // Reject absolute paths, URI schemes, and traversal — only workspace-relative basenames/paths.
        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|\\\\|/|[a-zA-Z]:[\\\\/])#i', $path) === 1) {
            throw new PdfEditorException('add_image path must be a relative path inside the workspace (no absolute paths or URIs).');
        }
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            throw new PdfEditorException('add_image path must not contain ".." or null bytes.');
        }

        return $payload;
    }
}
