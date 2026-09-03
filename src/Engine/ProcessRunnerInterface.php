<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Engine;

interface ProcessRunnerInterface
{
    /**
     * @param list<string> $command
     */
    public function run(array $command, float $timeout, float $idleTimeout): ProcessOutcome;
}
