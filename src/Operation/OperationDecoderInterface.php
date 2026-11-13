<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Operation;

use Nowo\PdfEditorBundle\Exception\PdfEditorException;

/**
 * Port: turns the operation list submitted by the editor UI into validated {@see EditorOperation} value objects.
 *
 * The bundle ships {@see OperationDecoder} and aliases this interface to it. A host that adds operations to its own
 * engine replaces the alias with a decoder that accepts them; the controller depends on this port only.
 */
interface OperationDecoderInterface
{
    /**
     * Validates and decodes raw operations (a JSON-decoded list).
     *
     * @param list<mixed> $raw Raw operations, each an object with an "op" string
     *
     * @return list<EditorOperation>
     *
     * @throws PdfEditorException When an operation is malformed, unsupported or the list is too long
     */
    public function decode(array $raw): array;

    /**
     * Resolves `add_image` relative paths to absolute paths under the workspace directory (and refuses escapes).
     *
     * @param list<EditorOperation> $ops          Decoded operations
     * @param string                $workspaceDir Local workspace directory
     *
     * @return list<EditorOperation>
     *
     * @throws PdfEditorException When a path leaves the workspace
     */
    public function bindAddImagePathsToWorkspace(array $ops, string $workspaceDir): array;
}
