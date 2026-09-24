<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Command;

use Nowo\PdfEditorBundle\Config\EditorProfile;
use Nowo\PdfEditorBundle\Engine\PdfEngineInterface;
use Nowo\PdfEditorBundle\Exception\EngineException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_file;
use function sprintf;

#[AsCommand(name: 'nowo:pdf-editor:check-engine', description: 'Verify the Python PDF engine (PyMuPDF) is available')]
final class CheckEngineCommand extends Command
{
    public function __construct(
        private readonly PdfEngineInterface $engine,
        private readonly EditorProfile $profile,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!is_file($this->profile->engineScript)) {
            throw EngineException::missingEngineScript($this->profile->engineScript);
        }

        $version = $this->engine->version();
        $io->success(sprintf(
            'Engine %s (PyMuPDF %s, Python %s)',
            $version['engine'],
            $version['pymupdf'],
            $version['python'],
        ));

        return Command::SUCCESS;
    }
}
