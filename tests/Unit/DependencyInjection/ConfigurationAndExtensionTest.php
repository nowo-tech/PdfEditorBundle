<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Unit\DependencyInjection;

use Nowo\PdfEditorBundle\Config\EditorProfile;
use Nowo\PdfEditorBundle\DependencyInjection\Configuration;
use Nowo\PdfEditorBundle\DependencyInjection\PdfEditorExtension;
use Nowo\PdfEditorBundle\Engine\PdfEngineInterface;
use Nowo\PdfEditorBundle\Engine\ProcessRunnerInterface;
use Nowo\PdfEditorBundle\Security\AllowAllPdfEditorAccessChecker;
use Nowo\PdfEditorBundle\Security\RolePdfEditorAccessChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
        $extension = new PdfEditorExtension();
        $extension->prepend($container);
        $twig = $container->getExtensionConfig('twig');
        self::assertNotSame([], $twig);
        self::assertArrayHasKey('paths', $twig[0]);
        $framework = $container->getExtensionConfig('framework');
        self::assertCount(2, $framework);
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/tmp/project');

        return $container;
    }
}
