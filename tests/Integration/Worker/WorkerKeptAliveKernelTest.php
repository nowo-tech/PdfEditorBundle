<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Integration\Worker;

use Nowo\PdfEditorBundle\Security\WorkspaceOwnerResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

use function bin2hex;
use function file_put_contents;
use function gc_collect_cycles;
use function hash;
use function memory_get_usage;
use function mkdir;
use function random_bytes;
use function sprintf;
use function str_contains;
use function sys_get_temp_dir;
use function uniqid;

/**
 * REQ-RUNTIME-002: ONE booted kernel, `services_resetter` / `kernel.reset` never called (FrankenPHP worker, kernel
 * reset false). Two anonymous users (two sessions) each own one workspace. N iterations alternate them through
 * `http_kernel` `handle()` + `terminate()`: the workspace page and the PDF download of the caller's own workspace
 * must answer with its own file name / bytes, the other user's workspace id must always be a 404, and memory growth
 * after warm-up must stay below 4 MiB. The owner is resolved from the current session on every call; nothing of the
 * previous caller may survive in the shared services.
 */
final class WorkerKeptAliveKernelTest extends TestCase
{
    private const ITERATIONS = 1200;
    private const WARM_UP    = 100;
    private const MARGIN     = 4 * 1024 * 1024;

    private ?WorkerTestKernel $kernel = null;

    protected function tearDown(): void
    {
        if ($this->kernel instanceof WorkerTestKernel) {
            $dir = $this->kernel->getProjectDir();
            $this->kernel->shutdown();
            (new Filesystem())->remove($dir);
        }
        $this->kernel = null;
    }

    public function testAlternatingUsersInOneKernelLeakNothingAndKeepMemoryFlat(): void
    {
        $this->kernel = new WorkerTestKernel(sys_get_temp_dir() . '/nowo-pdf-editor-worker-' . uniqid('', true));
        $this->kernel->boot();

        $users = [];
        foreach (['alice', 'bob'] as $name) {
            $token     = bin2hex(random_bytes(16));
            $id        = bin2hex(random_bytes(16));
            $directory = $this->kernel->workspaceDir() . '/' . $id;
            mkdir($directory, 0700, true);
            file_put_contents($directory . '/current.pdf', '%PDF-1.4 ' . $name . '-secret-bytes');
            file_put_contents($directory . '/meta.txt', $name . '-report.pdf');
            file_put_contents($directory . '/owner.txt', 'session:' . hash('sha256', $token));
            $session = new Session(new MockArraySessionStorage());
            $session->set(WorkspaceOwnerResolver::SESSION_KEY, $token);
            $users[$name] = ['id' => $id, 'session' => $session];
        }

        $baseline = 0;
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            if ($i === self::WARM_UP) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }

            [$me, $other] = $i % 2 === 0 ? ['alice', 'bob'] : ['bob', 'alice'];
            $context      = sprintf('iteration %d', $i);

            // The caller's own workspace page.
            $request  = $this->request('/pdf-editor/' . $users[$me]['id'], $users[$me]['session']);
            $response = $this->kernel->handle($request);
            $content  = (string) $response->getContent();
            self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $context);
            self::assertTrue(str_contains($content, $me . '-report.pdf'), $context . ': own file name missing');
            self::assertFalse(str_contains($content, $other . '-report.pdf'), $context . ': other user file name present');
            self::assertFalse(str_contains($content, $users[$other]['id']), $context . ': other workspace id present');
            $this->kernel->terminate($request, $response);

            // The caller's own PDF bytes.
            $request  = $this->request('/pdf-editor/' . $users[$me]['id'] . '/download', $users[$me]['session']);
            $response = $this->kernel->handle($request);
            self::assertInstanceOf(BinaryFileResponse::class, $response, $context);
            self::assertStringContainsString($me . '-report.pdf', (string) $response->headers->get('Content-Disposition'), $context);
            self::assertStringNotContainsString($other, (string) $response->headers->get('Content-Disposition'), $context);
            self::assertStringEqualsFile($response->getFile()->getPathname(), '%PDF-1.4 ' . $me . '-secret-bytes', $context);
            $this->kernel->terminate($request, $response);

            // The other user's workspace is never reachable.
            $request  = $this->request('/pdf-editor/' . $users[$other]['id'], $users[$me]['session']);
            $response = $this->kernel->handle($request);
            self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), $context . ': foreign workspace reachable');
            self::assertFalse(str_contains((string) $response->getContent(), $other . '-report.pdf'), $context);
            $this->kernel->terminate($request, $response);
        }

        gc_collect_cycles();
        $growth = memory_get_usage() - $baseline;
        self::assertLessThan(self::MARGIN, $growth, sprintf('memory grew by %d bytes over %d iterations', $growth, self::ITERATIONS - self::WARM_UP));
    }

    private function request(string $path, Session $session): Request
    {
        $request = Request::create($path);
        $request->setSession($session);

        return $request;
    }
}
