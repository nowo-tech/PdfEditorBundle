<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Command;

use Nowo\PdfEditorBundle\Document\WorkspaceManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function is_string;
use function preg_match;
use function sprintf;
use function strtolower;
use function trim;

/**
 * Workspace TTL: removes editor workspaces (uploaded PDF, previews, metadata) that were not touched for a while.
 * Schedule it (cron, Symfony Scheduler) so abandoned uploads do not pile up in workspace_dir.
 */
#[AsCommand(name: 'nowo:pdf-editor:purge', description: 'Delete PDF editor workspaces older than a given age')]
final class PurgeWorkspacesCommand extends Command
{
    private const UNITS = ['' => 1, 's' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400, 'w' => 604800];

    public function __construct(private readonly WorkspaceManager $workspaces)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'Minimum age since the last change: seconds or a number with s, m, h, d or w (e.g. 90m, 24h, 7d)', '24h')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the workspaces that would be deleted without deleting them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $raw    = $input->getOption('older-than');
        $maxAge = is_string($raw) ? self::parseDuration($raw) : null;
        $dryRun = (bool) $input->getOption('dry-run');

        if ($maxAge === null) {
            $io->error('Invalid --older-than value: use a positive number of seconds or a number followed by s, m, h, d or w (e.g. 24h).');

            return Command::INVALID;
        }

        $ids = $this->workspaces->purgeOlderThan($maxAge, $dryRun);
        if ($io->isVerbose() || $dryRun) {
            $io->listing($ids);
        }

        $io->success(sprintf(
            $dryRun ? '%d workspace(s) older than %d seconds would be deleted (dry run).' : '%d workspace(s) older than %d seconds deleted.',
            count($ids),
            $maxAge,
        ));

        return Command::SUCCESS;
    }

    private static function parseDuration(string $value): ?int
    {
        if (preg_match('/^(\d+)\s*([smhdw]?)$/', strtolower(trim($value)), $m) !== 1) {
            return null;
        }

        $seconds = (int) $m[1] * self::UNITS[$m[2]];

        return $seconds > 0 ? $seconds : null;
    }
}
