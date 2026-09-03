<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Operation;

final readonly class EditorOperation
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $op,
        public array $payload,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['op' => $this->op] + $this->payload;
    }
}
