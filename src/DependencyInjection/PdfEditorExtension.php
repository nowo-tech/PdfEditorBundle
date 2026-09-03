<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\DependencyInjection;

use Nowo\PdfEditorBundle\Config\EditorProfile;
use Nowo\PdfEditorBundle\Document\WorkspaceManager;
use Nowo\PdfEditorBundle\Engine\PdfEngineInterface;
use Nowo\PdfEditorBundle\Engine\PythonPdfEngine;
use Nowo\PdfEditorBundle\Exception\UnknownProfileException;
use Nowo\PdfEditorBundle\Security\AllowAllPdfEditorAccessChecker;
use Nowo\PdfEditorBundle\Security\PdfEditorAccessCheckerInterface;
use Nowo\PdfEditorBundle\Security\RolePdfEditorAccessChecker;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function dirname;
use function sprintf;

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
        $loader = new YamlFileLoader($container, new FileLocator(dirname(__DIR__) . '/Resources/config'));
        $loader->load('services.yaml');

        $config          = $this->processConfiguration(new Configuration(), $configs);
        $defaultProfile  = (string) $config['default_profile'];
        $profiles        = $config['profiles'];
        $security        = $config['security'];
        $allowUnauth     = (bool) $security['allow_unauthenticated'];
        $roles           = array_values($security['roles']);

        if (!isset($profiles[$defaultProfile])) {
            throw UnknownProfileException::forProfile($defaultProfile);
        }

        $profileConfig = $profiles[$defaultProfile];
        $engineScript  = (string) $profileConfig['engine_script'];
        $bundleEngine  = dirname(__DIR__, 2) . '/engine/pdf_editor_engine.py';
        if (str_contains($engineScript, 'vendor/nowo-tech/pdf-editor-bundle/engine/pdf_editor_engine.py')) {
            $engineScript = $bundleEngine;
        }

        $profileDef = new Definition(EditorProfile::class, [
            '$name'                  => $defaultProfile,
            '$pythonBinary'          => (string) $profileConfig['python_binary'],
            '$engineScript'          => $engineScript,
            '$timeout'               => (float) $profileConfig['timeout'],
            '$idleTimeout'           => (float) $profileConfig['idle_timeout'],
            '$maxUploadBytes'        => (int) $profileConfig['max_upload_bytes'],
            '$renderDpi'             => (int) $profileConfig['render_dpi'],
            '$workspaceDir'          => (string) $profileConfig['workspace_dir'],
            '$engineMode'            => (string) $profileConfig['engine_mode'],
            '$allowUnauthenticated'  => $allowUnauth,
            '$roles'                 => $roles,
        ]);
        $container->setDefinition('nowo_pdf_editor.profile', $profileDef);
        $container->setAlias(EditorProfile::class, 'nowo_pdf_editor.profile');

        $engine = new Definition(PythonPdfEngine::class, [
            '$processRunner' => new Reference('nowo_pdf_editor.process_runner'),
            '$profile'       => new Reference('nowo_pdf_editor.profile'),
        ]);
        $container->setDefinition('nowo_pdf_editor.engine', $engine);
        $container->setAlias(PdfEngineInterface::class, 'nowo_pdf_editor.engine');

        $workspace = new Definition(WorkspaceManager::class, [
            '$profile' => new Reference('nowo_pdf_editor.profile'),
        ]);
        $container->setDefinition('nowo_pdf_editor.workspace_manager', $workspace);
        $container->setAlias(WorkspaceManager::class, 'nowo_pdf_editor.workspace_manager');

        if ($allowUnauth) {
            $checker = new Definition(AllowAllPdfEditorAccessChecker::class);
        } else {
            $checker = new Definition(RolePdfEditorAccessChecker::class, [
                '$authorizationChecker' => new Reference('security.authorization_checker'),
                '$roles'                => $roles,
            ]);
        }
        $container->setDefinition('nowo_pdf_editor.access_checker', $checker);
        $container->setAlias(PdfEditorAccessCheckerInterface::class, 'nowo_pdf_editor.access_checker');

        $container->setParameter('nowo_pdf_editor.default_profile', $defaultProfile);
        $container->setParameter('nowo_pdf_editor.profiles', array_keys($profiles));
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('twig', [
            'paths' => [
                dirname(__DIR__) . '/Resources/views' => 'NowoPdfEditorBundle',
            ],
        ]);
        $container->prependExtensionConfig('framework', [
            'translator' => [
                'paths' => [dirname(__DIR__) . '/Resources/translations'],
            ],
        ]);
        $container->prependExtensionConfig('framework', [
            'assets' => [
                'packages' => [
                    'nowo_pdf_editor' => [
                        'base_path' => '/bundles/pdfeditor',
                    ],
                ],
            ],
        ]);
    }

    public function getAlias(): string
    {
        return 'nowo_pdf_editor';
    }
}
