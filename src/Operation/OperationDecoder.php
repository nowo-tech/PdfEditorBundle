<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Operation;

use Nowo\PdfEditorBundle\Exception\PdfEditorException;

use function in_array;
use function is_array;
use function is_string;

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
            $ops[] = new EditorOperation($op, $item);
        }

        return $ops;
    }
}
