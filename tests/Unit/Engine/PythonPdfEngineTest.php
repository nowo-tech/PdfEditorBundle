<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Engine;

use Nowo\PdfEditorBundle\Engine\ProcessOutcome;
use Nowo\PdfEditorBundle\Engine\ProcessRunnerInterface;
use Nowo\PdfEditorBundle\Engine\PythonPdfEngine;
use Nowo\PdfEditorBundle\Exception\EngineException;
use Nowo\PdfEditorBundle\Operation\EditorOperation;
use Nowo\PdfEditorBundle\Tests\ProfileFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PythonPdfEngine::class)]
#[CoversClass(ProcessOutcome::class)]
final class PythonPdfEngineTest extends TestCase
{
    public function testInspectDecodesOkPayload(): void
    {
        $engine = $this->engine(new ProcessOutcome(0, '{"ok": true, "pageCount": 1}', '', false));
        self::assertSame(1, $engine->inspect('/tmp/a.pdf')['pageCount']);
        self::assertSame('pymupdf', $engine->version()['engine']);
    }

    public function testTimedOut(): void
    {
        $this->expectException(EngineException::class);
        $this->engine(new ProcessOutcome(124, '', 'timeout', true))->inspect('/tmp/a.pdf');
    }

    public function testFailedStatus(): void
    {
        $this->expectException(EngineException::class);
        $this->engine(new ProcessOutcome(1, '{"ok": false, "error": "boom"}', '', false))->inspect('/tmp/a.pdf');
    }

    public function testInvalidJsonWhenExitZero(): void
    {
        $this->expectException(EngineException::class);
        $this->engine(new ProcessOutcome(0, 'not-json', '', false))->inspect('/tmp/a.pdf');
    }

    public function testMissingScript(): void
    {
        $runner = $this->runner(new ProcessOutcome(0, '{}', '', false));
        $engine = new PythonPdfEngine($runner, ProfileFactory::create(sys_get_temp_dir(), '/no/such/engine.py'));
        $this->expectException(EngineException::class);
        $engine->version();
    }

    public function testApplyAndRender(): void
    {
        $engine = $this->engine(new ProcessOutcome(0, '{"ok": true, "applied": 1}', '', false));
        $result = $engine->apply('/tmp/a.pdf', [new EditorOperation('insert_text', ['page' => 1])], '/tmp/out.pdf');
        self::assertSame(1, $result['applied']);
        $engine->renderPage('/tmp/a.pdf', 1, '/tmp/p.png', 72);
    }

    public function testNonObjectJson(): void
    {
        $this->expectException(EngineException::class);
        $this->engine(new ProcessOutcome(0, 'true', '', false))->inspect('/tmp/a.pdf');
    }

    public function testFailedExitWithoutOkJson(): void
    {
        $this->expectException(EngineException::class);
        $this->engine(new ProcessOutcome(2, 'broken', 'err', false))->inspect('/tmp/a.pdf');
    }

    public function testOkJsonWithFailedExit(): void
    {
        $this->expectException(EngineException::class);
        $this->engine(new ProcessOutcome(7, '{"ok": true}', 'fail', false))->inspect('/tmp/a.pdf');
    }

    public function testFailedOkFalseWithoutError(): void
    {
        $this->expectException(EngineException::class);
        $this->engine(new ProcessOutcome(0, '{"ok": false}', '', false))->inspect('/tmp/a.pdf');
    }

    public function testJsonOnStderrWhenStdoutEmpty(): void
    {
        $engine = $this->engine(new ProcessOutcome(0, '', '{"ok": true, "pageCount": 4}', false));
        self::assertSame(4, $engine->inspect('/tmp/a.pdf')['pageCount']);
    }

    public function testFailedExitWithEmptyOutputs(): void
    {
        $this->expectException(EngineException::class);
        $this->engine(new ProcessOutcome(3, '', '', false))->inspect('/tmp/a.pdf');
    }

    public function testOkJsonWithFailedExitEmptyStderr(): void
    {
        $this->expectException(EngineException::class);
        $this->engine(new ProcessOutcome(9, '{"ok": true}', '', false))->inspect('/tmp/a.pdf');
    }

    private function runner(ProcessOutcome $outcome): ProcessRunnerInterface
    {
        return new class($outcome) implements ProcessRunnerInterface {
            public function __construct(private ProcessOutcome $outcome)
            {
            }

            public function run(array $command, float $timeout, float $idleTimeout): ProcessOutcome
            {
                return $this->outcome;
            }
        };
    }

    private function engine(ProcessOutcome $outcome): PythonPdfEngine
    {
        $script = tempnam(sys_get_temp_dir(), 'eng');
        file_put_contents((string) $script, "#\n");
        $this->scripts[] = (string) $script;

        return new PythonPdfEngine($this->runner($outcome), ProfileFactory::create(sys_get_temp_dir(), (string) $script));
    }

    /** @var list<string> */
    private array $scripts = [];

    protected function tearDown(): void
    {
        foreach ($this->scripts as $script) {
            @unlink($script);
        }
    }
}
