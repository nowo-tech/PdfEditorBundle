<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Engine;

final readonly class ProcessOutcome
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
        public bool $timedOut,
    ) {
    }

    public function isSuccessful(): bool
    {
        return !$this->timedOut && $this->exitCode === 0;
    }
}
<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Engine;

final readonly class ProcessOutcome
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
        public bool $timedOut,
    ) {
    }

    public function isSuccessful(): bool
    {
        return !$this->timedOut && $this->exitCode === 0;
    }
}
