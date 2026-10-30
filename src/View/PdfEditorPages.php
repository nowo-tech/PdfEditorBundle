<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\View;

use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\PageHeader;
use Symfony\Component\Form\FormView;

/**
 * View models of the editor pages (REQ-UI-003): plain data, no services or request state (worker-safe).
 *
 * - upload page (`editor/index.html.twig`): UiKit form scaffold fed by {@see self::upload()};
 * - workspace (`editor/workspace.html.twig`): unique page on the UiKit admin layout (see docs/TEMPLATES.md).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class PdfEditorPages
{
    /** Twig namespace, translation domain and UiKit per-bundle theme key (REQ-TWIG-002 / REQ-I18N-003). */
    public const BUNDLE = 'NowoPdfEditorBundle';

    public static function upload(FormView $form, ?string $layout = null): FormPage
    {
        return new FormPage(
            header: new PageHeader(
                title: self::label('pdf_editor.home.heading'),
                intro: self::label('pdf_editor.home.lead'),
            ),
            form: $form,
            submitLabel: self::label('pdf_editor.home.open'),
            bundle: self::BUNDLE,
            layout: $layout,
            attributes: ['data-testid' => 'pdf-editor-upload'],
        );
    }

    public static function label(string $key): Label
    {
        return new Label($key, self::BUNDLE);
    }
}
