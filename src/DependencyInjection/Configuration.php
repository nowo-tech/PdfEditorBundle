<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function array_key_exists;
use function is_string;
use function sprintf;
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
                    ->info('Named editor profiles')
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
                ->arrayNode('security')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('allow_unauthenticated')
                            ->info('When true, skip role checks (demos only).')
                            ->defaultFalse()
                        ->end()
                        ->arrayNode('roles')
                            ->scalarPrototype()->end()
                            ->defaultValue(['ROLE_ADMIN'])
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static function (array $v): bool {
                    $default = $v['default_profile'] ?? '';

                    return !is_string($default) || $default === '' || !array_key_exists($default, $v['profiles'] ?? []);
                })
                ->thenInvalid('nowo_pdf_editor.default_profile must exist as a key under nowo_pdf_editor.profiles (REQ-CFG-001).')
            ->end();

        return $treeBuilder;
    }
}
