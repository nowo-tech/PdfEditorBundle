<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Integration\Worker;

use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\PdfEditorBundle\NowoPdfEditorBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Host kernel of the kept-alive worker test: the editor routes imported, anonymous access (non-debug `test` kernel, which the
 * `allow_unauthenticated` option accepts) so workspaces belong to the caller's session, own project directory.
 */
final class WorkerTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly string $dir)
    {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new NowoFormKitBundle();
        yield new NowoUiKitBundle();
        yield new NowoPdfEditorBundle();
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function getCacheDir(): string
    {
        return $this->dir . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->dir . '/log';
    }

    public function workspaceDir(): string
    {
        return $this->dir . '/workspaces';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'               => 'test-secret',
            'test'                 => true,
            'http_method_override' => false,
            'csrf_protection'      => true,
            'form'                 => ['csrf_protection' => true],
            'session'              => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'router'               => ['utf8' => true],
            'assets'               => true,
        ]);
        $container->extension('security', [
            'providers' => ['memory' => ['memory' => null]],
            'firewalls' => ['main' => ['lazy' => true]],
        ]);
        $container->extension('twig', []);
        // The expected 404s of the foreign-workspace requests must not flood the test output.
        $container->services()->set('logger', NullLogger::class);
        $container->extension('nowo_pdf_editor', [
            'profiles' => ['default' => ['workspace_dir' => $this->workspaceDir()]],
            'security' => ['allow_unauthenticated' => true],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@NowoPdfEditorBundle/Resources/config/routing.yaml');
    }
}
