<?php

declare(strict_types=1);

namespace Nowo\PdfEditorBundle\Tests\Integration;

use Nowo\PdfEditorBundle\Document\Workspace;
use Nowo\PdfEditorBundle\Form\PdfUploadType;
use Nowo\PdfEditorBundle\Tests\Support\FormKitTestSupport;
use Nowo\PdfEditorBundle\View\PdfEditorPages;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;

use function dirname;
use function html_entity_decode;
use function json_decode;
use function preg_match;
use function preg_match_all;
use function trim;

use const ENT_QUOTES;
use const JSON_THROW_ON_ERROR;

/**
 * REQ-UI-003 contract test of the editor pages, rendered against the UiKit stub host layout and stub theme:
 * the upload page (UiKit form scaffold) and the PDF workspace (unique page on the admin layout, see docs/TEMPLATES.md),
 * plus the deprecated 1.x layout shim.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class PageContractTest extends PageContractTestCase
{
    private const WORKSPACE_ID = '0123456789abcdef0123456789abcdef';

    public static function pageFixtures(): iterable
    {
        yield 'upload' => new PageFixture('@NowoPdfEditorBundle/editor/index.html.twig', self::uploadContext());
        yield 'workspace (client engine)' => new PageFixture('@NowoPdfEditorBundle/editor/workspace.html.twig', self::workspaceContext('client'));
        yield 'workspace (python engine)' => new PageFixture('@NowoPdfEditorBundle/editor/workspace.html.twig', self::workspaceContext('python'));
        yield 'workspace (extra editor config)' => new PageFixture('@NowoPdfEditorBundle/editor/workspace.html.twig', self::workspaceContext('client') + [
            'nowo_pdf_editor_config' => ['layout' => ['mode' => 'standalone']],
        ]);
    }

    public function testUploadFormIsMultipartExactlyOnce(): void
    {
        $page = $this->renderPage(new PageFixture('@NowoPdfEditorBundle/editor/index.html.twig', self::uploadContext()));

        self::assertSame(1, preg_match_all('/<form\b[^>]*enctype="multipart\/form-data"/', $page->html));
        self::assertSame(1, preg_match_all('/<input[^>]+type="file"/', $page->html));
        self::assertStringContainsString('Open PDF', $page->html);
        self::assertStringContainsString('Visual PDF editor', $page->html);
    }

    public function testWorkspaceMountsTheEditorWithItsConfigAndAssets(): void
    {
        $page = $this->renderPage(new PageFixture('@NowoPdfEditorBundle/editor/workspace.html.twig', self::workspaceContext('python') + [
            'nowo_pdf_editor_config' => ['layout' => ['mode' => 'standalone']],
        ]));

        self::assertSame(1, preg_match('/id="nowo-pdf-editor-react-root" data-config="([^"]+)"/', $page->html, $match));
        $config = json_decode(html_entity_decode($match[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(self::WORKSPACE_ID, $config['workspaceId']);
        self::assertSame('python', $config['engineMode']);
        self::assertSame('csrf-pdf_editor', $config['csrfToken']);
        self::assertSame(['mode' => 'standalone'], $config['layout']);
        self::assertSame('Save to PDF', $config['i18n']['action.commit']);
        self::assertStringContainsString('<script type="module" src="/assets/build/pdf-editor.js"></script>', $page->html);
        self::assertStringContainsString('href="/assets/build/pdf-editor.css"', $page->html);
        self::assertSame('report.pdf', trim($page->blockHtml('nowo_ui_document_title')));
    }

    public function testDeprecatedLayoutShimMapsThe1xBlocksOntoTheUiKitLayout(): void
    {
        $page = $this->renderPage(new PageFixture('legacy_page.html.twig', ['bundle' => PdfEditorPages::BUNDLE]));

        self::assertStringContainsString('<p class="legacy">1.x body</p>', $page->blockHtml('nowo_ui_content'));
        self::assertStringContainsString('legacy-script', $page->html);
        self::assertStringContainsString('PDF editor', $page->blockHtml('nowo_ui_document_title'));
        $this->assertCanonicalBlocksFilled($page);
        $this->assertNoForbiddenMarkup($page);
        $this->assertStubThemeReflected($page);
    }

    protected static function templatePaths(): array
    {
        return ['NowoPdfEditorBundle' => dirname(__DIR__, 2) . '/src/Resources/views'];
    }

    protected static function inlineTemplates(): array
    {
        return [
            'legacy_page.html.twig' => "{% extends '@NowoPdfEditorBundle/editor/layout.html.twig' %}"
                . '{% block nowo_pdf_editor_body %}<p class="legacy">1.x body</p>{% endblock %}'
                . '{% block nowo_pdf_editor_scripts %}<script type="module" src="/legacy-script.js"></script>{% endblock %}',
        ];
    }

    protected static function translationDirs(): array
    {
        return [dirname(__DIR__, 2) . '/src/Resources/translations'];
    }

    protected static function templatesDocument(): string
    {
        return dirname(__DIR__, 2) . '/docs/TEMPLATES.md';
    }

    protected static function strictMissingTranslations(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private static function uploadContext(): array
    {
        $form = FormKitTestSupport::formFactory()->create(PdfUploadType::class)->createView();

        return [
            'page'   => PdfEditorPages::upload($form),
            'form'   => $form,
            'layout' => null,
            'bundle' => PdfEditorPages::BUNDLE,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function workspaceContext(string $engineMode): array
    {
        return [
            'layout'      => null,
            'bundle'      => PdfEditorPages::BUNDLE,
            'workspace'   => new Workspace(self::WORKSPACE_ID, '/tmp/ws', '/tmp/ws/document.pdf', 'report.pdf'),
            'engine_mode' => $engineMode,
        ];
    }
}
