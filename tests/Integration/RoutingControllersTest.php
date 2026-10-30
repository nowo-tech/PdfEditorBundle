<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Integration;

use Nowo\PdfEditorBundle\Tests\Integration\Fixtures\RoutingTestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\RouterInterface;

use function array_filter;
use function array_keys;
use function array_values;
use function class_exists;
use function sort;
use function str_starts_with;
use function uniqid;

/**
 * The editor routes are opt-in: hosts import `@NowoPdfEditorBundle/Resources/config/routing.yaml` (with their own
 * prefix / when@ guards). Since Symfony 7.3 FrameworkBundle autoconfigures services carrying #[Route] with the
 * `routing.controller` tag, and the default host `routing.controllers` import would then load the editor routes
 * without that opt-in — the controller service is therefore not autoconfigured.
 */
#[CoversNothing]
final class RoutingControllersTest extends TestCase
{
    /** @var list<RoutingTestKernel> */
    private array $kernels = [];

    protected function setUp(): void
    {
        if (!class_exists(AttributeServicesLoader::class)) {
            self::markTestSkipped('routing.controllers (AttributeServicesLoader) needs symfony/routing 7.3+.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->kernels as $kernel) {
            $kernel->shutdown();
            (new Filesystem())->remove($kernel->getProjectDir());
        }
    }

    public function testRoutingControllersAloneDoesNotExposeTheEditor(): void
    {
        self::assertSame([], $this->editorRoutes(false));
    }

    public function testBundleRoutingImportExposesTheEditor(): void
    {
        self::assertSame([
            'nowo_pdf_editor_apply',
            'nowo_pdf_editor_download',
            'nowo_pdf_editor_home',
            'nowo_pdf_editor_inspect',
            'nowo_pdf_editor_page',
            'nowo_pdf_editor_view',
            'nowo_pdf_editor_workspace',
        ], $this->editorRoutes(true));
    }

    /**
     * @return list<string>
     */
    private function editorRoutes(bool $importBundleRouting): array
    {
        $kernel          = new RoutingTestKernel($importBundleRouting, uniqid('', true));
        $this->kernels[] = $kernel;
        $kernel->boot();
        $router = $kernel->getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $names = array_values(array_filter(
            array_keys($router->getRouteCollection()->all()),
            static fn (string $name): bool => str_starts_with($name, 'nowo_pdf_editor'),
        ));
        sort($names);

        return $names;
    }
}
