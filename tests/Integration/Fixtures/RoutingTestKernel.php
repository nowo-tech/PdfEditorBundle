<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Integration\Fixtures;

use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\PdfEditorBundle\NowoPdfEditorBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function sys_get_temp_dir;

/**
 * Minimal host kernel for routing tests: `routing.controllers` only (the default Symfony 7.3+ `config/routes.yaml`),
 * or that plus the bundle's opt-in `@NowoPdfEditorBundle/Resources/config/routing.yaml` import.
 */
final class RoutingTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(
        private readonly bool $importBundleRouting,
        private readonly string $id,
    ) {
        parent::__construct('test', true);
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
        return sys_get_temp_dir() . '/nowo-pdf-editor-routing-' . $this->id;
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/log';
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
        ]);
        $container->extension('security', [
            'providers' => ['memory' => ['memory' => null]],
            'firewalls' => ['main' => ['lazy' => true]],
        ]);
        $container->extension('twig', []);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // Default host config/routes.yaml since Symfony 7.3: `controllers: { resource: routing.controllers }`.
        $routes->import('routing.controllers');

        if ($this->importBundleRouting) {
            $routes->import('@NowoPdfEditorBundle/Resources/config/routing.yaml');
        }
    }
}
