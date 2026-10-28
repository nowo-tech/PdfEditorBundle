<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\DependencyInjection;

use LogicException;
use Nowo\PdfEditorBundle\Config\EditorProfile;
use Nowo\PdfEditorBundle\DependencyInjection\Configuration;
use Nowo\PdfEditorBundle\DependencyInjection\PdfEditorExtension;
use Nowo\PdfEditorBundle\Engine\PdfEngineInterface;
use Nowo\PdfEditorBundle\Engine\ProcessRunnerInterface;
use Nowo\PdfEditorBundle\Security\AllowAllPdfEditorAccessChecker;
use Nowo\PdfEditorBundle\Security\RolePdfEditorAccessChecker;
use Nowo\PdfEditorBundle\Security\WorkspaceOwnerResolverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(Configuration::class)]
#[CoversClass(PdfEditorExtension::class)]
final class ConfigurationAndExtensionTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);
        self::assertSame('default', $config['default_profile']);
        self::assertArrayHasKey('default', $config['profiles']);
        self::assertFalse($config['security']['allow_unauthenticated']);
        self::assertSame(['ROLE_ADMIN'], $config['security']['roles']);
    }

    public function testUnknownDefaultProfile(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [[
            'default_profile' => 'missing',
            'profiles'        => ['default' => []],
        ]]);
    }

    public function testExtraProfilesAreDeprecated(): void
    {
        $this->expectUserDeprecationMessage('Since nowo-tech/pdf-editor-bundle 1.2: Defining profiles other than "nowo_pdf_editor.default_profile" is deprecated: only the default profile is used, so "fast", "slow" has no effect. Keep a single profile; support for several profiles will be removed in 2.0.');

        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'default_profile' => 'main',
            'profiles'        => ['main' => [], 'fast' => ['timeout' => 5.0], 'slow' => []],
        ]]);

        self::assertSame('main', $config['default_profile']);
    }

    public function testTimeoutMinimum(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => ['default' => ['timeout' => 0.1]],
        ]]);
    }

    public function testLoadRewritesVendorEngineAndRegistersServices(): void
    {
        $container = $this->container();
        $extension = new PdfEditorExtension();
        $extension->load([], $container);

        self::assertSame('nowo_pdf_editor', $extension->getAlias());
        self::assertTrue($container->hasAlias('nowo_pdf_editor.process_runner') || $container->hasDefinition('nowo_pdf_editor.process_runner'));
        self::assertTrue($container->hasAlias(ProcessRunnerInterface::class));
        self::assertTrue($container->hasAlias(PdfEngineInterface::class));
        self::assertTrue($container->hasAlias(EditorProfile::class));
        self::assertSame('default', $container->getParameter('nowo_pdf_editor.default_profile'));
        self::assertSame(RolePdfEditorAccessChecker::class, $container->getDefinition('nowo_pdf_editor.access_checker')->getClass());

        $engineScript = $container->getDefinition('nowo_pdf_editor.profile')->getArgument('$engineScript');
        self::assertIsString($engineScript);
        self::assertStringEndsWith('/engine/pdf_editor_engine.py', $engineScript);
        self::assertStringNotContainsString('vendor/nowo-tech/pdf-editor-bundle', $engineScript);
    }

    public function testAllowUnauthenticatedIsRefusedInProduction(): void
    {
        foreach ([['prod', true], ['staging', false]] as [$environment, $debug]) {
            $container = $this->container();
            $container->setParameter('kernel.environment', $environment);
            $container->setParameter('kernel.debug', $debug);
            try {
                (new PdfEditorExtension())->load([['security' => ['allow_unauthenticated' => true]]], $container);
                self::fail($environment . ': allow_unauthenticated must be refused');
            } catch (LogicException $e) {
                self::assertStringContainsString('dev/demo switch', $e->getMessage());
            }
        }
    }

    public function testLoadAllowUnauthenticatedAndCustomEngineScript(): void
    {
        $container = $this->container();
        $extension = new PdfEditorExtension();
        $extension->load([[
            'security' => ['allow_unauthenticated' => true],
            'profiles' => [
                'default' => [
                    'engine_script' => '/opt/custom/pdf_editor_engine.py',
                    'python_binary' => 'python3',
                ],
            ],
        ]], $container);

        self::assertSame(AllowAllPdfEditorAccessChecker::class, $container->getDefinition('nowo_pdf_editor.access_checker')->getClass());
        self::assertSame(
            '/opt/custom/pdf_editor_engine.py',
            $container->getDefinition('nowo_pdf_editor.profile')->getArgument('$engineScript'),
        );
    }

    public function testPrepend(): void
    {
        $container = $this->container();
        $container->registerExtension(new StubExtension('twig'));
        $container->registerExtension(new StubExtension('framework'));
        $extension = new PdfEditorExtension();
        $extension->prepend($container);
        $twig = $container->getExtensionConfig('twig');
        self::assertNotSame([], $twig);
        self::assertArrayHasKey('paths', $twig[0]);
        $framework = $container->getExtensionConfig('framework');
        self::assertCount(2, $framework);
    }

    public function testPrependSkipsMissingTwigAndFramework(): void
    {
        $container = $this->container();
        (new PdfEditorExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('twig'));
        self::assertSame([], $container->getExtensionConfig('framework'));
    }

    public function testPrependFrameworkWithoutTwig(): void
    {
        $container = $this->container();
        $container->registerExtension(new StubExtension('framework'));
        (new PdfEditorExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('twig'));
        self::assertCount(2, $container->getExtensionConfig('framework'));
    }

    public function testWorkspaceManagerIsBoundToOwnerResolver(): void
    {
        $container = $this->container();
        (new PdfEditorExtension())->load([], $container);

        $owners = $container->getDefinition('nowo_pdf_editor.workspace_manager')->getArgument('$owners');
        self::assertInstanceOf(Reference::class, $owners);
        self::assertSame(WorkspaceOwnerResolverInterface::class, (string) $owners);
        self::assertTrue($container->hasAlias(WorkspaceOwnerResolverInterface::class));
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/tmp/project');

        return $container;
    }
}

/**
 * @internal
 */
final class StubExtension extends Extension
{
    public function __construct(
        private readonly string $alias,
    ) {
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    public function getAlias(): string
    {
        return $this->alias;
    }
}
