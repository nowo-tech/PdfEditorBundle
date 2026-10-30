# Templates

PdfEditorBundle follows the UiKit 2.x **page contract** (REQ-UI-003, `nowo-tech/ui-kit-bundle` ^2.17.3). It ships two
pages: the upload page (UiKit **form** scaffold) and the PDF workspace (a **unique page** on the UiKit admin layout:
the React editor does not fit a scaffold). References: UiKit `docs/PAGE_CONTRACT.md`, `docs/THEME_SLOTS.md`.

## Table of contents

- [Pages](#pages)
- [Unique pages](#unique-pages)
- [Overriding templates](#overriding-templates)
- [Per-page overrides](#per-page-overrides)
- [Layout](#layout)
- [Deprecated templates](#deprecated-templates)
- [Allowed exceptions](#allowed-exceptions)

## Pages

| Page | Route | Template (`<subpath>`) | Scaffold | View model |
| --- | --- | --- | --- | --- |
| Upload | `nowo_pdf_editor_home` (`/pdf-editor`) | `editor/index.html.twig` | `@NowoUiKit/page/form.html.twig` | `page` = `Nowo\UiKitBundle\View\FormPage` from `Nowo\PdfEditorBundle\View\PdfEditorPages::upload()` |

Upload page view model: header title `pdf_editor.home.heading`, intro `pdf_editor.home.lead`, form = `PdfUploadType`
(multipart, one `pdf` file field), submit `pdf_editor.home.open`, bundle `NowoPdfEditorBundle`, layout =
`web_ui.layout_template`, root attribute `data-testid="pdf-editor-upload"`. Data blocks: `nowo_ui_form_body` (the
REQ-TWIG-003 child loop, scaffold default) and `nowo_ui_form_aside` (empty). Other context: `form` (the same
`FormView`), `layout`, `bundle`.

## Unique pages

- `editor/workspace.html.twig` — the PDF workspace (`/pdf-editor/{id}`): the React editor (toolbar, page rail, viewer,
  draft and properties panels) mounted on `#nowo-pdf-editor-react-root`. Extends `layout ?? nowo_ui_layout.admin`
  (`layout` = `web_ui.layout_template`), sets `nowo_ui_bundle` (`NowoPdfEditorBundle`) and fills
  `nowo_ui_document_title` (the original file name), `nowo_ui_styles` (`css/pdf-editor.css` and
  `build/pdf-editor.css` of the `nowo_pdf_editor` asset package, after `parent()`), `nowo_ui_scripts`
  (`build/pdf-editor.js` module, after `parent()`) and `nowo_ui_content` (theme slot `section` + its attributes,
  `data-testid="pdf-editor-workspace"`). `nowo_ui_page_header` stays empty: the editor toolbar is the page header.
  Data blocks: `nowo_pdf_editor_styles`, `nowo_pdf_editor_scripts`, `nowo_pdf_editor_body` (the editor mount).
  Context: `layout`, `bundle`, `workspace` (`id`, `originalName`), `engine_mode` (`client` / `python`), `textForm`.
  Optional variable `nowo_pdf_editor_config`: a hash merged into the editor configuration (e.g.
  `{layout: {mode: 'standalone'}}` or a demo `scenario`). The editor surfaces use their own `nowo-pdf-editor-*`
  classes (painted by `css/pdf-editor.css` and `build/pdf-editor.css`); no framework classes.

## Overriding templates

REQ-TWIG-001. Every template above is overridable by the application: create
`templates/bundles/NowoPdfEditorBundle/<subpath>` (same relative path as under the bundle's `src/Resources/views/`). That
file **always wins** over the vendor copy (`TwigPathsPass` prepends the application directory to the
`@NowoPdfEditorBundle` namespace and appends the bundle views after it; the namespace is registered whenever the bundle
is enabled).

**Freeze rule:** an application file at the override path always wins and **will not pick up vendor changes** for that
`<subpath>` until you delete it or merge the new vendor version by hand. Prefer, in this order:

1. **No Twig fork** — `nowo_ui_kit.layouts.admin` (your layout or bridge, once for every UiKit page),
   `nowo_ui_kit.theme` (class map, tokens), `nowo_pdf_editor.web_ui.layout_template`, and the `NowoPdfEditorBundle`
   translation domain. Upgrades keep shipping the editor UI.
2. **Surgical override** — a file that `{% extends '@!NowoPdfEditorBundle/<subpath>' %}` (`@!` = the vendor copy only)
   and redefines one data block (`nowo_ui_form_aside`, `nowo_pdf_editor_body`, `nowo_pdf_editor_styles`, …) with
   `{{ parent() }}` where useful. Only that block is frozen.
3. **Full-file override** — copy the whole page: total control, the whole `<subpath>` is frozen.

Procedure:

1. Pick the `<subpath>` from the table below.
2. Create `templates/bundles/NowoPdfEditorBundle/<subpath>` in the application.
3. Extend `@!NowoPdfEditorBundle/<subpath>` and redefine only the blocks you need (or copy the vendor file for a full
   override).
4. Clear the cache in dev if needed: `php bin/console cache:clear`.

| `<subpath>` | Purpose |
| --- | --- |
| `editor/index.html.twig` | Upload page (UiKit form scaffold; blocks `nowo_ui_form_body`, `nowo_ui_form_aside`) |
| `editor/workspace.html.twig` | PDF workspace (unique page; blocks `nowo_pdf_editor_body`, `nowo_pdf_editor_styles`, `nowo_pdf_editor_scripts`) |
| `editor/layout.html.twig` | Deprecated 1.x layout shim (see below) |
| `editor/_toolbar.html.twig` | Deprecated 1.x toolbar partial, not rendered by the bundle |

**Recovering upstream after a freeze:** delete (or rename aside) the application file, clear the Twig cache, diff your
old copy against `vendor/nowo-tech/pdf-editor-bundle/src/Resources/views/<subpath>`, and re-apply only what you still
need as a surgical override.

Example (the demo does this): add a "how it works" panel next to the upload form without forking the page.

```twig
{# templates/bundles/NowoPdfEditorBundle/editor/index.html.twig #}
{% extends '@!NowoPdfEditorBundle/editor/index.html.twig' %}

{% block nowo_ui_form_aside %}
    <aside class="app-help">…</aside>
{% endblock %}
```

## Per-page overrides

`nowo_pdf_editor.web_ui.templates.upload` / `.workspace` (defaults `@NowoPdfEditorBundle/editor/index.html.twig` /
`@NowoPdfEditorBundle/editor/workspace.html.twig`): render a host template instead, for a host that keeps its
overrides outside `templates/bundles/`. It should `{% extends '@!NowoPdfEditorBundle/...' %}` and redefine one data
block, as above. Do not copy the whole page.

## Layout

`web_ui.layout_template` — default `null` = `nowo_ui_kit.layouts.admin` (the host layout or the one-file bridge that
renders the UiKit canonical blocks; see UiKit `layout/_bridge.html.twig`). Set it only when the editor needs a
different layout than the other UiKit pages of the application. The bundle ships no layout of its own and loads no CSS
framework; look and feel come from `nowo_ui_kit.css_framework` and `nowo_ui_kit.theme` (per-bundle exceptions under
`nowo_ui_kit.theme.bundles.NowoPdfEditorBundle`).

## Deprecated templates

Kept for 1.x hosts until 2.0 (see [UPGRADING](UPGRADING.md)):

- `editor/layout.html.twig` — no longer a standalone HTML document: it extends `layout ?? nowo_ui_layout.admin` and maps
  the 1.x blocks onto the canonical ones (`nowo_pdf_editor_title` → `nowo_ui_document_title`, `nowo_pdf_editor_body` →
  `nowo_ui_content`, `nowo_pdf_editor_scripts` → `nowo_ui_scripts`), so 1.x pages that extend it keep rendering inside
  the host layout. The bundle pages do not use it.
- `editor/_toolbar.html.twig` — the 1.x server-rendered toolbar (now `nowo-pdf-editor-btn*` classes); not rendered.

## Allowed exceptions

None: the contract test (`tests/Integration/PageContractTest.php`) runs with the default allow-list and strict
missing-translation checks.
