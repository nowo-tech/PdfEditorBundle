<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Engine;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class SymfonyProcessRunner implements ProcessRunnerInterface
{
    /**
     * @param list<string> $command
     */
    public function run(array $command, float $timeout, float $idleTimeout): ProcessOutcome
    {
        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->setIdleTimeout($idleTimeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            if ($process->isRunning()) { // @codeCoverageIgnore
                $process->stop(0); // @codeCoverageIgnore
            }

            return new ProcessOutcome(
                $process->getExitCode() ?? 124, // @codeCoverageIgnore
                $process->getOutput(),
                $process->getErrorOutput(),
                true,
            );
        }

        return new ProcessOutcome(
            $process->getExitCode() ?? 1,
            $process->getOutput(),
            $process->getErrorOutput(),
            false,
        );
    }
}
