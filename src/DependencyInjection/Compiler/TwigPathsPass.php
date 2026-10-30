<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function dirname;
use function is_dir;
use function is_string;
use function rtrim;

/**
 * Registers the `@NowoPdfEditorBundle` Twig namespace with application overrides first (REQ-TWIG-001 / REQ-TWIG-002).
 *
 * When `templates/bundles/NowoPdfEditorBundle/` exists in the application it is registered with
 * `FilesystemLoader::prependPath()`, then the bundle `src/Resources/views` with `addPath()`: the application copy of a
 * `<subpath>` always wins over the vendor one. `@!NowoPdfEditorBundle` points at the bundle views only (for an override
 * that extends the vendor page it replaces). The 1.x namespace `@PdfEditor` stays registered on the bundle views
 * (after `templates/bundles/PdfEditorBundle/` when the host still has it) until 2.0.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class TwigPathsPass implements CompilerPassInterface
{
    public const TWIG_NAMESPACE = 'NowoPdfEditorBundle';

    /** @deprecated since 1.5, removed in 2.0: the 1.x Twig namespace (`@PdfEditor/...`) */
    public const LEGACY_TWIG_NAMESPACE = 'PdfEditor';

    /** Application override directory of the 1.x namespace (Symfony's default for the `PdfEditorBundle` class). */
    private const LEGACY_OVERRIDE_DIR = 'PdfEditorBundle';

    public function process(ContainerBuilder $container): void
    {
        $loaderId = $this->nativeLoaderId($container);
        if ($loaderId === null) {
            return;
        }

        $definition = $container->getDefinition($loaderId);
        $viewsPath  = dirname(__DIR__, 2) . '/Resources/views';
        $projectDir = $container->hasParameter('kernel.project_dir') ? $container->getParameter('kernel.project_dir') : null;
        $projectDir = is_string($projectDir) ? rtrim($projectDir, '/\\') : null;

        $override = $projectDir === null ? null : $projectDir . '/templates/bundles/' . self::TWIG_NAMESPACE;
        if ($override !== null && is_dir($override)) {
            // @igor-ignore - Container compile-time DI mutation; not runtime worker state.
            $definition->addMethodCall('prependPath', [$override, self::TWIG_NAMESPACE]);
        }
        // @igor-ignore - Container compile-time DI mutation; not runtime worker state.
        $definition->addMethodCall('addPath', [$viewsPath, self::TWIG_NAMESPACE]);
        // `@!NowoPdfEditorBundle/...` = the vendor copy only, for overrides that extend the page they replace.
        // @igor-ignore - Container compile-time DI mutation; not runtime worker state.
        $definition->addMethodCall('addPath', [$viewsPath, '!' . self::TWIG_NAMESPACE]);

        // BC (1.x): `@PdfEditor/...` keeps resolving; Symfony registers it itself only for the deprecated
        // PdfEditorBundle class, so add it for NowoPdfEditorBundle too (appended: never ahead of host paths).
        $legacyOverride = $projectDir === null ? null : $projectDir . '/templates/bundles/' . self::LEGACY_OVERRIDE_DIR;
        if ($legacyOverride !== null && is_dir($legacyOverride)) {
            // @igor-ignore - Container compile-time DI mutation; not runtime worker state.
            $definition->addMethodCall('addPath', [$legacyOverride, self::LEGACY_TWIG_NAMESPACE]);
        }
        // @igor-ignore - Container compile-time DI mutation; not runtime worker state.
        $definition->addMethodCall('addPath', [$viewsPath, self::LEGACY_TWIG_NAMESPACE]);
    }

    private function nativeLoaderId(ContainerBuilder $container): ?string
    {
        if ($container->hasAlias('twig.loader.native')) {
            $id = (string) $container->getAlias('twig.loader.native');
            for ($i = 0; $i < 32 && $container->hasAlias($id); ++$i) {
                $id = (string) $container->getAlias($id);
            }
            if ($container->hasDefinition($id)) {
                return $id;
            }
        }

        foreach (['twig.loader.native', 'twig.loader.native_filesystem'] as $id) {
            if ($container->hasDefinition($id)) {
                return $id;
            }
        }

        return null;
    }
}
