<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\Engine;

use Nowo\PdfEditorBundle\Config\EditorProfile;
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

    public function testCommandCarriesTheMemoryLimitAndTheRenderPixelCap(): void
    {
        $script          = (string) tempnam(sys_get_temp_dir(), 'eng');
        $this->scripts[] = $script;
        $runner          = new class implements ProcessRunnerInterface {
            /** @var list<list<string>> */
            public array $commands = [];

            public function run(array $command, float $timeout, float $idleTimeout): ProcessOutcome
            {
                $this->commands[] = $command;

                return new ProcessOutcome(0, '{"ok": true}', '', false);
            }
        };
        $base    = ProfileFactory::create(sys_get_temp_dir(), $script);
        $profile = new EditorProfile($base->name, 'python3', $script, 5.0, 2.0, 1024, 144, sys_get_temp_dir(), 'python', false, ['ROLE_ADMIN'], 4_000_000, 512);
        (new PythonPdfEngine($runner, $profile))->renderPage('/w/current.pdf', 2, '/w/page-2.png', 144);

        self::assertSame(['python3', $script, '--memory-limit', '512', 'render', '--pdf', '/w/current.pdf', '--page', '2', '--out', '/w/page-2.png', '--dpi', '144', '--max-pixels', '4000000'], $runner->commands[0]);

        $unlimited = new EditorProfile($base->name, 'python3', $script, 5.0, 2.0, 1024, 144, sys_get_temp_dir(), 'python', false, ['ROLE_ADMIN'], 4_000_000, 0);
        (new PythonPdfEngine($runner, $unlimited))->version();
        self::assertSame(['python3', $script, 'version'], $runner->commands[1]);
    }

    public function testFailureMessagesNeverCarryPathsOrTracebacks(): void
    {
        try {
            $this->engine(new ProcessOutcome(1, '', "Traceback (most recent call last):\n  File \"/srv/app/engine.py\", line 3", false))->inspect('/tmp/a.pdf');
            self::fail('Expected an engine failure.');
        } catch (EngineException $e) {
            self::assertSame('PDF engine failed: exit 1', $e->getMessage());
        }

        try {
            $this->engine(new ProcessOutcome(1, '{"ok": false, "error": "cannot open /var/www/var/pdf-editor/ab/current.pdf: broken\nsecond line"}', '', false))->inspect('/tmp/a.pdf');
            self::fail('Expected an engine failure.');
        } catch (EngineException $e) {
            self::assertSame('PDF engine failed: cannot open [path] broken', $e->getMessage());
        }
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
