<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Engine;

use Nowo\PdfEditorBundle\Engine\SymfonyProcessRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SymfonyProcessRunner::class)]
final class SymfonyProcessRunnerTest extends TestCase
{
    public function testSuccessfulCommand(): void
    {
        $result = (new SymfonyProcessRunner())->run([PHP_BINARY, '-r', 'echo "ok";'], 5.0, 5.0);
        self::assertTrue($result->isSuccessful());
        self::assertSame('ok', $result->stdout);
    }

    public function testTimedOutCommand(): void
    {
        $result = (new SymfonyProcessRunner())->run([PHP_BINARY, '-r', 'sleep(3);'], 0.2, 0.2);
        self::assertTrue($result->timedOut);
        self::assertFalse($result->isSuccessful());
    }
}
