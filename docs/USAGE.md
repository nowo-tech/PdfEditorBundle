# Usage

The bundle is a Symfony PDF workspace with a **React editor** (embedpdf + pdf-lib). The browser collects a **draft**; **Save to PDF** sends the exported bytes to Symfony.

1. Grant `ROLE_ADMIN` (or list your roles in `security.access_roles`; `security.allow_unauthenticated: true` only in a demo).
2. Point `nowo_ui_kit.layouts.admin` at your layout (once for all UiKit pages) and run `php bin/console assets:install` (the compiled editor ships in `src/Resources/public/build`; rebuild only when you change its sources: `make assets` in the bundle repository).
3. Open `/pdf-editor` and upload a PDF.
4. Edit in the workspace:
   - Use the **shell toolbar** and embedpdf viewport (see [ADR 0001](adr/0001-unified-editor-ux-ui.md) for the target unified UX).
   - Queued changes appear in the **Draft** panel until you save.
5. Click **Save to PDF**. Symfony `POST /pdf-editor/{id}/apply` accepts `Content-Type: application/pdf` (client engine) or legacy `{"ops":[...]}` when `engine_mode: python`.
6. **Download** returns the last saved PDF (not the unsaved draft).

Inline PDF for the viewer: `GET /pdf-editor/{id}/view`.

JSON apply (Python engine only): `POST /pdf-editor/{id}/apply` with header `X-CSRF-TOKEN` and body `{"ops":[{"op":"replace_text",...}]}`.

## Twig templates and layout

The upload page is the UiKit form scaffold and the workspace a unique page on the UiKit admin layout; both render
inside `nowo_ui_kit.layouts.admin` (or `nowo_pdf_editor.web_ui.layout_template`). The upload form is a Symfony Form
built as a FormKit Type (`PdfUploadType`, profile `nowo_form_kit.profiles.pdf_editor`, REQ-TWIG-005 rule 11 — see
[CONFIGURATION](CONFIGURATION.md#form-profile-formkit)) rendered with `form_start` / child loop / `form_end`; overrides
must not reintroduce raw `<form>` / `<input>` markup. Templates use the
`@NowoPdfEditorBundle` namespace and can be overridden under `templates/bundles/NowoPdfEditorBundle/<subpath>`; an
application file there **always wins** and freezes that `<subpath>` (it will not pick up vendor changes until you delete
or merge it), so prefer the layout / theme configuration or a surgical override that extends
`@!NowoPdfEditorBundle/<subpath>`. The list of `<subpath>` values, blocks, the procedure and how to recover upstream
after a freeze are in [TEMPLATES.md](TEMPLATES.md#overriding-templates); configuration keys in
[CONFIGURATION.md](CONFIGURATION.md#web-ui-layout-look-and-feel-templates).
