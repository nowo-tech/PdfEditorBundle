<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Exception;

use Nowo\PdfEditorBundle\Exception\AccessDeniedException;
use Nowo\PdfEditorBundle\Exception\DocumentException;
use Nowo\PdfEditorBundle\Exception\EngineException;
use Nowo\PdfEditorBundle\Exception\UnknownProfileException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccessDeniedException::class)]
#[CoversClass(DocumentException::class)]
#[CoversClass(EngineException::class)]
#[CoversClass(UnknownProfileException::class)]
final class ExceptionFactoriesTest extends TestCase
{
    public function testFactories(): void
    {
        self::assertStringContainsString('not allowed', AccessDeniedException::workspace()->getMessage());
        self::assertStringContainsString('not found', DocumentException::notFound('abc')->getMessage());
        self::assertStringContainsString('bad', DocumentException::invalidUpload('bad')->getMessage());
        self::assertStringContainsString('1024', DocumentException::tooLarge(1024)->getMessage());
        self::assertStringContainsString('python3', EngineException::missingPython('python3')->getMessage());
        self::assertStringContainsString('engine.py', EngineException::missingEngineScript('engine.py')->getMessage());
        self::assertStringContainsString('1.5', EngineException::timedOut(1.5)->getMessage());
        self::assertStringContainsString('nope', EngineException::failed('nope')->getMessage());
        self::assertStringContainsString('syntax', EngineException::invalidJson('syntax')->getMessage());
        self::assertStringContainsString('x', UnknownProfileException::forProfile('x')->getMessage());
    }
}
