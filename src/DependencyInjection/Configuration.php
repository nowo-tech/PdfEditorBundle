<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function implode;
use function is_string;
use function sys_get_temp_dir;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('nowo_pdf_editor');
        $rootNode    = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('default_profile')
                    ->info('Name of the profile used when none is requested (REQ-CFG-001)')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('profiles')
                    ->info('Named editor profiles. Only the one named by default_profile is used; defining others is deprecated since 1.2.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('python_binary')
                                ->defaultValue('python3')
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('engine_script')
                                ->defaultValue('%kernel.project_dir%/vendor/nowo-tech/pdf-editor-bundle/engine/pdf_editor_engine.py')
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('workspace_dir')
                                ->defaultValue(sys_get_temp_dir() . '/nowo-pdf-editor')
                                ->cannotBeEmpty()
                            ->end()
                            ->floatNode('timeout')
                                ->info('Wall-clock timeout in seconds for the Python engine (REQ-RUNTIME-001)')
                                ->defaultValue(60.0)
                                ->min(1.0)
                            ->end()
                            ->floatNode('idle_timeout')
                                ->info('Idle timeout in seconds for the Python engine (REQ-RUNTIME-001)')
                                ->defaultValue(30.0)
                                ->min(1.0)
                            ->end()
                            ->integerNode('max_upload_bytes')
                                ->defaultValue(25 * 1024 * 1024)
                                ->min(1024)
                            ->end()
                            ->integerNode('render_dpi')
                                ->defaultValue(144)
                                ->min(72)
                                ->max(300)
                            ->end()
                            ->enumNode('engine_mode')
                                ->info('client = browser embedpdf/pdf-lib; python = PyMuPDF server engine')
                                ->values(['client', 'python'])
                                ->defaultValue('client')
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([
                        'default' => [
                            'python_binary'    => 'python3',
                            'engine_script'    => '%kernel.project_dir%/vendor/nowo-tech/pdf-editor-bundle/engine/pdf_editor_engine.py',
                            'workspace_dir'    => sys_get_temp_dir() . '/nowo-pdf-editor',
                            'timeout'          => 60.0,
                            'idle_timeout'     => 30.0,
                            'max_upload_bytes' => 25 * 1024 * 1024,
                            'render_dpi'       => 144,
                            'engine_mode'      => 'client',
                        ],
                    ])
                ->end()
                ->arrayNode('web_ui')
                    ->info('Editor pages (REQ-UI-001 / REQ-UI-003). Look and feel (css_framework, theme, layouts) come from nowo_ui_kit.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('layout_template')
                            ->info('Per-bundle layout override. null (default) = nowo_ui_kit.layouts.admin (the host layout or bridge rendering the UiKit canonical blocks).')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('templates')
                            ->info('Per-page template overrides: a host template that {% extends \'@!NowoPdfEditorBundle/...\' %} and redefines one data block.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('upload')
                                    ->defaultValue('@NowoPdfEditorBundle/editor/index.html.twig')
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('workspace')
                                    ->defaultValue('@NowoPdfEditorBundle/editor/workspace.html.twig')
                                    ->cannotBeEmpty()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('security')
                    ->info('Private-access contract (REQ-UI-002): access_roles, access_checker, allow_unauthenticated.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('access_roles')
                            ->info('The user must be granted at least one of these roles. An empty list denies everyone unless access_checker is set.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->defaultValue(['ROLE_ADMIN'])
                        ->end()
                        ->scalarNode('access_checker')
                            ->info('Service id of your own PdfEditorAccessCheckerInterface implementation; null = built-in role checker (access_roles).')
                            ->defaultNull()
                        ->end()
                        ->booleanNode('allow_unauthenticated')
                            ->info('DEV/DEMO only: open the editor to anonymous users (refused in prod and in non-debug kernels).')
                            ->defaultFalse()
                        ->end()
                        ->arrayNode('roles')
                            ->info('Deprecated since 1.5: use access_roles.')
                            ->setDeprecated('nowo-tech/pdf-editor-bundle', '1.5', 'The "%path%.%node%" option is deprecated, use "%path%.access_roles" instead.')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                    ->validate()
                        ->always(static function (array $v): array {
                            // BC: the 1.x `roles` key maps onto access_roles (unless access_roles is set too).
                            if (isset($v['roles']) && $v['roles'] !== [] && $v['access_roles'] === ['ROLE_ADMIN']) {
                                $v['access_roles'] = $v['roles'];
                            }
                            unset($v['roles']);

                            return $v;
                        })
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $v): bool => $v['access_roles'] === [] && $v['access_checker'] === null && !$v['allow_unauthenticated'])
                        ->thenInvalid('nowo_pdf_editor.security.access_roles is empty: nobody could use the editor. List at least one role, set security.access_checker to your own checker service, or (dev/demo only) allow_unauthenticated: true.')
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static function (array $v): bool {
                    $default = $v['default_profile'] ?? '';

                    return !is_string($default) || $default === '' || !array_key_exists($default, $v['profiles'] ?? []);
                })
                ->thenInvalid('nowo_pdf_editor.default_profile must exist as a key under nowo_pdf_editor.profiles (REQ-CFG-001).')
            ->end()
            ->validate()
                ->always(static function (array $v): array {
                    // Only default_profile is wired (one engine, workspace manager and controller per app); other profiles are dead.
                    $unused = array_diff(array_keys($v['profiles'] ?? []), [$v['default_profile'] ?? null]);
                    if ($unused !== []) {
                        trigger_deprecation(
                            'nowo-tech/pdf-editor-bundle',
                            '1.2',
                            'Defining profiles other than "nowo_pdf_editor.default_profile" is deprecated: only the default profile is used, so "%s" has no effect. Keep a single profile; support for several profiles will be removed in 2.0.',
                            implode('", "', $unused),
                        );
                    }

                    return $v;
                })
            ->end();

        return $treeBuilder;
    }
}
