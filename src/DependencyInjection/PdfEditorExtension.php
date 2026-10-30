<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\DependencyInjection;

use LogicException;
use Nowo\PdfEditorBundle\Config\EditorProfile;
use Nowo\PdfEditorBundle\Document\WorkspaceManager;
use Nowo\PdfEditorBundle\Engine\PdfEngineInterface;
use Nowo\PdfEditorBundle\Engine\ProcessRunnerInterface;
use Nowo\PdfEditorBundle\Engine\PythonPdfEngine;
use Nowo\PdfEditorBundle\Security\AllowAllPdfEditorAccessChecker;
use Nowo\PdfEditorBundle\Security\PdfEditorAccessCheckerInterface;
use Nowo\PdfEditorBundle\Security\RolePdfEditorAccessChecker;
use Nowo\PdfEditorBundle\Security\WorkspaceOwnerResolverInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Asset\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function array_values;
use function class_exists;
use function dirname;
use function is_array;
use function is_string;
use function str_contains;
use function str_starts_with;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class PdfEditorExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @param array<int, array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $config         = $this->processConfiguration(new Configuration(), $configs);
        $defaultProfile = (string) $config['default_profile'];
        $profiles       = $config['profiles'];
        $security       = $config['security'];
        $allowUnauth    = (bool) $security['allow_unauthenticated'];
        if ($allowUnauth) {
            $this->refuseAllowUnauthenticatedInProduction($container);
        }
        /** @var list<string> $roles */
        $roles         = array_values($security['access_roles']);
        $customChecker = $security['access_checker'];
        $webUi         = $config['web_ui'];

        // Configuration guarantees that default_profile exists under profiles.
        $profileConfig = $profiles[$defaultProfile];
        $engineScript  = (string) $profileConfig['engine_script'];
        $bundleEngine  = dirname(__DIR__, 2) . '/engine/pdf_editor_engine.py';
        if (str_contains($engineScript, 'vendor/nowo-tech/pdf-editor-bundle/engine/pdf_editor_engine.py')) {
            $engineScript = $bundleEngine;
        }

        $profileDef = new Definition(EditorProfile::class, [
            '$name'                 => $defaultProfile,
            '$pythonBinary'         => (string) $profileConfig['python_binary'],
            '$engineScript'         => $engineScript,
            '$timeout'              => (float) $profileConfig['timeout'],
            '$idleTimeout'          => (float) $profileConfig['idle_timeout'],
            '$maxUploadBytes'       => (int) $profileConfig['max_upload_bytes'],
            '$renderDpi'            => (int) $profileConfig['render_dpi'],
            '$workspaceDir'         => (string) $profileConfig['workspace_dir'],
            '$engineMode'           => (string) $profileConfig['engine_mode'],
            '$allowUnauthenticated' => $allowUnauth,
            '$roles'                => $roles,
            '$maxRenderPixels'      => (int) $profileConfig['max_render_pixels'],
            '$engineMemoryLimit'    => (int) $profileConfig['engine_memory_limit'],
        ]);
        $container->setDefinition('nowo_pdf_editor.profile', $profileDef);
        $container->setAlias(EditorProfile::class, 'nowo_pdf_editor.profile');

        $engine = new Definition(PythonPdfEngine::class, [
            '$processRunner' => new Reference('nowo_pdf_editor.process_runner'),
            '$profile'       => new Reference('nowo_pdf_editor.profile'),
            '$logger'        => new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
        ]);
        $container->setDefinition('nowo_pdf_editor.engine', $engine);
        $container->setAlias(PdfEngineInterface::class, 'nowo_pdf_editor.engine');

        $container->setAlias(ProcessRunnerInterface::class, 'nowo_pdf_editor.process_runner');

        $workspace = new Definition(WorkspaceManager::class, [
            '$profile'                  => new Reference('nowo_pdf_editor.profile'),
            '$owners'                   => new Reference(WorkspaceOwnerResolverInterface::class),
            '$clock'                    => new Reference(ClockInterface::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            '$logger'                   => new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            '$allowOwnerlessWorkspaces' => (bool) $security['allow_ownerless_workspaces'],
        ]);
        $container->setDefinition('nowo_pdf_editor.workspace_manager', $workspace);
        $container->setAlias(WorkspaceManager::class, 'nowo_pdf_editor.workspace_manager');

        if (is_string($customChecker) && $customChecker !== '') {
            // Host checker (REQ-UI-002): the interface alias points at the configured service.
            $container->setAlias('nowo_pdf_editor.access_checker', $customChecker);
        } elseif ($allowUnauth) {
            $container->setDefinition('nowo_pdf_editor.access_checker', new Definition(AllowAllPdfEditorAccessChecker::class));
        } else {
            $container->setDefinition('nowo_pdf_editor.access_checker', new Definition(RolePdfEditorAccessChecker::class, [
                '$authorizationChecker' => new Reference('security.authorization_checker'),
                '$roles'                => $roles,
            ]));
        }
        $container->setAlias(PdfEditorAccessCheckerInterface::class, 'nowo_pdf_editor.access_checker');

        $container->setParameter('nowo_pdf_editor.security.access_roles', $roles);
        $container->setParameter('nowo_pdf_editor.security.access_checker', is_string($customChecker) && $customChecker !== '' ? $customChecker : null);
        $container->setParameter('nowo_pdf_editor.security.allow_unauthenticated', $allowUnauth);
        $container->setParameter('nowo_pdf_editor.web_ui.layout_template', $webUi['layout_template']);
        $container->setParameter('nowo_pdf_editor.web_ui.templates', $webUi['templates']);
        $container->setParameter('nowo_pdf_editor.default_profile', $defaultProfile);
        $container->setParameter('nowo_pdf_editor.profiles', array_keys($profiles));
    }

    public function prepend(ContainerBuilder $container): void
    {
        // The @NowoPdfEditorBundle Twig namespace is registered by TwigPathsPass (application overrides first, REQ-TWIG-001).
        if (!$container->hasExtension('framework')) {
            return;
        }

        $container->prependExtensionConfig('framework', [
            'translator' => [
                'paths' => [__DIR__ . '/../Resources/translations'],
            ],
        ]);

        // framework.assets fails to boot without symfony/asset (it is a hard requirement, but stay defensive).
        if (!class_exists(Package::class)) { // @codeCoverageIgnoreStart
            return;
            // @codeCoverageIgnoreEnd
        }

        $container->prependExtensionConfig('framework', [
            'assets' => [
                'packages' => [
                    'nowo_pdf_editor' => [
                        'base_path' => $this->publicBasePath($container),
                    ],
                ],
            ],
        ]);
    }

    public function getAlias(): string
    {
        return 'nowo_pdf_editor';
    }

    /**
     * `assets:install` publishes `src/Resources/public` under `/bundles/<bundle name without "Bundle", lowercased>`:
     * `/bundles/nowopdfeditor` for NowoPdfEditorBundle, `/bundles/pdfeditor` for the deprecated PdfEditorBundle class.
     */
    private function publicBasePath(ContainerBuilder $container): string
    {
        $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [];
        if (is_array($bundles) && !isset($bundles['NowoPdfEditorBundle']) && isset($bundles['PdfEditorBundle'])) {
            return '/bundles/pdfeditor';
        }

        return '/bundles/nowopdfeditor';
    }

    /**
     * `allow_unauthenticated` opens the PDF editor to anonymous users: a dev / demo switch, refused in the prod environment and in
     * any non-debug kernel outside the test environments (test*), as in ContactFormBundle.
     */
    private function refuseAllowUnauthenticatedInProduction(ContainerBuilder $container): void
    {
        $environment = $container->hasParameter('kernel.environment') ? $container->getParameter('kernel.environment') : '';
        $environment = is_string($environment) ? $environment : '';
        $debug       = $container->hasParameter('kernel.debug') ? (bool) $container->getParameter('kernel.debug') : true;

        if ($environment === 'prod' || (!$debug && !str_starts_with($environment, 'test'))) {
            throw new LogicException('nowo_pdf_editor.security.allow_unauthenticated is a dev/demo switch: it opens the PDF editor to anonymous users and is refused in the prod environment and in non-debug kernels (test* environments excepted).');
        }
    }
}
