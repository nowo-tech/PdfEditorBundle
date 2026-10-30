<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\PdfEditorBundle\DependencyInjection\Compiler\TwigPathsPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Twig\Loader\FilesystemLoader;

use function dirname;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;

/**
 * REQ-TWIG-001 / REQ-TWIG-002: loader order of the @NowoPdfEditorBundle namespace (application overrides first).
 */
#[CoversClass(TwigPathsPass::class)]
final class TwigPathsPassTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/pdf-editor-twig-' . uniqid('', true);
        mkdir($this->projectDir . '/templates/bundles', 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (['NowoPdfEditorBundle', 'PdfEditorBundle'] as $dir) {
            @rmdir($this->projectDir . '/templates/bundles/' . $dir);
        }
        @rmdir($this->projectDir . '/templates/bundles');
        @rmdir($this->projectDir . '/templates');
        @rmdir($this->projectDir);
    }

    public function testRegistersTheBundleViewsWithoutOverrideDirectories(): void
    {
        $container = $this->container('twig.loader.native_filesystem');
        (new TwigPathsPass())->process($container);

        self::assertSame([
            ['addPath', [$this->views(), 'NowoPdfEditorBundle']],
            ['addPath', [$this->views(), '!NowoPdfEditorBundle']],
            ['addPath', [$this->views(), 'PdfEditor']],
        ], $this->calls($container, 'twig.loader.native_filesystem'));
    }

    public function testApplicationOverrideDirectoryIsPrependedBeforeTheBundleViews(): void
    {
        mkdir($this->projectDir . '/templates/bundles/NowoPdfEditorBundle');
        mkdir($this->projectDir . '/templates/bundles/PdfEditorBundle');
        $container = $this->container('twig.loader.native_filesystem');
        (new TwigPathsPass())->process($container);

        self::assertSame([
            ['prependPath', [$this->projectDir . '/templates/bundles/NowoPdfEditorBundle', 'NowoPdfEditorBundle']],
            ['addPath', [$this->views(), 'NowoPdfEditorBundle']],
            ['addPath', [$this->views(), '!NowoPdfEditorBundle']],
            ['addPath', [$this->projectDir . '/templates/bundles/PdfEditorBundle', 'PdfEditor']],
            ['addPath', [$this->views(), 'PdfEditor']],
        ], $this->calls($container, 'twig.loader.native_filesystem'));
    }

    public function testOverrideWinsAtRuntime(): void
    {
        $override = $this->projectDir . '/templates/bundles/NowoPdfEditorBundle';
        mkdir($override);
        $container = $this->container('twig.loader.native_filesystem');
        (new TwigPathsPass())->process($container);

        $loader = new FilesystemLoader();
        foreach ($this->calls($container, 'twig.loader.native_filesystem') as [$method, $arguments]) {
            $loader->{$method}(...$arguments);
        }

        self::assertSame([$override, $this->views()], $loader->getPaths('NowoPdfEditorBundle'));
        self::assertSame([$this->views()], $loader->getPaths('!NowoPdfEditorBundle'));
    }

    public function testFollowsTheTwigLoaderNativeAliasChain(): void
    {
        $container = $this->container('twig.loader.real');
        $container->setAlias('twig.loader.intermediate', 'twig.loader.real');
        $container->setAlias('twig.loader.native', 'twig.loader.intermediate');
        (new TwigPathsPass())->process($container);

        self::assertCount(3, $this->calls($container, 'twig.loader.real'));
    }

    public function testFallsBackToTwigLoaderNativeDefinition(): void
    {
        $container = $this->container('twig.loader.native');
        (new TwigPathsPass())->process($container);

        self::assertCount(3, $this->calls($container, 'twig.loader.native'));
    }

    public function testDanglingNativeAliasFallsBackToTheFilesystemLoader(): void
    {
        $container = $this->container('twig.loader.native_filesystem');
        $container->setAlias('twig.loader.native', 'twig.loader.missing');
        (new TwigPathsPass())->process($container);

        self::assertCount(3, $this->calls($container, 'twig.loader.native_filesystem'));
    }

    public function testNoRegistrationWithoutTwig(): void
    {
        $container = new ContainerBuilder();
        (new TwigPathsPass())->process($container);

        self::assertFalse($container->hasDefinition('twig.loader.native_filesystem'));
    }

    public function testWithoutProjectDirOnlyTheBundleViewsAreRegistered(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('twig.loader.native_filesystem', new Definition(FilesystemLoader::class));
        (new TwigPathsPass())->process($container);

        self::assertCount(3, $this->calls($container, 'twig.loader.native_filesystem'));
    }

    private function container(string $loaderId): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $this->projectDir . '/');
        $container->setDefinition($loaderId, new Definition(FilesystemLoader::class));

        return $container;
    }

    /**
     * @return list<array{0: string, 1: array<int, mixed>}>
     */
    private function calls(ContainerBuilder $container, string $id): array
    {
        /** @var list<array{0: string, 1: array<int, mixed>}> $calls */
        $calls = $container->getDefinition($id)->getMethodCalls();

        return $calls;
    }

    private function views(): string
    {
        return dirname(__DIR__, 4) . '/src/Resources/views';
    }
}
