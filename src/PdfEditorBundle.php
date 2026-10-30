<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;

use function trigger_deprecation;

/**
 * Deprecated 1.x bundle class, kept so existing `config/bundles.php` entries and
 * `@PdfEditorBundle/Resources/config/routing.yaml` imports keep working until 2.0.
 *
 * Register {@see NowoPdfEditorBundle} instead (see docs/UPGRADING.md). With this class the Symfony bundle name stays
 * `PdfEditorBundle` (public assets under `/bundles/pdfeditor`); the Twig namespace is `@NowoPdfEditorBundle` either way.
 *
 * @deprecated since 1.5, use {@see NowoPdfEditorBundle}; removed in 2.0
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class PdfEditorBundle extends NowoPdfEditorBundle
{
    public function build(ContainerBuilder $container): void
    {
        trigger_deprecation(
            'nowo-tech/pdf-editor-bundle',
            '1.5',
            'The "%s" bundle class is deprecated, register "%s" in config/bundles.php instead and import "@NowoPdfEditorBundle/Resources/config/routing.yaml" (see docs/UPGRADING.md).',
            self::class,
            NowoPdfEditorBundle::class,
        );

        parent::build($container);
    }
}
